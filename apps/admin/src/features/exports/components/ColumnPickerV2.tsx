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
import { ChevronDown, GripVertical, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import type { ColumnGroup, ColumnOption } from './column-catalog';

interface ColumnPickerV2Props {
  groups: ColumnGroup[];
  /** Selected column keys IN FILE ORDER. */
  value: string[];
  onChange: (columns: string[]) => void;
  /** Always-present first column (round-trip natural key, e.g. sku). */
  lockedKey?: string;
  isLoading?: boolean;
}

interface FlatOption extends ColumnOption {
  groupId: string;
  groupLabel: string;
}

/**
 * EXR-11 — two-pane column picker (screen 4): left = available
 * attributes in collapsible groups with search + group checkboxes,
 * right = selected columns whose order equals the file column order
 * (dnd-kit drag handle + keyboard alternative on the handle).
 */
export function ColumnPickerV2({
  groups,
  value,
  onChange,
  lockedKey,
  isLoading = false,
}: ColumnPickerV2Props) {
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const flatByKey = useMemo(() => {
    const map = new Map<string, FlatOption>();
    for (const group of groups) {
      for (const column of group.columns) {
        map.set(column.key, {
          ...column,
          groupId: group.id,
          groupLabel: t(group.labelKey, { defaultValue: group.defaultLabel }),
        });
      }
    }
    return map;
  }, [groups, t]);

  const needle = search.trim().toLowerCase();
  const visibleGroups = useMemo(() => {
    if (needle === '') return groups;
    return groups
      .map((group) => ({
        ...group,
        columns: group.columns.filter(
          (column) =>
            column.key.toLowerCase().includes(needle) ||
            t(column.labelKey, { defaultValue: column.defaultLabel })
              .toLowerCase()
              .includes(needle),
        ),
      }))
      .filter((group) => group.columns.length > 0);
  }, [groups, needle, t]);

  const selectedSet = useMemo(() => new Set(value), [value]);
  const allKeys = useMemo(
    () => groups.flatMap((group) => group.columns.map((column) => column.key)),
    [groups],
  );

  const ensureLocked = (keys: string[]): string[] => {
    if (lockedKey === undefined) return keys;
    return [lockedKey, ...keys.filter((key) => key !== lockedKey)];
  };

  const toggle = (key: string, checked: boolean) => {
    if (key === lockedKey && !checked) return;
    if (checked) {
      onChange(ensureLocked([...value.filter((existing) => existing !== key), key]));
    } else {
      onChange(ensureLocked(value.filter((existing) => existing !== key)));
    }
  };

  const toggleGroup = (group: ColumnGroup, checked: boolean) => {
    const keys = group.columns.map((column) => column.key);
    if (checked) {
      const additions = keys.filter((key) => !selectedSet.has(key));
      onChange(ensureLocked([...value, ...additions]));
    } else {
      onChange(ensureLocked(value.filter((key) => !keys.includes(key) || key === lockedKey)));
    }
  };

  const selectAll = () => onChange(ensureLocked(allKeys));
  const clearAll = () => onChange(ensureLocked([]));

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = value.indexOf(String(active.id));
    const newIndex = value.indexOf(String(over.id));
    if (oldIndex === -1 || newIndex === -1) return;
    onChange(ensureLocked(arrayMove(value, oldIndex, newIndex)));
  };

  if (isLoading) {
    return <div className="h-64 animate-pulse rounded-2xl bg-zinc-100" aria-busy="true" />;
  }

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
      {/* LEFT — available */}
      <section
        aria-label={t('exports.picker.available_aria')}
        className="rounded-2xl border border-zinc-200 bg-surface shadow-card"
      >
        <div className="flex items-center gap-2 border-b border-zinc-100 px-4 py-3">
          <h3 className="flex-1 text-[11px] font-medium tracking-wider text-zinc-500 uppercase">
            {t('exports.picker.available_title')}
          </h3>
          <button
            type="button"
            onClick={selectAll}
            className="focus-ring text-[12px] font-medium text-zinc-500 hover:text-ink"
          >
            {t('exports.picker.select_all')}
          </button>
        </div>
        <div className="px-4 py-3">
          <div className="relative">
            <Search
              className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-zinc-500"
              aria-hidden
            />
            <input
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={t('exports.picker.search_placeholder')}
              aria-label={t('exports.picker.search_placeholder')}
              className="focus-ring h-9 w-full rounded-xl border border-zinc-200 bg-surface pr-3 pl-8 text-[13px] placeholder:text-zinc-500"
            />
          </div>
        </div>
        <div className="max-h-[420px] overflow-y-auto px-2 pb-2">
          {visibleGroups.map((group) => {
            const keys = group.columns.map((column) => column.key);
            const selectedCount = keys.filter((key) => selectedSet.has(key)).length;
            const isCollapsed = needle === '' && collapsed[group.id] === true;
            const groupLabel = t(group.labelKey, { defaultValue: group.defaultLabel });
            return (
              <div key={group.id} className="mb-1">
                <div className="flex items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-zinc-50">
                  <input
                    type="checkbox"
                    aria-label={t('exports.picker.group_checkbox_aria', { group: groupLabel })}
                    checked={selectedCount === keys.length && keys.length > 0}
                    ref={(node) => {
                      if (node) {
                        node.indeterminate = selectedCount > 0 && selectedCount < keys.length;
                      }
                    }}
                    onChange={(event) => toggleGroup(group, event.target.checked)}
                    className="size-4 rounded border-zinc-300"
                  />
                  <button
                    type="button"
                    aria-expanded={!isCollapsed}
                    onClick={() =>
                      setCollapsed((previous) => ({ ...previous, [group.id]: !isCollapsed }))
                    }
                    className="focus-ring flex flex-1 items-center gap-1.5 text-left"
                  >
                    <span className="text-[11px] font-medium tracking-wider text-zinc-500 uppercase">
                      {groupLabel}
                    </span>
                    <span
                      className={cn(
                        'num rounded px-1.5 py-0.5 font-mono text-[10px] font-semibold',
                        selectedCount > 0 ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-600',
                      )}
                    >
                      {selectedCount}/{keys.length}
                    </span>
                    <ChevronDown
                      aria-hidden
                      className={cn(
                        'ml-auto size-3.5 text-zinc-500 transition-transform',
                        isCollapsed && '-rotate-90',
                      )}
                    />
                  </button>
                </div>
                {!isCollapsed && (
                  <ul className="mt-0.5 space-y-0.5 pl-7">
                    {group.columns.map((column) => {
                      const label = t(column.labelKey, { defaultValue: column.defaultLabel });
                      return (
                        <li key={column.key}>
                          <label className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-[13px] hover:bg-zinc-50">
                            <input
                              type="checkbox"
                              checked={selectedSet.has(column.key)}
                              disabled={column.key === lockedKey}
                              onChange={(event) => toggle(column.key, event.target.checked)}
                              className="size-4 rounded border-zinc-300"
                            />
                            <span className="min-w-0 flex-1 truncate text-zinc-700">{label}</span>
                            <span className="font-mono text-[10.5px] text-zinc-500">
                              {column.key}
                            </span>
                          </label>
                        </li>
                      );
                    })}
                  </ul>
                )}
              </div>
            );
          })}
          {visibleGroups.length === 0 && (
            <p className="px-3 py-6 text-center text-[12.5px] text-zinc-500">
              {t('exports.picker.no_matches')}
            </p>
          )}
        </div>
      </section>

      {/* RIGHT — selected, ordered */}
      <section
        aria-label={t('exports.picker.selected_aria')}
        className="rounded-2xl border border-zinc-200 bg-surface shadow-card"
      >
        <div className="flex items-center gap-2 border-b border-zinc-100 px-4 py-3">
          <h3 className="flex-1 text-[11px] font-medium tracking-wider text-zinc-500 uppercase">
            {t('exports.picker.selected_title', { count: value.length })}
          </h3>
          <button
            type="button"
            onClick={clearAll}
            className="focus-ring text-[12px] font-medium text-brick-500 hover:text-brick-700"
          >
            {t('exports.picker.clear')}
          </button>
        </div>
        <p className="px-4 pt-2 text-[11.5px] text-zinc-500">{t('exports.picker.order_hint')}</p>
        <div className="max-h-[420px] overflow-y-auto px-3 py-3">
          {value.length === 0 ? (
            <p className="px-2 py-6 text-center text-[12.5px] text-zinc-500">
              {t('exports.picker.none_selected')}
            </p>
          ) : (
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragEnd={handleDragEnd}
            >
              <SortableContext items={value} strategy={verticalListSortingStrategy}>
                <ol className="space-y-1.5">
                  {value.map((key, index) => {
                    const option = flatByKey.get(key);
                    return (
                      <SelectedRow
                        key={key}
                        id={key}
                        index={index}
                        label={
                          option ? t(option.labelKey, { defaultValue: option.defaultLabel }) : key
                        }
                        groupLabel={option?.groupLabel ?? ''}
                        locked={key === lockedKey}
                        onRemove={() => toggle(key, false)}
                      />
                    );
                  })}
                </ol>
              </SortableContext>
            </DndContext>
          )}
        </div>
      </section>
    </div>
  );
}

