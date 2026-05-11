import { Grid3x3, Table2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

export type ProductsViewMode = 'grid' | 'excel';

/**
 * Restored after VIEW-05 (#412) dropped the table/excel toggle for
 * mockup-perfection — operators kept reaching for batch inline-cell
 * edit (UI-02.12 ExcelLikeGrid). Same segmented-control shape as
 * {@link VariantsToggle} so the toolbar stays visually consistent.
 *
 * Persisted via localStorage in the parent (`pim.products.viewMode`)
 * so the choice survives navigation. Default is `grid` — the
 * pixel-perfect display the mockup ships.
 */
export function ViewModeToggle({
  mode,
  onChange,
}: {
  mode: ProductsViewMode;
  onChange: (next: ProductsViewMode) => void;
}) {
  const { t } = useTranslation();
  const options: ReadonlyArray<{
    value: ProductsViewMode;
    label: string;
    Icon: typeof Grid3x3;
  }> = [
    {
      value: 'grid',
      label: t('products.view_mode.grid', { defaultValue: 'Karty' }),
      Icon: Grid3x3,
    },
    {
      value: 'excel',
      label: t('products.view_mode.excel', { defaultValue: 'Excel' }),
      Icon: Table2,
    },
  ];

  return (
    <div
      className="inline-flex h-11 items-center rounded-2xl bg-white p-1 shadow-sm"
      role="tablist"
      aria-label={t('products.view_mode.aria', { defaultValue: 'Tryb widoku' })}
    >
      {options.map(({ value, label, Icon }) => {
        const active = mode === value;
        return (
          <button
            key={value}
            type="button"
            role="tab"
            aria-selected={active}
            onClick={() => {
              onChange(value);
            }}
            className={cn(
              'inline-flex h-9 items-center gap-1.5 rounded-xl px-3 text-[12.5px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900',
              active ? 'bg-zinc-900 text-white' : 'text-zinc-500 hover:text-zinc-700',
            )}
          >
            <Icon className="size-3.5" />
            {label}
          </button>
        );
      })}
    </div>
  );
}
