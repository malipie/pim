import { useQuery } from '@tanstack/react-query';
import { Check, Layers, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { resolveLabel } from '@/features/catalog/attributes/list';
import { HttpError, jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface AttributeRow {
  id: string;
  code: string;
  type: string;
  label?: Record<string, string> | string | null;
  system?: boolean;
}

const TYPE_OPTIONS = [
  'all',
  'text',
  'number',
  'boolean',
  'select',
  'multiselect',
  'date',
  'datetime',
  'asset',
  'reference',
  'relation',
  'price',
  'metric',
] as const;

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  objectTypeId: string;
  objectTypeName: string;
  /** UUIDs of attributes already direct-attached — disabled in the picker. */
  existingIds: Set<string>;
  /** Called after a successful bulk-attach so caller can refresh the list. */
  onAttached: () => void;
  locale: string;
}

/**
 * VIEW-01b (#413) — picker dialog for direct-attaching existing Attributes
 * to an ObjectType (bypassing AttributeGroup). Mirrors
 * `AddAttributesFromLibraryDialog` but submits to
 * `POST /api/object_types/{id}/attributes/bulk-attach` with `attributeIds`
 * instead of `attributeCodes` — the OT junction is keyed by UUID, not code.
 */
export function AddAttributesToObjectTypeDialog({
  open,
  onOpenChange,
  objectTypeId,
  objectTypeName,
  existingIds,
  onAttached,
  locale,
}: Props) {
  const { t } = useTranslation();
  const [q, setQ] = useState('');
  const [typeFilter, setTypeFilter] = useState<(typeof TYPE_OPTIONS)[number]>('all');
  const [picked, setPicked] = useState<Set<string>>(new Set());
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      setQ('');
      setTypeFilter('all');
      setPicked(new Set());
      setError(null);
    }
  }, [open]);

  const { data: attributes = [] } = useQuery<AttributeRow[]>({
    queryKey: ['attributes', 'picker'],
    queryFn: async () => {
      const data = await jsonFetch<{ member?: AttributeRow[] }>('/api/attributes?itemsPerPage=200');
      return data.member ?? [];
    },
    enabled: open,
    staleTime: 30_000,
  });

  const filtered = useMemo(() => {
    const needle = q.trim().toLowerCase();
    return attributes.filter((row) => {
      if (typeFilter !== 'all' && row.type !== typeFilter) return false;
      if (needle.length > 0) {
        const code = row.code.toLowerCase();
        const labelStr =
          typeof row.label === 'string'
            ? row.label.toLowerCase()
            : row.label !== null && typeof row.label === 'object'
              ? Object.values(row.label).join(' ').toLowerCase()
              : '';
        if (!code.includes(needle) && !labelStr.includes(needle)) return false;
      }
      return true;
    });
  }, [attributes, q, typeFilter]);

  const toggle = (id: string) => {
    setPicked((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const submit = async () => {
    if (picked.size === 0) return;
    setSubmitting(true);
    setError(null);
    try {
      await jsonFetch(`/api/object_types/${objectTypeId}/attributes/bulk-attach`, {
        method: 'POST',
        contentType: 'application/json',
        accept: 'application/json',
        body: { attributeIds: Array.from(picked) },
      });
      onAttached();
      onOpenChange(false);
    } catch (err) {
      if (err instanceof HttpError) {
        const detail =
          err.body && typeof err.body === 'object' && 'detail' in err.body
            ? String((err.body as Record<string, unknown>).detail)
            : null;
        setError(detail ?? `HTTP ${err.status}`);
      } else {
        setError(
          t('modeling.objectTypes.add_attributes.error', {
            defaultValue: 'Nie udało się dołączyć atrybutów',
          }),
        );
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[780px] gap-0 p-0">
        <div className="flex items-start gap-3 border-b border-zinc-100 px-7 pb-4 pt-6">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-zinc-900 text-white">
            <Layers className="size-4" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="font-display text-[18px] font-semibold tracking-tight">
              {t('modeling.objectTypes.add_attributes.title', {
                defaultValue: 'Dodaj atrybuty z biblioteki',
              })}
            </div>
            <div className="mt-0.5 text-[12.5px] text-muted-foreground">
              {t('modeling.objectTypes.add_attributes.desc_prefix', {
                defaultValue: 'Wybierz istniejące atrybuty do dołączenia bezpośrednio do typu',
              })}{' '}
              <span className="font-medium text-foreground">„{objectTypeName}"</span>.
              {existingIds.size > 0 ? (
                <span>
                  {' '}
                  {t('modeling.objectTypes.add_attributes.existing_note', {
                    defaultValue: 'Atrybuty już dołączone są wyłączone.',
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

        <div className="flex items-center gap-2 px-7 pb-3 pt-4">
          <div className="flex h-10 flex-1 items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3">
            <Search className="size-4 text-muted-foreground" />
            <input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder={t('modeling.objectTypes.add_attributes.search_placeholder', {
                defaultValue: 'Szukaj atrybutów po code lub nazwie…',
              })}
              className="flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
            />
          </div>
          <select
            value={typeFilter}
            onChange={(e) => setTypeFilter(e.target.value as (typeof TYPE_OPTIONS)[number])}
            className="h-10 rounded-xl border border-zinc-200 bg-white px-3 text-[13px] font-medium"
          >
            {TYPE_OPTIONS.map((opt) => (
              <option key={opt} value={opt}>
                {opt === 'all' ? 'Wszystkie typy' : opt}
              </option>
            ))}
          </select>
        </div>

        <div className="max-h-[420px] overflow-y-auto px-7 pb-2">
          {filtered.length === 0 ? (
            <div className="px-4 py-12 text-center text-[13px] text-muted-foreground">
              {t('modeling.objectTypes.add_attributes.empty', {
                defaultValue: 'Brak atrybutów dla podanych kryteriów',
              })}
            </div>
          ) : (
            <div className="space-y-1">
              {filtered.map((a) => {
                const isExisting = existingIds.has(a.id);
                const isPicked = picked.has(a.id);
                const labelStr = resolveLabel(a.label, locale);
                return (
                  <label
                    key={a.id}
                    className={cn(
                      'grid cursor-pointer grid-cols-[24px_1fr_120px] items-center gap-3 rounded-xl border px-3 py-2.5 transition',
                      isExisting
                        ? 'cursor-not-allowed border-zinc-100 bg-zinc-50 opacity-50'
                        : isPicked
                          ? 'border-zinc-900 bg-zinc-900 text-white'
                          : 'border-zinc-100 bg-white hover:border-zinc-300 hover:bg-zinc-50',
                    )}
                  >
                    <input
                      type="checkbox"
                      checked={isExisting || isPicked}
                      disabled={isExisting}
                      onChange={() => !isExisting && toggle(a.id)}
                      className="size-4 rounded"
                    />
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="truncate font-mono text-[13px] font-medium">{a.code}</span>
                        {a.system ? <BuiltInLockBadge /> : null}
                        {isExisting ? (
                          <span className="rounded bg-zinc-200 px-1.5 py-0.5 text-[9.5px] font-semibold uppercase tracking-wider text-zinc-700">
                            {t('modeling.objectTypes.add_attributes.attached_badge', {
                              defaultValue: 'dołączony',
                            })}
                          </span>
                        ) : null}
                      </div>
                      <div
                        className={cn(
                          'truncate text-[11.5px]',
                          isPicked ? 'text-white/70' : 'text-muted-foreground',
                        )}
                      >
                        {labelStr}
                      </div>
                    </div>
                    <span
                      className={cn(
                        'rounded-md px-2 py-0.5 text-[11px] font-medium uppercase',
                        isPicked ? 'bg-white/20 text-white' : 'bg-muted text-muted-foreground',
                      )}
                    >
                      {a.type}
                    </span>
                  </label>
                );
              })}
            </div>
          )}
        </div>

        <div className="flex items-center justify-between border-t border-zinc-100 bg-zinc-50/60 px-7 py-4">
          <div className="text-[12.5px] text-muted-foreground">
            {error !== null ? (
              <span className="text-destructive">{error}</span>
            ) : (
              <>
                {t('modeling.objectTypes.add_attributes.picked_prefix', {
                  defaultValue: 'Wybrano',
                })}{' '}
                <span className="font-semibold text-foreground tabular-nums">{picked.size}</span>{' '}
                {picked.size === 1 ? 'atrybut' : 'atrybutów'}
              </>
            )}
          </div>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9 rounded-xl"
              onClick={() => onOpenChange(false)}
              disabled={submitting}
            >
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button
              type="button"
              size="sm"
              disabled={picked.size === 0 || submitting}
              onClick={() => {
                void submit();
              }}
              className="h-9 rounded-xl bg-zinc-900 hover:bg-zinc-800"
            >
              <Check className="size-4" />
              {t('modeling.objectTypes.add_attributes.attach_action', {
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
