import { useQuery, useQueryClient } from '@tanstack/react-query';
import { FolderTree, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { buildCategoryTree, CategoryTree } from '@/components/modeling/category-tree';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { unwrapAttributesIndexed } from '@/lib/attributes-indexed';
import { HttpError, jsonFetch } from '@/lib/http';

interface CategoryRow {
  id: string;
  code: string;
  path?: string | null;
  attributesIndexed?: Record<string, unknown> | null;
}

interface CategoriesListResponse {
  'hydra:member'?: CategoryRow[];
  member?: CategoryRow[];
}

export interface CurrentAssignment {
  categoryId: string;
  isPrimary: boolean;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /**
   * When non-empty, the dialog autosaves via `PUT /api/products/{id}/categories`
   * and bursts the related query cache. When empty, the dialog runs in
   * controlled mode: nothing hits the API; the caller receives the new
   * selection through {@link onSelect} and is responsible for persisting
   * it (e.g. as part of a POST `/api/products` payload for create flows).
   */
  productId: string;
  /** Current assignments — used to seed the picker state when the dialog opens. */
  currentAssignments: CurrentAssignment[];
  /** Called after a successful PUT so the caller can refresh the chip list + effective groups. */
  onSaved: () => void;
  /**
   * #891 — controlled-mode callback fired in place of the PUT when
   * {@link productId} is the empty string. Receives the chosen
   * categories + the picked primary so the caller can stash them in
   * local state until the parent POST is fired.
   */
  onSelect?: (categoryIds: string[], primaryCategoryId: string | null) => void;
  /**
   * #1209 — which API surface to autosave against. `products` (default) keeps
   * the legacy `/api/products/{id}/categories` behaviour for the product
   * detail; `objects` targets the poly-kind `/api/objects/{id}/categories`
   * so custom kinds (and any ObjectType) can assign categories too.
   */
  endpoint?: 'products' | 'objects';
  /**
   * #1209 — ADR-015 tree scope. When set, the picker only lists categories
   * belonging to this ObjectType's tree (`?categoryTargetObjectType=`), so a
   * custom kind never sees product categories (and the backend
   * `assertSameTree` guard never rejects the save).
   */
  objectTypeId?: string;
}

/**
 * PCAT-05 (#478) — multi-select category picker for the product's
 * "Kategorie" tab. Loads the full category tree (capped at 200 rows
 * for the picker — typical mid-market catalogue is well under that),
 * lets the operator toggle an arbitrary subset, and pin one as
 * primary via a star button. Save dispatches a single
 * `PUT /api/products/{id}/categories` (atomic replace) so the partial
 * unique index never sees a transient state.
 *
 * Validation:
 *   - if at least one category is selected, exactly one must be primary
 *   - the picker auto-promotes the first selection to primary when no
 *     primary was set yet, mirroring the controller's promote-next
 *     semantics on DELETE
 */
export function CategoryPickerDialog({
  open,
  onOpenChange,
  productId,
  currentAssignments,
  onSaved,
  onSelect,
  endpoint = 'products',
  objectTypeId,
}: Props) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [primaryId, setPrimaryId] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Re-seed every time the dialog opens so reopening discards a
  // half-edited state from the previous open.
  useEffect(() => {
    if (!open) return;
    const ids = new Set<string>();
    let primary: string | null = null;
    for (const assignment of currentAssignments) {
      ids.add(assignment.categoryId);
      if (assignment.isPrimary) {
        primary = assignment.categoryId;
      }
    }
    setSelected(ids);
    setPrimaryId(primary);
    setSearch('');
    setError(null);
  }, [open, currentAssignments]);

  const { data, isLoading } = useQuery({
    // #1209 — key by tree so a custom kind's scoped list does not collide
    // with the product (all-trees) list in the cache.
    queryKey: ['categories', 'picker', objectTypeId ?? 'all'],
    queryFn: async () => {
      // Refine convention: itemsPerPage with a generous cap; the picker
      // is for admin-paced selection, not infinite-scroll. When scoped to an
      // ObjectType (ADR-015) only that tree's categories are listed.
      const treeFilter =
        objectTypeId !== undefined && objectTypeId !== ''
          ? `&categoryTargetObjectType=${encodeURIComponent(objectTypeId)}`
          : '';
      return jsonFetch<CategoriesListResponse>(`/api/categories?itemsPerPage=200${treeFilter}`);
    },
    enabled: open,
    staleTime: 60_000,
  });

  const rows = useMemo<CategoryRow[]>(() => {
    if (!data) return [];
    return data['hydra:member'] ?? data.member ?? [];
  }, [data]);

  const filteredRows = useMemo(() => {
    if (search.trim() === '') return rows;
    const needle = search.trim().toLowerCase();
    return rows.filter((row) => {
      if (row.code.toLowerCase().includes(needle)) return true;
      const name = unwrapAttributesIndexed(row.attributesIndexed).name;
      if (typeof name === 'string' && name.toLowerCase().includes(needle)) return true;
      if (name && typeof name === 'object') {
        const map = name as Record<string, string>;
        for (const value of Object.values(map)) {
          if (typeof value === 'string' && value.toLowerCase().includes(needle)) return true;
        }
      }
      return false;
    });
  }, [rows, search]);

  const tree = useMemo(
    () =>
      buildCategoryTree(
        filteredRows.map((row) => ({
          id: row.id,
          code: row.code,
          path: row.path ?? null,
          attributesIndexed: row.attributesIndexed ?? undefined,
        })),
      ),
    [filteredRows],
  );

  const handleToggle = (id: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
        // If the primary was deselected, drop the primary flag
        if (primaryId === id) setPrimaryId(null);
      } else {
        next.add(id);
        // Auto-promote the first selection to primary if none was set
        if (primaryId === null) setPrimaryId(id);
      }
      return next;
    });
  };

  const handlePrimaryChange = (id: string) => {
    if (!selected.has(id)) return;
    setPrimaryId(id);
  };

  const canSave = selected.size === 0 || (primaryId !== null && selected.has(primaryId));

  const handleSave = async () => {
    if (!canSave || isSaving) return;
    setIsSaving(true);
    setError(null);
    try {
      const categoryIds = Array.from(selected);
      const primary = selected.size === 0 ? null : primaryId;

      // #891 — controlled mode: when no product exists yet (create flow)
      // the caller owns persistence. Emit the selection, dismiss the
      // dialog, and skip the autosave PUT entirely.
      if (productId === '') {
        if (onSelect) onSelect(categoryIds, primary);
        onSaved();
        onOpenChange(false);
        return;
      }

      await jsonFetch(`/api/${endpoint}/${productId}/categories`, {
        method: 'PUT',
        contentType: 'application/json',
        accept: 'application/json',
        body: { primaryCategoryId: primary, categoryIds },
      });
      // Burst the affected cache keys so the chip list, the effective
      // schema, and any object-level reads pick up the change. The universal
      // detail page (#1209) keys reads under `['object', id, …]`; the legacy
      // product detail keys under `['products', id, …]`.
      const base = endpoint === 'objects' ? 'object' : 'products';
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: [base, productId, 'categories'] }),
        queryClient.invalidateQueries({
          queryKey: [base, productId, 'effective-attribute-groups'],
        }),
        queryClient.invalidateQueries({ queryKey: [base, productId] }),
      ]);
      onSaved();
      onOpenChange(false);
    } catch (e) {
      setError(
        e instanceof HttpError && e.body && typeof e.body === 'object' && 'detail' in e.body
          ? String((e.body as { detail: unknown }).detail)
          : t('categories.picker.error_generic', { defaultValue: 'Nie udało się zapisać' }),
      );
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <header className="mb-3 flex items-center gap-2">
          <FolderTree className="size-4 text-orange-600" />
          <h2 className="text-base font-semibold">
            {t('categories.picker.title', { defaultValue: 'Wybierz kategorie' })}
          </h2>
        </header>

        <div className="relative mb-3">
          <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('categories.picker.search_placeholder', {
              defaultValue: 'Szukaj kategorii…',
            })}
            className="w-full rounded-xl border border-zinc-200 bg-white py-2 pl-9 pr-3 text-[13px] focus:border-orange-300 focus:outline-none"
          />
        </div>

        <div className="max-h-[60vh] overflow-y-auto rounded-xl border border-zinc-100 bg-white p-3">
          {isLoading ? (
            <p className="px-2 py-4 text-center text-[12.5px] text-zinc-500">
              {t('app.loading', { defaultValue: 'Ładowanie…' })}
            </p>
          ) : tree.length === 0 ? (
            <p className="px-2 py-4 text-center text-[12.5px] text-zinc-500">
              {t('categories.picker.empty', { defaultValue: 'Brak wyników.' })}
            </p>
          ) : (
            <CategoryTree
              nodes={tree}
              onSelect={() => undefined}
              mode="multi-select"
              selectedIds={selected}
              onToggle={handleToggle}
              primaryId={primaryId}
              onPrimaryChange={handlePrimaryChange}
              initialExpanded={new Set(tree.map((n) => n.id))}
            />
          )}
        </div>

        {!canSave ? (
          <p className="mt-3 text-[11.5px] text-amber-600">
            {t('categories.picker.no_primary_warning', {
              defaultValue: 'Zaznacz kategorię główną (⭐) żeby zapisać.',
            })}
          </p>
        ) : null}

        {error !== null ? (
          <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-[12.5px] text-red-700">{error}</p>
        ) : null}

        <footer className="mt-4 flex items-center justify-between">
          <span className="text-[11.5px] text-zinc-500">
            {t('categories.picker.selected_count', {
              defaultValue: 'Wybrano: {{count}}',
              count: selected.size,
            })}
          </span>
          <div className="flex items-center gap-2">
            <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={isSaving}>
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button onClick={() => void handleSave()} disabled={!canSave || isSaving}>
              {isSaving
                ? t('categories.picker.saving', { defaultValue: 'Zapisywanie…' })
                : t('categories.picker.save', { defaultValue: 'Zapisz' })}
            </Button>
          </div>
        </footer>
      </DialogContent>
    </Dialog>
  );
}
