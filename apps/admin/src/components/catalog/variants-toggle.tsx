import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

export type VariantsMode = 'tree' | 'flat';

/**
 * VIEW-05 (#411) — segmented control matching the prototype mockup
 * `produkty/list-view.jsx` lines 104–109. Two pills inside a soft-shadow
 * card switch the products list between flat and tree rendering. UI-02
 * persistence semantics (`config.variants_mode`) stay unchanged — this
 * is purely a visual refactor from the previous radio-fieldset.
 */
export function VariantsToggle({
  mode,
  onChange,
}: {
  mode: VariantsMode;
  onChange: (next: VariantsMode) => void;
}) {
  const { t } = useTranslation();
  const options: ReadonlyArray<{ value: VariantsMode; label: string }> = [
    { value: 'flat', label: t('products.variants.flat', { defaultValue: 'Płasko' }) },
    { value: 'tree', label: t('products.variants.tree', { defaultValue: 'Drzewo' }) },
  ];

  return (
    <div className="h-11 rounded-2xl bg-white shadow-sm inline-flex items-center p-1">
      {options.map((opt) => {
        const active = mode === opt.value;
        return (
          <button
            key={opt.value}
            type="button"
            aria-pressed={active}
            onClick={() => {
              onChange(opt.value);
            }}
            className={cn(
              'h-9 px-3 rounded-xl text-[12.5px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900',
              active ? 'bg-zinc-900 text-white' : 'text-zinc-500 hover:text-zinc-700',
            )}
          >
            {opt.label}
          </button>
        );
      })}
    </div>
  );
}
