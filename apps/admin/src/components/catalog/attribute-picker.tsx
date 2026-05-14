import { ChevronDown, Search, Star, StarOff } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Input } from '@/components/ui/input';
import { jsonFetch } from '@/lib/http';
import { useFilterFavorites } from '@/lib/users/use-filter-favorites';
import { cn } from '@/lib/utils';

/**
 * VIEW-27 + VIEW-22c + VIEW-25a (#558, #553, #556) — reusable
 * attribute picker.
 *
 * Two visual groups:
 *  - **Ulubione** (top) — `useFilterFavorites` (user-scoped, max 10).
 *    Star pin toggles add/remove (optimistic).
 *  - **Wszystkie atrybuty** (bottom) — full tenant list (`/api/attributes?itemsPerPage=200`).
 *
 * Mini search bar filters both groups client-side by `code` + `label`.
 * Consumed by AdvancedFilterPanel (VIEW-22c) and BulkWizard Step 1
 * (VIEW-25a) — same UX everywhere the operator picks an attribute.
 */

interface AttributeRow {
  id: string;
  code: string;
  label: Record<string, string> | string | null;
  type?: string;
}

interface AttributeListResponse {
  'hydra:member'?: AttributeRow[];
  member?: AttributeRow[];
}

export interface AttributePickerProps {
  value: string | null;
  onChange: (next: { id: string; code: string; type?: string } | null) => void;
  /**
   * Optional limit on attribute types; useful when the consumer only
   * supports certain types (e.g. BulkIncrementNumeric wants
   * `number` / `metric` only).
   */
  allowedTypes?: ReadonlyArray<string>;
  placeholder?: string;
  className?: string;
}

function attrLabel(row: AttributeRow, locale = 'pl'): string {
  if (row.label === null || row.label === undefined) return row.code;
  if (typeof row.label === 'string') return row.label;
  return row.label[locale] ?? row.label.en ?? row.code;
}

