import { Search } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import { Input } from '@/components/ui/input';

export type AssetMimeGroup = 'all' | 'image' | 'pdf';

export interface AssetFilters {
  search: string;
  mimeGroup: AssetMimeGroup;
}

export interface AssetFilterBarProps {
  filters: AssetFilters;
  onChange: (filters: AssetFilters) => void;
}

export function AssetFilterBar({ filters, onChange }: AssetFilterBarProps) {
  const { t } = useTranslation();
  const searchId = useId();

  return (
    <div className="flex flex-wrap items-center gap-3">
      <div className="relative flex w-64 max-w-full items-center">
        <Search className="absolute left-3 size-4 text-muted-foreground" aria-hidden="true" />
        <label htmlFor={searchId} className="sr-only">
          {t('assets.filters.search_placeholder')}
        </label>
        <Input
          id={searchId}
          type="search"
          placeholder={t('assets.filters.search_placeholder')}
          value={filters.search}
          onChange={(event) => onChange({ ...filters, search: event.target.value })}
          className="pl-9"
        />
      </div>

      <fieldset className="flex items-center gap-1 rounded-md border bg-card p-0.5">
        <legend className="sr-only">{t('assets.filters.mime_label')}</legend>
        {(
          [
            { value: 'all', label: t('assets.filters.mime_all') },
            { value: 'image', label: t('assets.filters.mime_images') },
            { value: 'pdf', label: t('assets.filters.mime_pdf') },
          ] satisfies Array<{ value: AssetMimeGroup; label: string }>
        ).map((option) => (
          <button
            key={option.value}
            type="button"
            onClick={() => onChange({ ...filters, mimeGroup: option.value })}
            aria-pressed={filters.mimeGroup === option.value}
            className={`rounded px-3 py-1 text-xs font-medium transition-colors ${
              filters.mimeGroup === option.value
                ? 'bg-primary text-primary-foreground'
                : 'text-muted-foreground hover:bg-muted'
            }`}
          >
            {option.label}
          </button>
        ))}
      </fieldset>
    </div>
  );
}
