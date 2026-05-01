import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export type FilterValue = string | string[] | { gte?: number; lte?: number };

export interface FilterChip {
  key: string;
  label: string;
  value: FilterValue;
}

/**
 * UI-02.9 (#299) — chips visualisation of active filters with X to
 * remove. Per `Project Plan/UI/epik-02-produkty.md` §4.1 punkt 5.
 */
export function ProductFilterChips({
  chips,
  onRemove,
}: {
  chips: FilterChip[];
  onRemove: (key: string) => void;
}) {
  const { t } = useTranslation();
  if (chips.length === 0) return null;

  return (
    <div className="flex flex-wrap items-center gap-2">
      {chips.map((chip) => (
        <span
          key={chip.key}
          className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
        >
          <span>{chip.label}</span>
          <button
            type="button"
            onClick={() => onRemove(chip.key)}
            className="rounded-full p-0.5 transition-colors hover:bg-secondary-foreground/10"
            aria-label={t('products.filters.remove_chip', {
              defaultValue: `Remove ${chip.label}`,
              label: chip.label,
            })}
          >
            <X className="size-3" />
          </button>
        </span>
      ))}
    </div>
  );
}

export function formatChipLabel(key: string, value: FilterValue, label?: string): string {
  const prefix = label ?? key;
  if (Array.isArray(value)) return `${prefix}: ${value.join(', ')}`;
  if (typeof value === 'object') {
    const parts: string[] = [];
    if (value.gte !== undefined) parts.push(`≥${value.gte}`);
    if (value.lte !== undefined) parts.push(`≤${value.lte}`);
    return `${prefix}: ${parts.join(' ')}`;
  }
  return `${prefix}: ${value}`;
}
