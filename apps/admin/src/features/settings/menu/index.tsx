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
import {
  Boxes,
  Cog,
  EyeOff,
  FileText,
  GripVertical,
  Image,
  LayoutDashboard,
  Lock,
  type LucideIcon,
  Package,
  Plug2,
  Plus,
  Settings2,
  Tag,
  Workflow,
  Wrench,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
  type EffectiveMenuItem,
  type MenuConfigurationItem,
  useEffectiveMenu,
  useReplaceMenuConfiguration,
} from '@/lib/use-effective-menu';
import { cn } from '@/lib/utils';

const ICON_MAP: Record<string, LucideIcon> = {
  Boxes,
  Cog,
  FileText,
  Image,
  LayoutDashboard,
  Package,
  Plug2,
  Settings2,
  Tag,
  Workflow,
  Wrench,
};

interface RowProps {
  item: EffectiveMenuItem;
  draggable: boolean;
  trailing?: React.ReactNode;
  labelText: string;
}

function Row({ item, draggable, trailing, labelText }: RowProps) {
  const Icon = ICON_MAP[item.icon] ?? Boxes;
  const sortable = useSortable({ id: item.id, disabled: !draggable });
  const style = sortable.transform
    ? {
        transform: CSS.Transform.toString(sortable.transform),
        transition: sortable.transition,
      }
    : undefined;

  return (
    <div
      ref={sortable.setNodeRef}
      style={style}
      className={cn(
        'flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 shadow-xs',
        sortable.isDragging && 'opacity-60',
      )}
    >
      {draggable ? (
        <button
          type="button"
          aria-label="Drag handle"
          {...sortable.attributes}
          {...sortable.listeners}
          className="grid size-7 cursor-grab place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100 active:cursor-grabbing"
        >
          <GripVertical className="size-4" />
        </button>
      ) : (
        <span className="grid size-7 place-items-center text-zinc-300">
          <Lock className="size-3.5" />
        </span>
      )}
      <Icon className="size-4 text-zinc-700" />
      <span className="flex-1 text-sm font-medium tracking-tight text-zinc-900">{labelText}</span>
      <span
        className={cn(
          'rounded px-1.5 py-0.5 text-[9.5px] font-semibold uppercase tracking-wider',
          item.kind === 'system' ? 'bg-zinc-100 text-zinc-600' : 'bg-orange-500/10 text-orange-700',
        )}
      >
        {item.kind === 'system' ? 'SYS' : 'OT'}
      </span>
      {item.comingSoon ? (
        <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] uppercase text-amber-800">
          Wkrótce
        </span>
      ) : null}
      {trailing}
    </div>
  );
}

/**
 * VIEW-08 (#427) — Settings · Menu drag-drop.
 *
 * Two sections:
 *   - Visible (drag-drop sortable) — the items currently rendered in the
 *     left sidebar, in order. Protected items (`settings`, `modeling`)
 *     can be reordered but not hidden.
 *   - Available (hidden) — exposed ObjectTypes not yet in the menu, plus
 *     items currently hidden (visible=false). Click `+ Dodaj` to push to
 *     the end of Visible.
 *
 * Auto-save: every drag-end / show / hide flushes a `PUT /api/menu_configuration`
 * with the entire items array. Optimistic update via `useReplaceMenuConfiguration`'s
 * `onSuccess` query invalidation — the sidebar rerenders without a manual reload.
 */
