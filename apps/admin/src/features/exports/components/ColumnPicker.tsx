import { useTranslation } from 'react-i18next';

export interface ColumnGroup {
  id: string;
  labelKey: string;
  defaultLabel: string;
  columns: ColumnOption[];
}

export interface ColumnOption {
  key: string;
  labelKey: string;
  defaultLabel: string;
}

export interface ColumnPickerProps {
  /**
   * Catalog of selectable columns the user can pick from.
   *
   * In MVP this list is built-in (sku, parent_sku, status,
   * enabled, completeness_pct, created_at, updated_at, category).
   * Attribute-driven columns (description.pl, brand, etc.) land
   * with EXP-11 modal integration once the attribute repository
   * is wired in.
   */
  available: readonly ColumnGroup[];
  /**
   * Ordered list of column keys the user has already selected.
   * Matches the API contract for `selected_columns` (PRD §5.3).
   */
  selected: readonly string[];
  /** Callback fired when the user adds / removes / reorders a column. */
  onChange: (next: string[]) => void;
}

/**
 * EXP-10 (#589) — Two-pane column picker (MVP).
 *
 * Left pane: groups of available columns with checkboxes. Click checks
 * → adds to right pane. Re-check unchecks.
 *
 * Right pane: ordered list of selected columns with X buttons to
 * remove and ↑↓ buttons to reorder. PRD §3.3 punkt 4 specified
 * drag-and-drop reorder via dnd-kit — deferred to a follow-up
 * because the picker contract (props in / out) is identical and
 * dnd-kit can swap in without changing call sites. ARIA-aware
 * reordering buttons cover keyboard-only operators today.
 *
 * Świadome odejścia:
 *  - No search filter (typeahead) in the left pane — list is
 *    small enough in MVP (~10 built-ins + per-tenant attributes
 *    when EXP-11 wires them in). Adding a search input is a
 *    follow-up the same instant attribute count grows past ~30.
 *  - No drag-and-drop — ↑↓ buttons keep keyboard navigation
 *    accessible from day 1; dnd-kit lands when a real
 *    a11y review confirms its compatibility (axe-core gate).
 *  - No locale / channel sub-selectors for scopable attributes
 *    — locale checkboxes live as a separate section in the modal
 *    (EXP-11) so the picker stays focused on "which columns",
 *    not "in how many variants".
 */
