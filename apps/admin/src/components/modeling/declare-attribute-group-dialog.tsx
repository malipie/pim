import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Layers, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { HttpError, jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface AttributeGroupRow {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  description?: Record<string, string> | string | null;
  icon?: string | null;
  color?: string | null;
  is_system_group?: boolean;
  isSystemGroup?: boolean;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  categoryId: string;
  /** ObjectKind discriminator the declaration targets (`product` / `service` / `category` / ...). */
  targetObjectTypeKind: string;
  /** AttributeGroup IDs already declared on this category for this target — disabled with a tooltip. */
  declaredGroupIds: Set<string>;
  /** AttributeGroup IDs inherited from ancestors — disabled with a different tooltip. */
  inheritedFromMap: Map<string, string>;
  /** Called after a successful declare so caller can refresh declared list + effective preview. */
  onDeclared: () => void;
}

/**
 * VIEW-04 (#408) — pop-up to declare one or more AttributeGroups on a
 * category for a given target ObjectType. Modeled on
 * {@link AddAttributesFromLibraryDialog}: search + checkbox list,
 * disabled rows for groups already declared / inherited (with tooltip
 * explaining which ancestor contributes them), parallel POSTs on submit
 * — failures bubble up as a single error banner so the operator can
 * retry. The category Detail panel revalidates `declaredGroups` +
 * `effectiveGroups` queries on success.
 */
export function DeclareAttributeGroupDialog({
  open,
  onOpenChange,
  categoryId,
  targetObjectTypeKind,
  declaredGroupIds,
  inheritedFromMap,
  onDeclared,
}: Props) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [q, setQ] = useState('');
  const [picked, setPicked] = useState<Set<string>>(new Set());
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      setQ('');
      setPicked(new Set());
      setError(null);
    }
  }, [open]);

  const { data: groups = [] } = useQuery<AttributeGroupRow[]>({
    queryKey: ['attribute_groups', 'picker'],
    queryFn: async () => {
      const data = await jsonFetch<{ 'hydra:member'?: AttributeGroupRow[] }>(
        '/api/attribute_groups?itemsPerPage=200',
        { accept: 'application/ld+json' },
      );
      return data['hydra:member'] ?? [];
    },
    enabled: open,
    staleTime: 30_000,
  });

  const filtered = useMemo(() => {
    const needle = q.trim().toLowerCase();
    if (!needle) return groups;
    return groups.filter(
      (g) =>
        g.code.toLowerCase().includes(needle) ||
        labelString(g.label).toLowerCase().includes(needle),
    );
  }, [groups, q]);

  const handleToggle = (id: string) => {
    setPicked((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleSubmit = async () => {
    if (picked.size === 0) {
      onOpenChange(false);
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      // Sequential POSTs — server is idempotent on duplicate, so a retry
      // after partial failure is safe. Sequential keeps audit-log
      // ordering deterministic + lets us short-circuit on the first
      // 4xx. Set is small (<10 typically) so latency is not a concern.
      for (const groupId of picked) {
        await jsonFetch(`/api/categories/${categoryId}/attribute_groups`, {
          method: 'POST',
          contentType: 'application/json',
          body: { groupId, targetObjectTypeKind },
        });
      }
      await queryClient.invalidateQueries({
        queryKey: ['categories', categoryId, 'attribute_groups', targetObjectTypeKind],
      });
      await queryClient.invalidateQueries({
        queryKey: ['categories', categoryId, 'effective-groups', targetObjectTypeKind],
      });
      onDeclared();
      onOpenChange(false);
    } catch (err) {
      setError(
        err instanceof HttpError
          ? typeof err.body === 'object' && err.body !== null && 'detail' in err.body
            ? String((err.body as { detail: unknown }).detail)
            : err.message
          : err instanceof Error
            ? err.message
            : 'unknown',
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[640px] gap-0 p-0">
        <div className="border-b border-zinc-100 px-7 pb-4 pt-6">
          <div className="flex items-center gap-2">
            <Layers className="size-4 text-zinc-500" />
            <h2 className="text-[15px] font-semibold tracking-tight">
              {t('categories.declare_dialog.title', {
                defaultValue: 'Declare attribute group',
              })}
            </h2>
          </div>
          <p className="mt-1 text-[12.5px] text-zinc-500">
            {t('categories.declare_dialog.description', {
              defaultValue:
                'Wybierz grupy atrybutów które obiekty typu „{{kind}}" w tej kategorii (i jej dzieciach) będą widzieć w formularzu.',
              kind: targetObjectTypeKind,
            })}
          </p>
        </div>

        <div className="space-y-3 px-7 py-4">
          <div className="flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2">
            <Search className="size-4 text-zinc-400" />
            <input
              type="text"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder={t('categories.declare_dialog.search_placeholder', {
                defaultValue: 'Szukaj grup…',
              })}
              className="flex-1 bg-transparent text-[13px] outline-none placeholder:text-zinc-400"
            />
          </div>

          <div className="max-h-[420px] divide-y divide-zinc-50 overflow-y-auto rounded-xl border border-zinc-100">
            {filtered.length === 0 ? (
              <p className="px-4 py-6 text-center text-[13px] text-muted-foreground">
                {t('categories.declare_dialog.empty_results', {
                  defaultValue: 'Brak wyników.',
                })}
              </p>
            ) : (
              filtered.map((g) => {
                const isDeclared = declaredGroupIds.has(g.id);
                const inheritedFrom = inheritedFromMap.get(g.id);
                const isInherited = !isDeclared && Boolean(inheritedFrom);
                const isDisabled = isDeclared || isInherited;
                const isPicked = picked.has(g.id);
                const isSystem = Boolean(g.is_system_group ?? g.isSystemGroup);

                return (
                  <button
                    key={g.id}
                    type="button"
                    disabled={isDisabled}
                    onClick={() => !isDisabled && handleToggle(g.id)}
                    title={
                      isDeclared
                        ? t('categories.declare_dialog.already_declared_tooltip', {
                            defaultValue: 'Już zadeklarowane na tej kategorii.',
                          })
                        : isInherited
                          ? t('categories.declare_dialog.already_inherited_tooltip', {
                              defaultValue: 'Dziedziczone z „{{from}}".',
                              from: inheritedFrom,
                            })
                          : undefined
                    }
                    className={cn(
                      'flex w-full items-center gap-3 px-4 py-3 text-left transition',
                      isDisabled
                        ? 'cursor-not-allowed bg-zinc-50/60 opacity-60'
                        : 'hover:bg-zinc-50',
                      isPicked && !isDisabled && 'bg-violet-50/60',
                    )}
                  >
                    <span
                      className={cn(
                        'grid size-5 place-items-center rounded border',
                        isPicked
                          ? 'border-violet-500 bg-violet-500 text-white'
                          : 'border-zinc-300 bg-white',
                      )}
                    >
                      {isPicked ? '✓' : ''}
                    </span>
                    <span
                      className="grid size-8 place-items-center rounded-md text-[14px]"
                      style={{
                        background: g.color ? `${g.color}1f` : '#f4f4f5',
                        color: g.color ?? '#71717a',
                      }}
                    >
                      {g.icon ?? '📦'}
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span className="text-[13.5px] font-medium tracking-tight">
                          {labelString(g.label) || g.code}
                        </span>
                        {isSystem ? <BuiltInLockBadge tone="quiet" /> : null}
                      </div>
                      <div className="font-mono text-[11px] text-zinc-400">{g.code}</div>
                    </div>
                    {isInherited ? (
                      <span className="rounded bg-white px-2 py-0.5 font-mono text-[10.5px] text-zinc-500">
                        ↪ {inheritedFrom}
                      </span>
                    ) : null}
                  </button>
                );
              })
            )}
          </div>

          {error !== null ? (
            <p className="rounded-md bg-rose-50 px-3 py-2 text-[12px] text-rose-700">{error}</p>
          ) : null}
        </div>

        <div className="flex items-center justify-between border-t border-zinc-100 bg-zinc-50/60 px-7 py-4">
          <span className="text-[12px] text-muted-foreground">
            {t('categories.declare_dialog.picked_count', {
              defaultValue: 'Wybrano: {{count}}',
              count: picked.size,
            })}
          </span>
          <div className="flex items-center gap-2">
            <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={submitting}>
              {t('categories.declare_dialog.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button onClick={handleSubmit} disabled={submitting || picked.size === 0}>
              {t('categories.declare_dialog.submit', { defaultValue: 'Zadeklaruj' })}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function labelString(label: Record<string, string> | string | null | undefined): string {
  if (typeof label === 'string') return label;
  if (label && typeof label === 'object')
    return label.pl ?? label.en ?? Object.values(label)[0] ?? '';
  return '';
}
