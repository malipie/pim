import {
  closestCenter,
  DndContext,
  type DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronDown, ChevronRight, GripVertical } from 'lucide-react';
import { useState } from 'react';
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
 * EXP-10 (#589) — Two-pane column picker.
 *
 * Left pane: groups of available columns with checkboxes. Click checks
 * → adds to right pane. Re-check unchecks.
 *
 * Right pane: ordered list of selected columns with X buttons to
 * remove. Reordering supports two paths so keyboard-only operators
 * are not locked out (EXP-19 #631):
 *   - Drag the grip handle (PointerSensor) for mouse / touch users.
 *   - Tab to ↑↓ buttons for keyboard users (KeyboardSensor on the
 *     grip also works via dnd-kit, but the explicit buttons remain
 *     so the affordance is visible).
 *
 * Świadome odejścia:
 *  - No search filter (typeahead) in the left pane — list is
 *    small enough in MVP (~10 built-ins + per-tenant attributes
 *    when EXP-11 wires them in). Adding a search input is a
 *    follow-up the same instant attribute count grows past ~30.
 *  - No locale / channel sub-selectors for scopable attributes
 *    — locale checkboxes live as a separate section in the modal
 *    (EXP-11) so the picker stays focused on "which columns",
 *    not "in how many variants".
 */
/**
 * Detects locale-variant columns (e.g. `description.pl`, `description.en`)
 * and groups them under their parent attribute code.
 *
 * A "locale group" requires ≥2 columns sharing the same prefix before the
 * last `.`. Single columns (or single-variant attributes) stay flat.
 */
function buildVisualGroups(columns: ColumnOption[]): VisualItem[] {
  const byParent = new Map<string, ColumnOption[]>();
  const order: string[] = [];

  for (const col of columns) {
    const dot = col.key.lastIndexOf('.');
    const parent = dot !== -1 ? col.key.slice(0, dot) : null;
    if (parent !== null) {
      if (!byParent.has(parent)) {
        byParent.set(parent, []);
        order.push(parent);
        // Scopable attributes emit [bare, ...channelCols]. If the bare key was
        // already queued as __bare__X, absorb it as the first variant so that
        // the attribute appears exactly once (as a group) rather than twice
        // (flat bare + expandable channel group).
        const bareKey = `__bare__${parent}`;
        if (byParent.has(bareKey)) {
          // biome-ignore lint/style/noNonNullAssertion: checked above
          byParent.get(parent)!.push(...byParent.get(bareKey)!);
          byParent.delete(bareKey);
          const idx = order.indexOf(bareKey);
          if (idx !== -1) order.splice(idx, 1);
        }
      }
      // biome-ignore lint/style/noNonNullAssertion: guaranteed to exist
      byParent.get(parent)!.push(col);
    } else {
      order.push(`__bare__${col.key}`);
      byParent.set(`__bare__${col.key}`, [col]);
    }
  }

  const result: VisualItem[] = [];
  for (const key of order) {
    const cols = byParent.get(key) ?? [];
    if (cols.length >= 2 && !key.startsWith('__bare__')) {
      result.push({ kind: 'locale-group', parentCode: key, variants: cols });
    } else {
      for (const col of cols) result.push(col);
    }
  }
  return result;
}

interface LocaleGroupOption {
  kind: 'locale-group';
  parentCode: string;
  variants: ColumnOption[];
}

type VisualItem = ColumnOption | LocaleGroupOption;

function isLocaleGroup(item: VisualItem): item is LocaleGroupOption {
  return 'kind' in item && item.kind === 'locale-group';
}

export function ColumnPicker({
  available,
  selected,
  onChange,
}: ColumnPickerProps): React.ReactElement {
  const { t } = useTranslation();
  // Track which locale-groups are expanded (by parentCode).
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const isSelected = (key: string) => selected.includes(key);

  const toggleExpand = (parentCode: string) => {
    setExpandedGroups((prev) => {
      const next = new Set(prev);
      if (next.has(parentCode)) next.delete(parentCode);
      else next.add(parentCode);
      return next;
    });
  };

  const toggleLocaleGroup = (group: LocaleGroupOption) => {
    const keys = group.variants.map((v) => v.key);
    const allSelected = keys.every((k) => selected.includes(k));
    if (allSelected) {
      onChange(selected.filter((k) => !keys.includes(k)));
    } else {
      const toAdd = keys.filter((k) => !selected.includes(k));
      onChange([...selected, ...toAdd]);
    }
  };

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

  const onDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (over === null || active.id === over.id) return;
    const oldIndex = selected.indexOf(String(active.id));
    const newIndex = selected.indexOf(String(over.id));
    if (oldIndex === -1 || newIndex === -1) return;
    onChange(arrayMove([...selected], oldIndex, newIndex));
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
          {available.map((group) => {
            const visualItems = buildVisualGroups(group.columns);
            return (
              <div key={group.id}>
                <div className="mb-1 text-xs font-medium text-muted-foreground">
                  {t(group.labelKey, { defaultValue: group.defaultLabel })}
                </div>
                <ul className="space-y-1">
                  {visualItems.map((item) => {
                    if (isLocaleGroup(item)) {
                      const keys = item.variants.map((v) => v.key);
                      const allSel = keys.every((k) => selected.includes(k));
                      const someSel = !allSel && keys.some((k) => selected.includes(k));
                      const expanded = expandedGroups.has(item.parentCode);
                      const parentLabel = item.variants[0]
                        ? t(item.variants[0].labelKey, {
                            defaultValue: item.variants[0].defaultLabel,
                          }).split(' [')[0]
                        : item.parentCode;
                      return (
                        <li key={item.parentCode}>
                          <div className="flex items-center gap-1 rounded px-2 py-1 text-sm hover:bg-muted">
                            <input
                              type="checkbox"
                              className="size-4 accent-zinc-900"
                              checked={allSel}
                              ref={(el) => {
                                if (el) el.indeterminate = someSel;
                              }}
                              onChange={() => {
                                toggleLocaleGroup(item);
                              }}
                            />
                            <button
                              type="button"
                              className="flex flex-1 cursor-pointer items-center gap-1 text-left"
                              onClick={() => {
                                toggleExpand(item.parentCode);
                              }}
                            >
                              {expanded ? (
                                <ChevronDown
                                  className="size-3 text-muted-foreground"
                                  aria-hidden="true"
                                />
                              ) : (
                                <ChevronRight
                                  className="size-3 text-muted-foreground"
                                  aria-hidden="true"
                                />
                              )}
                              <span>{parentLabel}</span>
                              <span className="ml-1 text-xs text-muted-foreground">
                                ({keys.filter((k) => selected.includes(k)).length}/{keys.length})
                              </span>
                              <code className="ml-auto text-xs text-muted-foreground">
                                {item.parentCode}
                              </code>
                            </button>
                          </div>
                          {expanded && (
                            <ul className="ml-6 mt-0.5 space-y-0.5">
                              {item.variants.map((col) => (
                                <li key={col.key}>
                                  <label className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted">
                                    <input
                                      type="checkbox"
                                      className="size-4 accent-zinc-900"
                                      checked={isSelected(col.key)}
                                      onChange={() => {
                                        toggle(col.key);
                                      }}
                                    />
                                    <span>
                                      {t(col.labelKey, { defaultValue: col.defaultLabel })}
                                    </span>
                                    <code className="ml-auto text-xs text-muted-foreground">
                                      {col.key}
                                    </code>
                                  </label>
                                </li>
                              ))}
                            </ul>
                          )}
                        </li>
                      );
                    }
                    return (
                      <li key={item.key}>
                        <label className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted">
                          <input
                            type="checkbox"
                            className="size-4 accent-zinc-900"
                            checked={isSelected(item.key)}
                            onChange={() => {
                              toggle(item.key);
                            }}
                          />
                          <span>{t(item.labelKey, { defaultValue: item.defaultLabel })}</span>
                          <code className="ml-auto text-xs text-muted-foreground">{item.key}</code>
                        </label>
                      </li>
                    );
                  })}
                </ul>
              </div>
            );
          })}
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
        {selected.length === 0 ? (
          <ol className="max-h-[60vh] overflow-y-auto">
            <li className="p-4 text-center text-sm text-muted-foreground">
              {t('exports.column_picker.empty', { defaultValue: 'Zaznacz kolumny po lewej.' })}
            </li>
          </ol>
        ) : (
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
            <SortableContext items={[...selected]} strategy={verticalListSortingStrategy}>
              <ol className="max-h-[60vh] divide-y overflow-y-auto">
                {selected.map((key, index) => (
                  <SortableColumnRow
                    key={key}
                    columnKey={key}
                    index={index}
                    total={selected.length}
                    onMoveUp={() => move(index, -1)}
                    onMoveDown={() => move(index, 1)}
                    onRemove={() => remove(key)}
                    labels={{
                      dragHandle: t('exports.column_picker.drag_handle', {
                        defaultValue: 'Przeciągnij, aby zmienić kolejność',
                      }),
                      moveUp: t('exports.column_picker.move_up', {
                        defaultValue: 'Przesuń w górę',
                      }),
                      moveDown: t('exports.column_picker.move_down', {
                        defaultValue: 'Przesuń w dół',
                      }),
                      remove: t('exports.column_picker.remove', { defaultValue: 'Usuń' }),
                    }}
                  />
                ))}
              </ol>
            </SortableContext>
          </DndContext>
        )}
      </section>
    </div>
  );
}

interface SortableColumnRowProps {
  columnKey: string;
  index: number;
  total: number;
  onMoveUp: () => void;
  onMoveDown: () => void;
  onRemove: () => void;
  labels: {
    dragHandle: string;
    moveUp: string;
    moveDown: string;
    remove: string;
  };
}

function SortableColumnRow({
  columnKey,
  index,
  total,
  onMoveUp,
  onMoveDown,
  onRemove,
  labels,
}: SortableColumnRowProps): React.ReactElement {
  const sortable = useSortable({ id: columnKey });
  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(sortable.transform),
    transition: sortable.transition,
    opacity: sortable.isDragging ? 0.6 : 1,
  };

  return (
    <li
      ref={sortable.setNodeRef}
      style={style}
      className="flex items-center justify-between gap-2 bg-card px-3 py-2"
    >
      <div className="flex items-center gap-2 min-w-0">
        <button
          type="button"
          {...sortable.attributes}
          {...sortable.listeners}
          aria-label={labels.dragHandle}
          className="grid size-6 cursor-grab place-items-center rounded text-muted-foreground hover:bg-muted active:cursor-grabbing focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <GripVertical className="size-4" aria-hidden="true" />
        </button>
        <code className="truncate text-sm">{columnKey}</code>
      </div>
      <div className="inline-flex items-center gap-1">
        <button
          type="button"
          onClick={onMoveUp}
          disabled={index === 0}
          className="rounded border border-input bg-background px-2 py-0.5 text-xs disabled:opacity-30"
          aria-label={labels.moveUp}
        >
          ↑
        </button>
        <button
          type="button"
          onClick={onMoveDown}
          disabled={index === total - 1}
          className="rounded border border-input bg-background px-2 py-0.5 text-xs disabled:opacity-30"
          aria-label={labels.moveDown}
        >
          ↓
        </button>
        <button
          type="button"
          onClick={onRemove}
          className="rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs text-rose-900"
          aria-label={labels.remove}
        >
          ×
        </button>
      </div>
    </li>
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
