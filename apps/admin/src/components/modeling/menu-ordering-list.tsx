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
import { useQueryClient } from '@tanstack/react-query';
import { GripVertical } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { ObjectTypeIcon } from '@/components/modeling/object-type-icon';
import { jsonFetch } from '@/lib/http';
import {
  SIDEBAR_OBJECT_TYPES_QUERY_KEY,
  type SidebarObjectType,
  useObjectTypesMenu,
} from '@/lib/use-object-types-menu';
import { cn } from '@/lib/utils';

interface Props {
  language: string;
}

/**
 * VIEW-01c (#414) — drag-and-drop reorder of the visible sidebar entries.
 * Wraps `useObjectTypesMenu` so the same payload powers both the sidebar
 * and the Settings ordering control. POST `/api/object_types/menu/reorder`
 * after each drag, optimistic update on the cached query, fallback
 * invalidate-on-error so a partial failure rolls back.
 */
export function MenuOrderingList({ language }: Props) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { data: items = [], isLoading } = useObjectTypesMenu();
  const [error, setError] = useState<string | null>(null);

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;

    const oldIndex = items.findIndex((row) => row.id === active.id);
    const newIndex = items.findIndex((row) => row.id === over.id);
    if (oldIndex < 0 || newIndex < 0) return;

    const reordered = arrayMove(items, oldIndex, newIndex);
    queryClient.setQueryData<SidebarObjectType[]>(SIDEBAR_OBJECT_TYPES_QUERY_KEY, reordered);

    setError(null);
    try {
      await jsonFetch('/api/object_types/menu/reorder', {
        method: 'POST',
        contentType: 'application/json',
        body: { order: reordered.map((row) => row.id) },
      });
      void queryClient.invalidateQueries({ queryKey: SIDEBAR_OBJECT_TYPES_QUERY_KEY });
    } catch (e) {
      setError(e instanceof Error ? e.message : 'unknown');
      void queryClient.invalidateQueries({ queryKey: SIDEBAR_OBJECT_TYPES_QUERY_KEY });
    }
  };

  if (isLoading) {
    return (
      <p className="text-[12.5px] text-muted-foreground">
        {t('app.loading', { defaultValue: 'Ładowanie…' })}
      </p>
    );
  }

  if (items.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-zinc-200 px-4 py-6 text-center text-[12.5px] text-zinc-500">
        {t('object_types.menu_ordering_empty', {
          defaultValue:
            'Żaden typ nie jest widoczny w menu. Włącz „Widoczne w menu" w ustawieniach typu.',
        })}
      </div>
    );
  }

  return (
    <div>
      <p className="mb-3 text-[12px] text-muted-foreground">
        {t('object_types.menu_ordering_drag_hint', {
          defaultValue: 'Przeciągnij wiersze, żeby zmienić kolejność menu.',
        })}
      </p>
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={items.map((row) => row.id)} strategy={verticalListSortingStrategy}>
          <div className="space-y-1.5">
            {items.map((row) => (
              <SortableMenuRow key={row.id} row={row} language={language} />
            ))}
          </div>
        </SortableContext>
      </DndContext>
      {error !== null ? (
        <p role="alert" className="mt-3 rounded-md bg-rose-50 px-3 py-2 text-[12px] text-rose-700">
          {error}
        </p>
      ) : null}
    </div>
  );
}

function SortableMenuRow({ row, language }: { row: SidebarObjectType; language: string }) {
  const { t } = useTranslation();
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: row.id,
  });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  const labelText = row.label[language] ?? row.label.pl ?? row.label.en ?? row.code;

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        'flex items-center gap-3 rounded-xl border border-zinc-100 bg-white px-3 py-2.5',
        isDragging && 'border-zinc-300 bg-zinc-50 shadow-sm',
      )}
    >
      <button
        type="button"
        {...attributes}
        {...listeners}
        aria-label={t('object_types.menu_ordering_drag_handle', {
          defaultValue: 'Uchwyt do przeciągania',
        })}
        className="cursor-grab text-zinc-400 hover:text-zinc-700"
      >
        <GripVertical className="size-4" />
      </button>
      <ObjectTypeIcon icon={row.icon} color={row.color} kind={row.kind} size="sm" />
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <span className="truncate text-[13.5px] font-medium tracking-tight">{labelText}</span>
          {row.builtIn ? (
            <BuiltInLockBadge />
          ) : (
            <span className="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10.5px] font-medium uppercase tracking-wide text-emerald-700">
              custom
            </span>
          )}
        </div>
        <div className="truncate font-mono text-[11px] text-zinc-400">{row.code}</div>
      </div>
      <span className="num shrink-0 text-[11px] tabular-nums text-zinc-400">
        #{row.menuPosition}
      </span>
    </div>
  );
}