export function MenuSettingsPage() {
  const { t } = useTranslation();
  const { data, isLoading, isError } = useEffectiveMenu();
  const replace = useReplaceMenuConfiguration();

  const [localItems, setLocalItems] = useState<EffectiveMenuItem[]>([]);
  const [localAvailable, setLocalAvailable] = useState<EffectiveMenuItem[]>([]);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Re-hydrate local state when the server returns a fresh effective menu.
  // We trust the server's order — `position` is canonical.
  useEffect(() => {
    if (data) {
      setLocalItems(sortByPosition(data.visible));
      setLocalAvailable(data.available);
    }
  }, [data]);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const persist = useCallback(
    async (visible: EffectiveMenuItem[], hiddenObjectTypes: EffectiveMenuItem[]) => {
      setErrorMessage(null);
      const payload = buildPayload(visible, hiddenObjectTypes);
      try {
        await replace.mutateAsync(payload);
      } catch (err) {
        setErrorMessage(
          err instanceof Error
            ? err.message
            : t('settings.menu.toast_error', {
                defaultValue: 'Nie udało się zapisać zmian',
              }),
        );
      }
    },
    [replace, t],
  );

  const onDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event;
      if (!over || active.id === over.id) {
        return;
      }
      const oldIndex = localItems.findIndex((it) => it.id === active.id);
      const newIndex = localItems.findIndex((it) => it.id === over.id);
      if (oldIndex < 0 || newIndex < 0) return;

      const reordered = arrayMove(localItems, oldIndex, newIndex);
      setLocalItems(reordered);
      void persist(reordered, hiddenObjectTypesFromAvailable(localAvailable));
    },
    [localAvailable, localItems, persist],
  );

  const hideItem = useCallback(
    (item: EffectiveMenuItem) => {
      if (item.protected) {
        return;
      }
      const nextVisible = localItems.filter((it) => it.id !== item.id);
      // Object_type items go back to "Available"; system items disappear
      // from the visible list (they are never in available — they live in
      // the registry, hidden=invisible system items are encoded in the
      // payload so we add them back at the end as visible=false rows on
      // the next PUT, but we don't render them in the UI).
      const nextAvailable =
        item.kind === 'object_type' ? [...localAvailable, item] : localAvailable;
      setLocalItems(nextVisible);
      setLocalAvailable(nextAvailable);
      void persist(nextVisible, hiddenObjectTypesFromAvailable(nextAvailable));
    },
    [localAvailable, localItems, persist],
  );

  const addItem = useCallback(
    (item: EffectiveMenuItem) => {
      const nextVisible = [...localItems, item];
      const nextAvailable = localAvailable.filter((it) => it.id !== item.id);
      setLocalItems(nextVisible);
      setLocalAvailable(nextAvailable);
      void persist(nextVisible, hiddenObjectTypesFromAvailable(nextAvailable));
    },
    [localAvailable, localItems, persist],
  );

  const visibleIds = useMemo(() => localItems.map((it) => it.id), [localItems]);

  if (isLoading) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }
  if (isError || !data) {
    return (
      <p className="text-sm text-destructive">
        {t('settings.menu.error_loading', {
          defaultValue: 'Nie udało się załadować konfiguracji menu.',
        })}
      </p>
    );
  }

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('settings.menu.title', { defaultValue: 'Menu główne' })}
        </h1>
        <p className="text-sm text-muted-foreground">
          {t('settings.menu.intro', {
            defaultValue:
              'Przeciągnij pozycje, żeby zmienić ich kolejność. Ukryte pozycje pojawiają się poniżej i można je dodać z powrotem do menu.',
          })}
        </p>
      </header>

      {errorMessage ? (
        <div className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
          {errorMessage}
        </div>
      ) : null}

      <Card className="space-y-3 p-6">
        <h2 className="text-[13px] font-semibold uppercase tracking-wider text-zinc-500">
          {t('settings.menu.section_visible', { defaultValue: 'Widoczne w menu' })}
        </h2>
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
          <SortableContext items={visibleIds} strategy={verticalListSortingStrategy}>
            <div className="flex flex-col gap-2" data-testid="menu-visible-list">
              {localItems.map((item) => (
                <Row
                  key={item.id}
                  item={item}
                  draggable
                  labelText={renderLabel(item, t)}
                  trailing={
                    item.protected ? (
                      <span
                        className="grid size-8 place-items-center rounded-lg text-zinc-300"
                        title={t('settings.menu.protected_tooltip', {
                          defaultValue: 'Ta pozycja jest wymagana i nie może być ukryta',
                        })}
                      >
                        <Lock className="size-3.5" />
                      </span>
                    ) : (
                      <button
                        type="button"
                        aria-label={t('settings.menu.action_hide', { defaultValue: 'Ukryj' })}
                        onClick={() => hideItem(item)}
                        className="grid size-8 place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700"
                      >
                        <EyeOff className="size-4" />
                      </button>
                    )
                  }
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      </Card>

      <Card className="space-y-3 p-6">
        <h2 className="text-[13px] font-semibold uppercase tracking-wider text-zinc-500">
          {t('settings.menu.section_available', { defaultValue: 'Dostępne (ukryte)' })}
        </h2>
        {localAvailable.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            {t('settings.menu.empty_available_prefix', {
              defaultValue: 'Aby dodać ObjectType do menu, włącz „Udostępnij do głównego menu" w',
            })}{' '}
            <Link
              to="/modeling/object-types"
              className="text-orange-700 underline underline-offset-2 hover:text-orange-700/80"
            >
              {t('settings.menu.empty_available_link', {
                defaultValue: 'Modelowanie → ObjectType',
              })}
            </Link>
            .
          </p>
        ) : (
          <div className="flex flex-col gap-2" data-testid="menu-available-list">
            {localAvailable.map((item) => (
              <Row
                key={item.id}
                item={item}
                draggable={false}
                labelText={renderLabel(item, t)}
                trailing={
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => addItem(item)}
                    className="h-8 gap-1 rounded-lg"
                  >
                    <Plus className="size-3.5" />
                    {t('settings.menu.action_add', { defaultValue: 'Dodaj' })}
                  </Button>
                }
              />
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}

function sortByPosition(items: EffectiveMenuItem[]): EffectiveMenuItem[] {
  return [...items].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
}

function renderLabel(
  item: EffectiveMenuItem,
  t: (key: string, opts?: { defaultValue?: string }) => string,
): string {
  if (item.label !== null) {
    return item.label;
  }
  if (item.labelKey) {
    return t(item.labelKey, { defaultValue: item.ref });
  }
  return item.ref;
}

function hiddenObjectTypesFromAvailable(available: EffectiveMenuItem[]): EffectiveMenuItem[] {
  // Only object_types live in `available` — system items don't need a
  // hidden record because we always send them as visible=true (the
  // backend rejects hiding protected ones, the others stay visible by
  // default in our UI). We carry forward object_types that the user
  // hid back to `available` so they show up in the next PUT as
  // visible=false, ensuring the BE remembers the operator's choice
  // even after a refresh.
  return available.filter((it) => it.kind === 'object_type');
}

function buildPayload(
  visible: EffectiveMenuItem[],
  hiddenObjectTypes: EffectiveMenuItem[],
): MenuConfigurationItem[] {
  const payload: MenuConfigurationItem[] = visible.map((it, index) => ({
    kind: it.kind,
    ref: it.ref,
    position: index,
    visible: true,
  }));

  // Append hidden object_types after the visible block so positions stay
  // unique. The BE retains them as visible=false and the FE will pick
  // them up in the next `available` list.
  let nextPosition = payload.length;
  for (const it of hiddenObjectTypes) {
    payload.push({
      kind: it.kind,
      ref: it.ref,
      position: nextPosition++,
      visible: false,
    });
  }

  return payload;
}
