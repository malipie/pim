import { useList } from '@refinedev/core';
import { ChevronsUpDown, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { resolveLabel } from '@/features/catalog/attributes/list';

interface CategoryRow {
  id: string;
  code: string;
  attributes_indexed?: { name?: Record<string, string> } | null;
  parentId?: string | null;
}

interface CategoryRootComboboxProps {
  value: string | null;
  onChange: (id: string | null) => void;
  ariaLabelledBy?: string;
}

/**
 * Dropdown selector for the Channel `categoryTreeRootId`. Lists all
 * `kind=category` rows (the backend `ChannelCategoryRootValidator`
 * enforces the kind invariant on save). Operators usually have a
 * handful of root candidates, so a flat list with search is enough —
 * a true tree picker is a follow-up if seedów rośnie.
 */
export function CategoryRootCombobox({
  value,
  onChange,
  ariaLabelledBy,
}: CategoryRootComboboxProps) {
  const { t, i18n } = useTranslation();
  const { result, query } = useList<CategoryRow>({
    resource: 'categories',
    pagination: { mode: 'off' },
  });

  const categories = result.data;
  const isLoading = query.isLoading;

  const selected = categories.find((row) => row.id === value);

  return (
    <fieldset className="space-y-2" aria-labelledby={ariaLabelledBy}>
      {selected ? (
        <div className="inline-flex items-center gap-2 rounded border bg-card px-3 py-2">
          <span className="font-mono text-xs">{selected.code}</span>
          <span className="text-sm text-muted-foreground">
            {resolveLabel(selected.attributes_indexed?.name, i18n.language) ?? selected.code}
          </span>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => onChange(null)}
            aria-label={t('channels.form.clear_category_root', { defaultValue: 'Wyczyść' })}
          >
            <X className="size-3.5" />
          </Button>
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">
          {t('channels.form.category_root_empty', {
            defaultValue: 'Nie wybrano korzenia kategorii (opcjonalne).',
          })}
        </p>
      )}

      <div className="rounded border bg-card p-2">
        {isLoading ? (
          <p className="px-2 py-1 text-xs text-muted-foreground">{t('app.loading')}</p>
        ) : categories.length === 0 ? (
          <p className="px-2 py-1 text-xs text-muted-foreground">
            {t('channels.form.category_root_none', {
              defaultValue: 'Brak dostępnych kategorii.',
            })}
          </p>
        ) : (
          <div className="flex max-h-40 flex-col gap-0.5 overflow-y-auto">
            {categories.map((row) => (
              <Button
                key={row.id}
                type="button"
                variant={row.id === value ? 'secondary' : 'ghost'}
                size="sm"
                onClick={() => onChange(row.id)}
                className="justify-start font-mono text-xs"
              >
                <ChevronsUpDown className="size-3.5 opacity-50" />
                <span>{row.code}</span>
                <span className="ml-2 text-[11px] text-muted-foreground">
                  {resolveLabel(row.attributes_indexed?.name, i18n.language) ?? ''}
                </span>
              </Button>
            ))}
          </div>
        )}
      </div>
    </fieldset>
  );
}
