import { Search, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface CatalogSearchBoxProps {
  value: string;
  onChange: (value: string) => void;
  isLoading?: boolean;
  placeholder?: string;
}

/**
 * Reusable search input for catalog list pages (#53 / 0.5.5).
 *
 * Wraps a shadcn `Input` with a leading search icon and a clear button
 * that surfaces only when the user has typed. The actual search call
 * lives in {@see useCatalogSearch} so the box stays UX-only — it
 * pushes the value back to the parent which feeds the hook.
 *
 * The component is intentionally not a "Cmd+K command bar" — that
 * pattern is reserved for the agent layer in epic 0.7. Keeping the
 * search box plain matches Refine's standard list-filter pattern.
 */
export function CatalogSearchBox({
  value,
  onChange,
  isLoading,
  placeholder,
}: CatalogSearchBoxProps) {
  const { t } = useTranslation();

  return (
    <div className="relative w-full max-w-md">
      <Search
        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
        aria-hidden="true"
      />
      <Input
        type="search"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder ?? t('search.placeholder', { defaultValue: 'Search…' })}
        aria-label={t('search.aria_label', { defaultValue: 'Catalog search' })}
        className="pl-9 pr-9"
      />
      {value !== '' ? (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="absolute right-1 top-1/2 size-7 -translate-y-1/2 p-0"
          onClick={() => onChange('')}
          aria-label={t('search.clear', { defaultValue: 'Clear search' })}
        >
          <X className="size-4" />
        </Button>
      ) : null}
      {isLoading ? (
        <span
          className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"
          aria-live="polite"
        >
          {t('search.loading', { defaultValue: 'Searching…' })}
        </span>
      ) : null}
    </div>
  );
}