interface SelectedRowProps {
  id: string;
  index: number;
  label: string;
  groupLabel: string;
  locked: boolean;
  onRemove: () => void;
}

function SelectedRow({ id, index, label, groupLabel, locked, onRemove }: SelectedRowProps) {
  const { t } = useTranslation();
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    disabled: locked,
  });

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        'flex items-center gap-2.5 rounded-xl border border-zinc-200 bg-surface px-3 py-2',
        isDragging && 'soft-shadow z-10 opacity-90',
      )}
    >
      <span className="num w-5 shrink-0 text-center font-mono text-[11px] text-zinc-500">
        {index + 1}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block truncate text-[13px] font-medium text-ink">{label}</span>
        <span className="block truncate text-[11px] text-zinc-500">
          {groupLabel}
          <span className="ml-1.5 font-mono">{id}</span>
        </span>
      </span>
      {locked ? (
        <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-[9.5px] font-semibold tracking-wider text-zinc-500 uppercase">
          {t('exports.picker.locked_badge')}
        </span>
      ) : (
        <>
          <button
            type="button"
            aria-label={t('exports.picker.drag_handle_aria', { column: label })}
            className="focus-ring grid h-7 w-7 cursor-grab place-items-center rounded-md text-zinc-500 hover:bg-zinc-100 hover:text-ink active:cursor-grabbing"
            {...attributes}
            {...listeners}
          >
            <GripVertical className="size-3.5" aria-hidden />
          </button>
          <button
            type="button"
            aria-label={t('exports.picker.remove_aria', { column: label })}
            onClick={onRemove}
            className="focus-ring grid h-7 w-7 place-items-center rounded-md text-zinc-500 hover:bg-brick-50 hover:text-brick-600"
          >
            <X className="size-3.5" aria-hidden />
          </button>
        </>
      )}
    </li>
  );
}
