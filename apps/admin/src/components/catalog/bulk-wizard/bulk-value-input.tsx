import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Input } from '@/components/ui/input';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * VIEW-25b (#556) — value input adapter dla BulkWizard.
 *
 * Renderuje odpowiedni picker zależnie od typu atrybutu:
 *  - `text` → standardowy <Input>
 *  - `number` / `metric` → <Input type=number>
 *  - `date` → <Input type=date>
 *  - `boolean` → toggle Tak/Nie
 *  - `select` → dropdown z `/api/attributes/{code}/options`
 *  - `multiselect` → chips picker z tej samej listy options
 *  - typ undefined → fallback do `text`
 *
 * Wartość zwracana w `onChange` jest typowana per atrybut:
 *  - `text` → string
 *  - `number` / `metric` → number | ''
 *  - `boolean` → boolean
 *  - `select` → string (kod opcji)
 *  - `multiselect` → string[]
 */

export interface BulkValueInputProps {
  attrCode: string;
  attrType: string | undefined;
  value: unknown;
  onChange: (next: unknown) => void;
  /** Optional placeholder dla text/number wariantu. */
  placeholder?: string;
}

interface AttributeOptionRow {
  id?: string;
  code: string;
  label?: Record<string, string> | string | null;
}

interface OptionsListResponse {
  'hydra:member'?: AttributeOptionRow[];
  member?: AttributeOptionRow[];
}

function labelOf(row: AttributeOptionRow): string {
  const label = row.label;
  if (label === null || label === undefined) return row.code;
  if (typeof label === 'string') return label;
  return label.pl ?? label.en ?? Object.values(label)[0] ?? row.code;
}

export function BulkValueInput({
  attrCode,
  attrType,
  value,
  onChange,
  placeholder,
}: BulkValueInputProps) {
  const { t } = useTranslation();
  const [options, setOptions] = useState<AttributeOptionRow[]>([]);
  const [optionsLoading, setOptionsLoading] = useState(false);

  const isSelect = attrType === 'select' || attrType === 'multiselect';

  useEffect(() => {
    if (!isSelect || attrCode === '') {
      setOptions([]);
      return;
    }
    let cancelled = false;
    setOptionsLoading(true);
    const load = async (): Promise<void> => {
      try {
        const response = await jsonFetch<OptionsListResponse>(
          `/api/attributes/${encodeURIComponent(attrCode)}/options`,
        );
        const rows = response['hydra:member'] ?? response.member ?? [];
        if (!cancelled) setOptions(rows);
      } catch {
        if (!cancelled) setOptions([]);
      } finally {
        if (!cancelled) setOptionsLoading(false);
      }
    };
    void load();
    return () => {
      cancelled = true;
    };
  }, [attrCode, isSelect]);

  if (attrType === 'boolean') {
    const boolValue = value === true || value === 'true';
    return (
      <div className="inline-flex items-center gap-2">
        <button
          type="button"
          onClick={() => onChange(true)}
          className={cn(
            'h-9 px-3 rounded-lg text-[12px] font-medium border',
            boolValue
              ? 'bg-zinc-900 text-white border-zinc-900'
              : 'bg-white text-zinc-700 border-zinc-200 hover:border-zinc-300',
          )}
        >
          {t('bulk_value.true', { defaultValue: 'Tak' })}
        </button>
        <button
          type="button"
          onClick={() => onChange(false)}
          className={cn(
            'h-9 px-3 rounded-lg text-[12px] font-medium border',
            !boolValue
              ? 'bg-zinc-900 text-white border-zinc-900'
              : 'bg-white text-zinc-700 border-zinc-200 hover:border-zinc-300',
          )}
        >
          {t('bulk_value.false', { defaultValue: 'Nie' })}
        </button>
      </div>
    );
  }

  if (attrType === 'date') {
    return (
      <Input
        type="date"
        value={typeof value === 'string' ? value : ''}
        onChange={(e) => onChange(e.target.value)}
        className="font-mono"
      />
    );
  }

  if (attrType === 'number' || attrType === 'metric') {
    return (
      <Input
        type="number"
        inputMode="decimal"
        value={typeof value === 'string' || typeof value === 'number' ? String(value) : ''}
        onChange={(e) => {
          const raw = e.target.value;
          onChange(raw === '' ? '' : Number(raw));
        }}
        placeholder={placeholder ?? 'np. 49.99'}
        className="font-mono"
      />
    );
  }

  if (attrType === 'select') {
    return (
      <select
        value={typeof value === 'string' ? value : ''}
        onChange={(e) => onChange(e.target.value)}
        disabled={optionsLoading}
        className="h-9 w-full rounded-lg border border-zinc-200 px-2 text-[13px] focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900"
      >
        <option value="">
          {optionsLoading
            ? t('bulk_value.loading', { defaultValue: 'Ładuję…' })
            : t('bulk_value.select_placeholder', { defaultValue: '— wybierz —' })}
        </option>
        {options.map((row) => (
          <option key={row.code} value={row.code}>
            {labelOf(row)} ({row.code})
          </option>
        ))}
      </select>
    );
  }

  if (attrType === 'multiselect') {
    const currentList = Array.isArray(value)
      ? (value as string[])
      : typeof value === 'string' && value.trim() !== ''
        ? value.split(',').map((s) => s.trim())
        : [];
    const toggle = (code: string): void => {
      const next = currentList.includes(code)
        ? currentList.filter((c) => c !== code)
        : [...currentList, code];
      onChange(next);
    };
    return (
      <div className="rounded-lg border border-zinc-200 bg-white max-h-[180px] overflow-y-auto">
        {optionsLoading ? (
          <div className="px-3 py-2 text-[12px] text-zinc-400">
            {t('bulk_value.loading', { defaultValue: 'Ładuję…' })}
          </div>
        ) : options.length === 0 ? (
          <div className="px-3 py-2 text-[12px] text-zinc-400">
            {t('bulk_value.no_options', { defaultValue: 'Brak opcji dla tego atrybutu' })}
          </div>
        ) : (
          options.map((row) => {
            const checked = currentList.includes(row.code);
            return (
              <label
                key={row.code}
                className={cn(
                  'flex items-center gap-2 px-3 py-1.5 text-[12.5px] cursor-pointer',
                  checked ? 'bg-emerald-50/60' : 'hover:bg-zinc-50',
                )}
              >
                <input
                  type="checkbox"
                  checked={checked}
                  onChange={() => toggle(row.code)}
                  className="size-3.5"
                />
                <span className="font-mono text-[11px] text-zinc-500 min-w-[100px] truncate">
                  {row.code}
                </span>
                <span className="flex-1 truncate">{labelOf(row)}</span>
              </label>
            );
          })
        )}
      </div>
    );
  }

  return (
    <Input
      value={typeof value === 'string' ? value : ''}
      onChange={(e) => onChange(e.target.value)}
      placeholder={placeholder ?? 'np. Festo'}
    />
  );
}
