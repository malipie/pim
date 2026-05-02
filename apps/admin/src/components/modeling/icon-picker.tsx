import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

const DEFAULT_ICONS = ['📦', '🎫', '📍', '📅', '🔄', '💎', '🛠️', '🚚'] as const;

interface IconPickerProps {
  selected: string;
  onSelect: (icon: string) => void;
  options?: readonly string[];
}

/**
 * VIEW-01 (#372) — emoji-tile picker for the new ObjectType wizard
 * (pixel-perfect with `NewObjectTypeView` lines 380–384). Selected
 * tile takes the dark fill, the rest stay light.
 */
export function IconPicker({ selected, onSelect, options = DEFAULT_ICONS }: IconPickerProps) {
  const { t } = useTranslation();

  return (
    <div
      className="flex flex-wrap items-center gap-2"
      role="radiogroup"
      aria-label={t('object_type_wizard.icon_picker_aria', { defaultValue: 'Wybór ikony' })}
    >
      {options.map((icon) => {
        const isSelected = icon === selected;
        return (
          // biome-ignore lint/a11y/useSemanticElements: emoji tile button looks nothing like an <input type="radio"> — radiogroup semantics applied via role+aria-checked.
          <button
            key={icon}
            type="button"
            role="radio"
            aria-checked={isSelected}
            aria-label={icon}
            onClick={() => onSelect(icon)}
            className={cn(
              'grid h-10 w-10 place-items-center rounded-xl text-[18px] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              isSelected
                ? 'bg-zinc-900 text-white'
                : 'border border-zinc-200 bg-white hover:bg-zinc-50',
            )}
          >
            {icon}
          </button>
        );
      })}
    </div>
  );
}

export const DEFAULT_WIZARD_ICONS = DEFAULT_ICONS;

/**
 * VIEW-03 (#375) — 14-icon palette from `NewAttributeGroupView`
 * (`groups-categories.jsx:489`). Used by the AttributeGroup create
 * form `Wygląd` section.
 */
export const ATTRIBUTE_GROUP_ICONS = [
  '📦',
  '📐',
  '🔧',
  '⚙️',
  '🛡️',
  '💧',
  '🌡️',
  '🏗️',
  '📋',
  '🎨',
  '🔌',
  '📡',
  '🪛',
  '🧰',
] as const;
