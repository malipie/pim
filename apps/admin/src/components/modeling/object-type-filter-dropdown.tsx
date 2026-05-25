import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * ADR-014 / MOD-11 (#903) — target ObjectType selector for the category
 * Detail panel.
 *
 * Renders the list of ObjectTypes that are eligible to declare attribute
 * groups via `CategoryAttributeGroup` rows. Eligibility = `is_built_in
 * = true` AND `is_categorizable = true`. Built-in is required because
 * the backend `CategoryEffectiveGroupsController` resolves the target
 * via `findBuiltInByKind($kind, $tenant)` (one OT per kind); custom OT
 * support waits on a backend ticket that switches the API contract to
 * `objectTypeId` (UUID) instead of `objectTypeKind` (enum).
 *
 * UX collapse rules:
 *   - 0 eligible OTs → renders a disabled hint ("no categorizable OT").
 *   - 1 eligible OT  → auto-selects it and renders a read-only label
 *     (single-choice dropdown adds visual noise without UX value).
 *   - 2+ eligible OTs → renders a native `<select>` like before.
 *
 * Persistence stays on the parent via the URL search-param contract
 * (`?targetType=<kind>`). When the URL points at an OT that's been
 * deleted or flipped to `is_categorizable=false`, the parent should
 * accept the auto-selected fallback emitted via `onChange` on mount.
 */
interface ObjectTypeOption {
  id: string;
  code: string;
  kind: string;
  label?: Record<string, string> | string | null;
  builtIn?: boolean;
  isCategorizable?: boolean;
}

interface Props {
  value: string | null;
  onChange: (next: string) => void;
  className?: string;
}

function optionLabel(opt: ObjectTypeOption, locale: string): string {
  if (opt.label && typeof opt.label === 'object') {
    return opt.label[locale] ?? opt.label.pl ?? opt.label.en ?? opt.code;
  }
  if (typeof opt.label === 'string' && opt.label !== '') {
    return opt.label;
  }
  // Fallback: capitalised code.
  return opt.code.charAt(0).toUpperCase() + opt.code.slice(1);
}

export function ObjectTypeFilterDropdown({ value, onChange, className }: Props) {
  const { t, i18n } = useTranslation();
  const locale = i18n.language === 'pl' ? 'pl' : 'en';

  const query = useQuery<ObjectTypeOption[]>({
    queryKey: ['modeling', 'categorizable-object-types'],
    queryFn: async () => {
      const data = await jsonFetch<ObjectTypeOption[]>('/api/object_types', {
        accept: 'application/json',
      });
      return data.filter((ot) => ot.builtIn === true && ot.isCategorizable === true);
    },
    staleTime: 60_000,
  });

  const options = query.data ?? [];

  // Auto-select the first option when the URL param doesn't match anything
  // in the fetched list (initial render OR after the operator demoted the
  // previously-selected OT via toggle).
  const firstOption = options[0];
  const valueMatches = options.some((ot) => ot.kind === value);
  if (!query.isLoading && firstOption !== undefined && !valueMatches) {
    onChange(firstOption.kind);
  }

  if (query.isLoading) {
    return (
      <span
        className={cn(
          'inline-flex h-9 items-center gap-2 rounded-xl border border-line bg-white px-3 text-[12.5px] soft-shadow',
          className,
        )}
      >
        <span className="text-zinc-500">{t('app.loading')}</span>
      </span>
    );
  }

  if (options.length === 0) {
    return (
      <span
        className={cn(
          'inline-flex h-9 items-center gap-2 rounded-xl border border-dashed border-line bg-white px-3 text-[12.5px] text-muted-foreground soft-shadow',
          className,
        )}
      >
        {t('categories.target_type_empty', {
          defaultValue: 'Brak ObjectType kategoryzowalnych',
        })}
      </span>
    );
  }

  if (options.length === 1 && firstOption !== undefined) {
    const only = firstOption;
    return (
      <span
        className={cn(
          'inline-flex h-9 items-center gap-2 rounded-xl border border-line bg-white px-3 text-[12.5px] soft-shadow',
          className,
        )}
      >
        <span className="text-zinc-500">
          {t('categories.target_type_label', { defaultValue: 'Object Type:' })}
        </span>
        <span className="font-medium">{optionLabel(only, locale)}</span>
      </span>
    );
  }

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
        value={value ?? firstOption?.kind ?? ''}
        onChange={(e) => onChange(e.target.value)}
        aria-label={t('categories.target_type_aria', {
          defaultValue: 'Filter declared groups by target ObjectType',
        })}
      >
        {options.map((opt) => (
          <option key={opt.id} value={opt.kind}>
            {optionLabel(opt, locale)}
          </option>
        ))}
      </select>
    </label>
  );
}
