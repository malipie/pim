import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import type { FilterValue } from './types';

interface SessionsFilterPillsProps {
  value: FilterValue;
  onChange: (next: FilterValue) => void;
}

const FILTERS: ReadonlyArray<FilterValue> = ['all', 'success', 'warning', 'error', 'cancelled'];

export function SessionsFilterPills({ value, onChange }: SessionsFilterPillsProps) {
  const { t } = useTranslation();

  return (
    <fieldset className="flex items-center gap-0.5 bg-white soft-shadow rounded-xl p-1 h-9 border-0">
      <legend className="sr-only">{t('imports.sessions.filter.aria_label')}</legend>
      {FILTERS.map((id) => {
        const active = value === id;
        return (
          <button
            key={id}
            type="button"
            aria-pressed={active}
            onClick={() => onChange(id)}
            className={cn(
              'h-7 px-2.5 text-[11.5px] font-medium rounded-lg transition',
              active ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100',
            )}
          >
            {t(`imports.sessions.filter.${id}`)}
          </button>
        );
      })}
    </fieldset>
  );
}
