import { useQuery } from '@tanstack/react-query';
import { Check, Layers, Search, X } from 'lucide-react';
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
  icon?: string | null;
  color?: string | null;
  is_system_group?: boolean;
  isSystemGroup?: boolean;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Empty string in pick-only mode (wizard, OT not yet created). */
  objectTypeId: string;
  objectTypeName: string;
  /** AttributeGroup IDs already attached — disabled in the picker. */
  attachedIds: Set<string>;
  /**
   * Default mode: dialog POSTs each pick to `/api/object_types/{id}/groups/{groupId}`,
   * then calls `onAttached()`. When `onPicked` is provided, the dialog skips
   * the API calls and just hands the picked IDs to the caller — used by the
   * wizard which collects picks pre-creation and attaches in one batch
   * after `POST /api/object_types`.
   */
  onAttached?: () => void;
  onPicked?: (ids: Set<string>) => void;
  locale: string;
}

/**
 * VIEW-01b (#413) — picker dialog for declaring (attaching) existing
 * AttributeGroups to an ObjectType. Mirrors `DeclareAttributeGroupDialog`
 * (which targets categories with kind discriminator) but submits directly
 * to the simpler `POST /api/object_types/{id}/groups/{groupId}` idempotent
 * endpoint — no inheritance, no per-kind targeting.
 */
export function DeclareObjectTypeAttributeGroupDialog({
  open,
  onOpenChange,
  objectTypeId,
  objectTypeName,
  attachedIds,
  onAttached,
  onPicked,
  locale,
}: Props) {
  const { t } = useTranslation();
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
      const data = await jsonFetch<{ member?: AttributeGroupRow[] }>(
        '/api/attribute_groups?itemsPerPage=200',
      );
      return data.member ?? [];
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
        labelString(g.label, locale).toLowerCase().includes(needle),
    );
  }, [groups, q, locale]);

  const toggle = (id: string) => {
    setPicked((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const submit = async () => {
    if (picked.size === 0) {
      onOpenChange(false);
      return;
    }

    // Pick-only mode (wizard): hand IDs to the caller, no API call.
    if (onPicked) {
      onPicked(new Set(picked));
      onOpenChange(false);
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      // Sequential POSTs — endpoint is idempotent, sequential keeps audit-log
      // ordering deterministic and lets us short-circuit on the first 4xx.
      const failed: string[] = [];
      for (const groupId of picked) {
        try {
          await jsonFetch(`/api/object_types/${objectTypeId}/groups/${groupId}`, {
            method: 'POST',
          });
        } catch (err) {
          failed.push(groupId);
          if (err instanceof HttpError) {
            const detail =
              err.body && typeof err.body === 'object' && 'detail' in err.body
                ? String((err.body as Record<string, unknown>).detail)
                : null;
            setError(detail ?? `HTTP ${err.status}`);
          }
        }
      }
      if (failed.length === 0) {
        onAttached?.();
        onOpenChange(false);
      } else {
        // Partial success — inform the caller so it can refresh, leave
        // the dialog open so the operator can retry remaining picks.
        onAttached?.();
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[640px] gap-0 p-0">
        <div className="flex items-start gap-3 border-b border-zinc-100 px-7 pb-4 pt-6">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-zinc-900 text-white">
            <Layers className="size-4" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="font-display text-[18px] font-semibold tracking-tight">
              {t('modeling.objectTypes.declare_group.title', {
                defaultValue: 'Dołącz grupy z biblioteki',
              })}
            </div>
            <div className="mt-0.5 text-[12.5px] text-muted-foreground">
              {t('modeling.objectTypes.declare_group.desc_prefix', {
                defaultValue: 'Wybierz istniejące grupy atrybutów do dołączenia do typu',
              })}{' '}
              <span className="font-medium text-foreground">„{objectTypeName}"</span>.
              {attachedIds.size > 0 ? (
                <span>
                  {' '}
                  {t('modeling.objectTypes.declare_group.attached_note', {
                    defaultValue: 'Grupy już dołączone są wyłączone.',
                  })}
                </span>
              ) : null}
            </div>
          </div>
          <button
            type="button"
            onClick={() => onOpenChange(false)}
            className="grid size-9 shrink-0 place-items-center rounded-xl text-muted-foreground hover:bg-zinc-100"
            aria-label={t('app.close', { defaultValue: 'Zamknij' })}
          >
            <X className="size-4" />
          </button>
        </div>

        <div className="space-y-3 px-7 py-4">
          <div className="flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2">
            <Search className="size-4 text-zinc-500" />
            <input
              type="text"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder={t('modeling.objectTypes.declare_group.search_placeholder', {
                defaultValue: 'Szukaj grup…',
              })}
              className="flex-1 bg-transparent text-[13px] outline-none placeholder:text-zinc-400"
            />
          </div>

          <div className="max-h-[420px] divide-y divide-zinc-50 overflow-y-auto rounded-xl border border-zinc-100">
            {filtered.length === 0 ? (
              <p className="px-4 py-6 text-center text-[13px] text-muted-foreground">
                {t('modeling.objectTypes.declare_group.empty_results', {
                  defaultValue: 'Brak wyników.',
                })}
              </p>
            ) : (
              filtered.map((g) => {
                const isAttached = attachedIds.has(g.id);
                const isPicked = picked.has(g.id);
                const isSystem = Boolean(g.is_system_group ?? g.isSystemGroup);

                return (
                  <button
                    key={g.id}
                    type="button"
                    disabled={isAttached}
                    onClick={() => !isAttached && toggle(g.id)}
                    title={
                      isAttached
                        ? t('modeling.objectTypes.declare_group.already_attached_tooltip', {
                            defaultValue: 'Już dołączone do tego typu.',
                          })
                        : undefined
                    }
                    className={cn(
                      'flex w-full items-center gap-3 px-4 py-3 text-left transition',
                      isAttached
                        ? 'cursor-not-allowed bg-zinc-50/60 opacity-60'
                        : 'hover:bg-zinc-50',
                      isPicked && !isAttached && 'bg-orange-50/60',
                    )}
                  >
                    <span
                      className={cn(
                        'grid size-5 place-items-center rounded border',
                        isPicked
                          ? 'border-orange-500 bg-orange-500 text-white'
                          : 'border-zinc-300 bg-white',
                      )}
                    >
                      {isPicked ? <Check className="size-3" /> : null}
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
                          {labelString(g.label, locale) || g.code}
                        </span>
                        {isSystem ? <BuiltInLockBadge /> : null}
                      </div>
                      <div className="font-mono text-[11px] text-zinc-500">{g.code}</div>
                    </div>
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
            {t('modeling.objectTypes.declare_group.picked_count', {
              defaultValue: 'Wybrano: {{count}}',
              count: picked.size,
            })}
          </span>
          <div className="flex items-center gap-2">
            <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={submitting}>
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button onClick={() => void submit()} disabled={submitting || picked.size === 0}>
              <Check className="size-4" />
              {t('modeling.objectTypes.declare_group.submit_action', {
                defaultValue: 'Dołącz {{count}}',
                count: picked.size,
              })}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function labelString(
  label: Record<string, string> | string | null | undefined,
  locale: string,
): string {
  if (typeof label === 'string') return label;
  if (label && typeof label === 'object') {
    return label[locale] ?? label.pl ?? label.en ?? Object.values(label)[0] ?? '';
  }
  return '';
}