export function AttributePicker({
  value,
  onChange,
  allowedTypes,
  placeholder,
  className,
}: AttributePickerProps) {
  const { t, i18n } = useTranslation();
  const locale = i18n.language || 'pl';

  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [attributes, setAttributes] = useState<AttributeRow[]>([]);
  const containerRef = useRef<HTMLDivElement>(null);

  const { favorites, toggle } = useFilterFavorites();

  useEffect(() => {
    let cancelled = false;
    const load = async (): Promise<void> => {
      try {
        const response = await jsonFetch<AttributeListResponse>('/api/attributes?itemsPerPage=200');
        const rows = response['hydra:member'] ?? response.member ?? [];
        if (!cancelled) setAttributes(rows);
      } catch {
        if (!cancelled) setAttributes([]);
      }
    };
    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  // Close on outside click
  useEffect(() => {
    if (!open) return;
    const handler = (event: MouseEvent): void => {
      if (!containerRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    window.addEventListener('mousedown', handler);
    return () => window.removeEventListener('mousedown', handler);
  }, [open]);

  const allowedRows = useMemo(() => {
    if (!allowedTypes || allowedTypes.length === 0) return attributes;
    return attributes.filter((row) => !row.type || allowedTypes.includes(row.type));
  }, [attributes, allowedTypes]);

  const filteredRows = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (needle === '') return allowedRows;
    return allowedRows.filter((row) => {
      const label = attrLabel(row, locale).toLowerCase();
      return row.code.toLowerCase().includes(needle) || label.includes(needle);
    });
  }, [allowedRows, query, locale]);

  const favoriteIds = useMemo(() => new Set(favorites.map((f) => f.attribute_id)), [favorites]);
  const favoriteRows = useMemo(
    () =>
      favorites
        .map((f) => allowedRows.find((row) => row.id === f.attribute_id))
        .filter((row): row is AttributeRow => row !== undefined)
        .filter((row) => {
          const needle = query.trim().toLowerCase();
          if (needle === '') return true;
          const label = attrLabel(row, locale).toLowerCase();
          return row.code.toLowerCase().includes(needle) || label.includes(needle);
        }),
    [favorites, allowedRows, query, locale],
  );

  const currentRow = useMemo(
    () => attributes.find((row) => row.id === value || row.code === value),
    [attributes, value],
  );
  const triggerLabel = currentRow
    ? `${currentRow.code} · ${attrLabel(currentRow, locale)}`
    : (placeholder ?? t('attribute_picker.placeholder', { defaultValue: 'Wybierz atrybut…' }));

  return (
    <div ref={containerRef} className={cn('relative', className)}>
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        aria-haspopup="listbox"
        aria-expanded={open}
        className="h-9 w-full inline-flex items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-[12.5px] hover:border-zinc-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900"
      >
        <span className="truncate text-left">{triggerLabel}</span>
        <ChevronDown className="size-4 shrink-0 text-zinc-400" aria-hidden="true" />
      </button>
      {open ? (
        <div className="absolute z-30 mt-1 w-[320px] rounded-xl border border-zinc-200 bg-white shadow-xl">
          <div className="p-2 border-b border-zinc-100">
            <div className="relative">
              <Search
                className="absolute left-2 top-1/2 -translate-y-1/2 size-3.5 text-zinc-400"
                aria-hidden="true"
              />
              <Input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder={t('attribute_picker.search_placeholder', {
                  defaultValue: 'Szukaj atrybutu…',
                })}
                className="h-8 pl-7 text-[12.5px]"
                autoFocus
              />
            </div>
          </div>

          <div className="max-h-[320px] overflow-y-auto py-1">
            {favoriteRows.length > 0 ? (
              <>
                <div className="px-3 py-1 text-[10.5px] uppercase tracking-wider font-semibold text-zinc-400">
                  {t('attribute_picker.favorites', { defaultValue: 'Ulubione' })}
                </div>
                {favoriteRows.map((row) => (
                  <PickerRow
                    key={`fav-${row.id}`}
                    row={row}
                    locale={locale}
                    isFavorite
                    isActive={value === row.id || value === row.code}
                    onPick={() => {
                      onChange({ id: row.id, code: row.code, type: row.type });
                      setOpen(false);
                    }}
                    onToggleFavorite={() => {
                      void toggle(row.id);
                    }}
                  />
                ))}
                <div className="my-1 h-px bg-zinc-100" />
              </>
            ) : null}

            <div className="px-3 py-1 text-[10.5px] uppercase tracking-wider font-semibold text-zinc-400">
              {t('attribute_picker.all', { defaultValue: 'Wszystkie atrybuty' })}
            </div>
            {filteredRows.length === 0 ? (
              <div className="px-3 py-2 text-[12px] text-zinc-400">
                {t('attribute_picker.empty', { defaultValue: 'Brak atrybutów' })}
              </div>
            ) : (
              filteredRows.map((row) => (
                <PickerRow
                  key={row.id}
                  row={row}
                  locale={locale}
                  isFavorite={favoriteIds.has(row.id)}
                  isActive={value === row.id || value === row.code}
                  onPick={() => {
                    onChange({ id: row.id, code: row.code, type: row.type });
                    setOpen(false);
                  }}
                  onToggleFavorite={() => {
                    void toggle(row.id);
                  }}
                />
              ))
            )}
          </div>
        </div>
      ) : null}
    </div>
  );
}

interface PickerRowProps {
  row: AttributeRow;
  locale: string;
  isFavorite: boolean;
  isActive: boolean;
  onPick: () => void;
  onToggleFavorite: () => void;
}

function PickerRow({
  row,
  locale,
  isFavorite,
  isActive,
  onPick,
  onToggleFavorite,
}: PickerRowProps) {
  const StarIcon = isFavorite ? Star : StarOff;
  return (
    <div
      className={cn(
        'flex items-center gap-2 px-3 py-1.5 text-[12.5px]',
        isActive ? 'bg-violet-50/60' : 'hover:bg-zinc-50',
      )}
    >
      <button
        type="button"
        onClick={onToggleFavorite}
        aria-label={isFavorite ? 'Usuń z ulubionych' : 'Dodaj do ulubionych'}
        className={cn(
          'size-5 rounded grid place-items-center transition',
          isFavorite ? 'text-amber-500 hover:text-amber-600' : 'text-zinc-300 hover:text-zinc-500',
        )}
      >
        <StarIcon className="size-3.5" aria-hidden="true" />
      </button>
      <button
        type="button"
        onClick={onPick}
        className="flex-1 flex items-center justify-between gap-2 text-left"
      >
        <span className="truncate">{attrLabel(row, locale)}</span>
        <span className="font-mono text-[10.5px] text-zinc-400 shrink-0">{row.code}</span>
      </button>
    </div>
  );
}