export function ColumnPicker({
  available,
  selected,
  onChange,
}: ColumnPickerProps): React.ReactElement {
  const { t } = useTranslation();

  const isSelected = (key: string) => selected.includes(key);

  const toggle = (key: string) => {
    if (isSelected(key)) {
      onChange(selected.filter((k) => k !== key));
      return;
    }
    onChange([...selected, key]);
  };

  const remove = (key: string) => {
    onChange(selected.filter((k) => k !== key));
  };

  const move = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= selected.length) return;
    const next = [...selected];
    const tmp = next[target] as string;
    next[target] = next[index] as string;
    next[index] = tmp;
    onChange(next);
  };

  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      <section
        className="rounded-md border bg-card"
        aria-label={t('exports.column_picker.available_aria', { defaultValue: 'Dostępne kolumny' })}
      >
        <header className="flex items-center justify-between border-b px-3 py-2 text-xs font-medium uppercase text-muted-foreground">
          <span>{t('exports.column_picker.available_heading', { defaultValue: 'Dostępne' })}</span>
        </header>
        <div className="max-h-[60vh] space-y-3 overflow-y-auto p-3">
          {available.map((group) => (
            <div key={group.id}>
              <div className="mb-1 text-xs font-medium text-muted-foreground">
                {t(group.labelKey, { defaultValue: group.defaultLabel })}
              </div>
              <ul className="space-y-1">
                {group.columns.map((column) => (
                  <li key={column.key}>
                    <label className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted">
                      <input
                        type="checkbox"
                        className="size-4 accent-zinc-900"
                        checked={isSelected(column.key)}
                        onChange={() => toggle(column.key)}
                      />
                      <span>{t(column.labelKey, { defaultValue: column.defaultLabel })}</span>
                      <code className="ml-auto text-xs text-muted-foreground">{column.key}</code>
                    </label>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </section>

      <section
        className="rounded-md border bg-card"
        aria-label={t('exports.column_picker.selected_aria', { defaultValue: 'Wybrane kolumny' })}
      >
        <header className="flex items-center justify-between border-b px-3 py-2 text-xs font-medium uppercase text-muted-foreground">
          <span>
            {t('exports.column_picker.selected_heading', {
              count: selected.length,
              defaultValue: `Wybrane (${selected.length})`,
            })}
          </span>
          {selected.length > 0 && (
            <button
              type="button"
              className="text-xs font-normal text-rose-700 hover:underline"
              onClick={() => onChange([])}
            >
              {t('exports.column_picker.clear_all', { defaultValue: 'Wyczyść' })}
            </button>
          )}
        </header>
        <ol className="max-h-[60vh] divide-y overflow-y-auto">
          {selected.length === 0 ? (
            <li className="p-4 text-center text-sm text-muted-foreground">
              {t('exports.column_picker.empty', { defaultValue: 'Zaznacz kolumny po lewej.' })}
            </li>
          ) : (
            selected.map((key, index) => (
              <li key={key} className="flex items-center justify-between gap-2 px-3 py-2">
                <code className="truncate text-sm">{key}</code>
                <div className="inline-flex items-center gap-1">
                  <button
                    type="button"
                    onClick={() => move(index, -1)}
                    disabled={index === 0}
                    className="rounded border border-input bg-background px-2 py-0.5 text-xs disabled:opacity-30"
                    aria-label={t('exports.column_picker.move_up', {
                      defaultValue: 'Przesuń w górę',
                    })}
                  >
                    ↑
                  </button>
                  <button
                    type="button"
                    onClick={() => move(index, 1)}
                    disabled={index === selected.length - 1}
                    className="rounded border border-input bg-background px-2 py-0.5 text-xs disabled:opacity-30"
                    aria-label={t('exports.column_picker.move_down', {
                      defaultValue: 'Przesuń w dół',
                    })}
                  >
                    ↓
                  </button>
                  <button
                    type="button"
                    onClick={() => remove(key)}
                    className="rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs text-rose-900"
                    aria-label={t('exports.column_picker.remove', { defaultValue: 'Usuń' })}
                  >
                    ×
                  </button>
                </div>
              </li>
            ))
          )}
        </ol>
      </section>
    </div>
  );
}

export const BUILT_IN_COLUMN_GROUPS: readonly ColumnGroup[] = [
  {
    id: 'identity',
    labelKey: 'exports.column_picker.group_identity',
    defaultLabel: 'Identyfikacja',
    columns: [
      { key: 'sku', labelKey: 'exports.columns.sku', defaultLabel: 'SKU' },
      { key: 'parent_sku', labelKey: 'exports.columns.parent_sku', defaultLabel: 'SKU rodzica' },
      { key: 'category', labelKey: 'exports.columns.category', defaultLabel: 'Kategorie' },
    ],
  },
  {
    id: 'lifecycle',
    labelKey: 'exports.column_picker.group_lifecycle',
    defaultLabel: 'Stan',
    columns: [
      { key: 'status', labelKey: 'exports.columns.status', defaultLabel: 'Status' },
      { key: 'enabled', labelKey: 'exports.columns.enabled', defaultLabel: 'Włączony' },
      {
        key: 'completeness_pct',
        labelKey: 'exports.columns.completeness_pct',
        defaultLabel: 'Kompletność (%)',
      },
      { key: 'created_at', labelKey: 'exports.columns.created_at', defaultLabel: 'Utworzono' },
      { key: 'updated_at', labelKey: 'exports.columns.updated_at', defaultLabel: 'Zmodyfikowano' },
    ],
  },
] as const;

export default ColumnPicker;
