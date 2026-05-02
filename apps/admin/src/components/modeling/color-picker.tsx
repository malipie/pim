import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

const DEFAULT_COLORS = [
  '#6366f1',
  '#22c55e',
  '#f59e0b',
  '#ef4444',
  '#3b82f6',
  '#a855f7',
  '#14b8a6',
] as const;

/**
 * VIEW-03 (#375) — 8-swatch palette from `NewAttributeGroupView`
 * (`groups-categories.jsx:488`). Used by the AttributeGroup create
 * form `Wygląd` section.
 */
export const ATTRIBUTE_GROUP_SWATCHES = [
  '#71717a',
  '#3b82f6',
  '#8b5cf6',
  '#10b981',
  '#f59e0b',
  '#ef4444',
  '#06b6d4',
  '#ec4899',
] as const;

/**
 * VIEW-02 (#374) — 10-swatch palette from `attribute-values.jsx:4–7`.
 * Used by the AttributeOption Allowed Values editor (color dot
 * displayed next to every option label).
 */
export const ATTRIBUTE_OPTION_SWATCHES = [
  '#71717a',
  '#3b82f6',
  '#10b981',
  '#f59e0b',
  '#ef4444',
  '#a855f7',
  '#ec4899',
  '#14b8a6',
  '#f97316',
  '#06b6d4',
] as const;

interface ColorPickerProps {
  selected: string;
  onSelect: (hex: string) => void;
  options?: readonly string[];
}

/**
 * VIEW-01 (#372) — color swatch picker matching `NewObjectTypeView`
 * lines 390–394. Selected swatch gets a 2px dark border; the swatch
 * itself shows the actual hex color.
 */
export function ColorPicker({ selected, onSelect, options = DEFAULT_COLORS }: ColorPickerProps) {
  const { t } = useTranslation();

  return (
    <div
      className="flex flex-wrap items-center gap-2"
      role="radiogroup"
      aria-label={t('object_type_wizard.color_picker_aria', { defaultValue: 'Wybór koloru' })}
    >
      {options.map((hex) => {
        const isSelected = hex === selected;
        return (
          // biome-ignore lint/a11y/useSemanticElements: color swatch button is visual; radiogroup semantics applied via role+aria-checked.
          <button
            key={hex}
            type="button"
            role="radio"
            aria-checked={isSelected}
            aria-label={hex}
            onClick={() => onSelect(hex)}
            className={cn(
              'h-8 w-8 rounded-xl border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              isSelected ? 'border-zinc-900' : 'border-transparent',
            )}
            style={{ background: hex }}
          />
        );
      })}
    </div>
  );
}

export const DEFAULT_WIZARD_COLORS = DEFAULT_COLORS;
