import { useTranslation } from 'react-i18next';

export type VariantsMode = 'tree' | 'flat';

/**
 * UI-02.14 (#304) — radio toggle between tree (default) and flat view
 * for the products list, per `Project Plan/UI/epik-02-produkty.md` §4.4.
 *
 * Persistence per saved view (UI-02.15 `config.variants_mode`) lands
 * with the saved-views integration.
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
    { value: 'tree', label: t('products.variants.mode_tree', { defaultValue: 'As tree' }) },
    { value: 'flat', label: t('products.variants.mode_flat', { defaultValue: 'Flat' }) },
  ];

  return (
    <fieldset className="flex items-center gap-3">
      <legend className="text-xs uppercase tracking-wide text-muted-foreground">
        {t('products.variants.toggle_legend', { defaultValue: 'Variants' })}
      </legend>
      {options.map((opt) => (
        <label key={opt.value} className="inline-flex cursor-pointer items-center gap-1 text-sm">
          <input
            type="radio"
            name="products-variants-mode"
            value={opt.value}
            checked={mode === opt.value}
            onChange={() => onChange(opt.value)}
            className="size-3"
          />
          {opt.label}
        </label>
      ))}
    </fieldset>
  );
}
