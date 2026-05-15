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
import { GripVertical } from 'lucide-react';
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
export function ColumnPicker({
  available,
  selected,
  onChange,
}: ColumnPickerProps): React.ReactElement {
  const { t } = useTranslation();

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

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
