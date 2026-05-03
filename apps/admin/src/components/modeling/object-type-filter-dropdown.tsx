import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

const BUILT_IN_KINDS = ['service', 'product', 'category', 'asset', 'brand'] as const;

export type BuiltInObjectKind = (typeof BUILT_IN_KINDS)[number];

interface Props {
  value: BuiltInObjectKind;
  onChange: (next: BuiltInObjectKind) => void;
  className?: string;
}

/**
 * VIEW-04 (#408) — target ObjectType selector for the category Detail
 * panel. Persists the chosen kind via the parent (URL search param in
 * the list page) so deep-links into a particular `kind=service` view
 * survive a refresh.
 *
 * MVP scope: built-in kinds only. Custom kinds (per ADR-009 phase 2)
 * stay hidden behind {@link CustomObjectTypeApiGuard} — the dropdown
 * surfaces them automatically once the feature flag flips.
 */
export function ObjectTypeFilterDropdown({ value, onChange, className }: Props) {
  const { t } = useTranslation();

  return (
    <label
      className={cn(
        'inline-flex h-9 items-center gap-2 rounded-xl border border-line bg-white px-3 text-[12.5px] soft-shadow',
        className,
      )}
    >
      <span className="text-zinc-500">
        {t('categories.target_type_label', { defaultValue: 'Object Type:' })}
      </span>
      <select
        className="bg-transparent font-medium outline-none"
        value={value}
        onChange={(e) => onChange(e.target.value as BuiltInObjectKind)}
        aria-label={t('categories.target_type_aria', {
          defaultValue: 'Filter declared groups by target ObjectType',
        })}
      >
        {BUILT_IN_KINDS.map((kind) => (
          <option key={kind} value={kind}>
            {kind.charAt(0).toUpperCase() + kind.slice(1)}
          </option>
        ))}
      </select>
    </label>
  );
}
