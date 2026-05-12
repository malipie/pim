import { useList } from '@refinedev/core';
import { Plus, Search, Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { AdvancedFilterBuilder } from '@/components/catalog/advanced-filter-builder';
import { BulkBar } from '@/components/catalog/bulk-bar';
import { EmptyStateProducts } from '@/components/catalog/empty-state-products';
import { type ExcelColumn, ExcelLikeGrid } from '@/components/catalog/excel-like-grid';
import { FilterPill } from '@/components/catalog/filter-pill';
import type { FilterValue } from '@/components/catalog/product-filter-chips';
import { ProductsGrid, type ProductsGridRow } from '@/components/catalog/products-grid';
import { SaveViewModal } from '@/components/catalog/save-view-modal';
import { SavedViewsRail } from '@/components/catalog/saved-views-rail';
import type { SyncAggregate } from '@/components/catalog/sync-aggregate-icon';
import { type VariantsMode, VariantsToggle } from '@/components/catalog/variants-toggle';
import { type ProductsViewMode, ViewModeToggle } from '@/components/catalog/view-mode-toggle';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import {
  type CatalogSearchHit,
  useCatalogSearch,
} from '@/features/catalog/search/use-catalog-search';
import { unwrapAttributesIndexed } from '@/lib/attributes-indexed';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface CatalogObjectListEntry {
  id: string;
  code: string;
  enabled?: boolean;
  status?: string;
  createdAt?: string;
  updatedAt?: string;
  attributesIndexed?: Record<string, unknown>;
  completenessPct?: number;
  syncStatusAggregate?: string;
  parent?: { id?: string } | null;
  parentId?: string | null;
}

const PRODUCT_FACETS = ['enabled', 'status', 'brand', 'family'];

/**
 * Column set for Excel view (UI-02.12 restored). SKU + status + variant
 * axis are read-only (system / derived). Name + brand commit through
 * the attributes payload; enabled commits at the product root. Other
 * columns are read-only because the value renderer flattens nested
 * structures (categories, price, sync) that the inline editor cannot
 * round-trip into a single PATCH.
 *
 * The intersection with `Record<string, unknown>` satisfies the
 * `ExcelLikeGrid` generic constraint without modifying the shared
 * `ProductsGridRow` interface (which has narrow per-key types).
 */
type ExcelProductRow = ProductsGridRow & Record<string, unknown>;

const EXCEL_COLUMNS: ExcelColumn<ExcelProductRow>[] = [
  { key: 'sku', label: 'SKU', type: 'text', width: 160, readOnly: true },
  { key: 'name', label: 'Nazwa', type: 'text', width: 280 },
  { key: 'brand', label: 'Marka', type: 'text', width: 160 },
  { key: 'enabled', label: 'Aktywny', type: 'boolean', width: 100 },
  { key: 'status', label: 'Status', type: 'text', width: 100, readOnly: true },
  { key: 'completenessPct', label: 'Kompletność', type: 'number', width: 110, readOnly: true },
  { key: 'variantAxis', label: 'Wariant', type: 'text', width: 120, readOnly: true },
];

const STATUS_VALUES: ReadonlyArray<{ value: string; label: string; sync: SyncAggregate }> = [
  { value: 'green', label: 'OK', sync: 'green' },
  { value: 'yellow', label: 'Niepełne', sync: 'yellow' },
  { value: 'red', label: 'Błąd', sync: 'red' },
];

const CHANNEL_VALUES: ReadonlyArray<string> = ['Shopify', 'BaseLinker', 'Allegro'];

export function ProductListPage() {
  const { t } = useTranslation();
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState<Record<string, string | string[]>>({});
  const [advancedFilters, setAdvancedFilters] = useState<Record<string, FilterValue>>({});
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [showSelectedOnly, setShowSelectedOnly] = useState(false);
  const [variantsMode, setVariantsMode] = useState<VariantsMode>('tree');
  const [viewMode, setViewMode] = useState<ProductsViewMode>(() => {
    if (typeof window === 'undefined') return 'grid';
    const stored = window.localStorage.getItem('pim.products.viewMode');
    return stored === 'excel' ? 'excel' : 'grid';
  });
  const handleViewModeChange = (next: ProductsViewMode): void => {
    setViewMode(next);
    if (typeof window !== 'undefined') {
      window.localStorage.setItem('pim.products.viewMode', next);
    }
  };
  const [activeViewSlug, setActiveViewSlug] = useState<string | null>(null);
  const [showSaveViewModal, setShowSaveViewModal] = useState(false);
  const [expandedMasters, setExpandedMasters] = useState<Set<string>>(new Set());

  const { searchFilters, rangeFilters } = useMemo(() => {
    const sf: Record<string, string | string[]> = { ...filters };
    const rf: Record<string, { gte?: number; lte?: number }> = {};
    for (const [key, value] of Object.entries(advancedFilters)) {
      if (value === null || value === undefined) continue;
      if (Array.isArray(value)) {
        sf[key] = value as string[];
      } else if (typeof value === 'object') {
        const range: { gte?: number; lte?: number } = {};
        if (typeof value.gte === 'number') range.gte = value.gte;
        if (typeof value.lte === 'number') range.lte = value.lte;
        if (Object.keys(range).length > 0) rf[key] = range;
      } else if (typeof value === 'string' && value !== '') {
        sf[key] = value;
      }
    }
    return { searchFilters: sf, rangeFilters: rf };
  }, [filters, advancedFilters]);

  const isSearchActive =
    query !== '' || Object.keys(searchFilters).length > 0 || Object.keys(rangeFilters).length > 0;

  const { result: searchResult, isLoading: isSearchLoading } = useCatalogSearch({
    kind: 'products',
    query,
    filters: searchFilters,
    rangeFilters,
    facets: PRODUCT_FACETS,
    perPage: 30,
  });

  const { result, query: listQuery } = useList<CatalogObjectListEntry>({
    resource: 'products',
    queryOptions: { enabled: !isSearchActive },
  });
  const refetch = listQuery.refetch;
  const products = result.data;
  const isListLoading = listQuery.isLoading;

  const baseRows = useMemo<ProductsGridRow[]>(() => {
    if (isSearchActive) {
      return (searchResult?.hits ?? []).map(searchHitToRow);
    }
    return (products ?? []).map(catalogObjectToRow);
  }, [isSearchActive, products, searchResult]);

  const filteredRows = useMemo<ProductsGridRow[]>(() => {
    if (showSelectedOnly && selected.size > 0) {
      return baseRows.filter((row) => selected.has(row.id));
    }
    return baseRows;
  }, [baseRows, showSelectedOnly, selected]);

  const variantsByMasterCount = useMemo(() => {
    const counts = new Map<string, number>();
    for (const row of filteredRows) {
      if (row.parentId === null) continue;
      counts.set(row.parentId, (counts.get(row.parentId) ?? 0) + 1);
    }
    return counts;
  }, [filteredRows]);

  const visible = useMemo<ProductsGridRow[]>(() => {
    if (variantsMode === 'flat') return filteredRows;
    const byId = new Map(filteredRows.map((r) => [r.id, r]));
    const masters: ProductsGridRow[] = [];
    const variantsByMaster = new Map<string, ProductsGridRow[]>();
    const orphans: ProductsGridRow[] = [];
    for (const row of filteredRows) {
      if (row.parentId === null) {
        masters.push(row);
        continue;
      }
      if (byId.has(row.parentId)) {
        const list = variantsByMaster.get(row.parentId) ?? [];
        list.push(row);
        variantsByMaster.set(row.parentId, list);
      } else {
        orphans.push(row);
      }
    }
    const out: ProductsGridRow[] = [];
    for (const master of masters) {
      out.push(master);
      if (expandedMasters.has(master.id)) {
        out.push(...(variantsByMaster.get(master.id) ?? []));
      }
    }
    out.push(...orphans);
    return out;
  }, [filteredRows, variantsMode, expandedMasters]);

  const toggleExpand = (masterId: string): void => {
    setExpandedMasters((prev) => {
      const next = new Set(prev);
      if (next.has(masterId)) next.delete(masterId);
      else next.add(masterId);
      return next;
    });
  };

  const isLoading = isSearchActive ? isSearchLoading : isListLoading;

  const totalHits = isSearchActive
    ? (searchResult?.totalHits ?? 0)
    : (result.total ?? products?.length ?? 0);

  const lastSyncMinutesAgo = useMemo<number | null>(() => {
    const stamps = (products ?? [])
      .map((p) => (typeof p.updatedAt === 'string' ? Date.parse(p.updatedAt) : NaN))
      .filter((n) => !Number.isNaN(n));
    if (stamps.length === 0) return null;
    const newest = Math.max(...stamps);
    return Math.max(0, Math.floor((Date.now() - newest) / 60000));
  }, [products]);

  const setPillFilter =
    (key: string) =>
    (next: string | null): void => {
      setFilters((prev) => {
        const updated = { ...prev };
        if (next === null) {
          delete updated[key];
        } else {
          updated[key] = next;
        }
        return updated;
      });
    };

  const handleChannelChange = (next: string | null): void => {
    if (next === null) return;
    toast.info(
      t('products.toolbar.channel_filter_pending', {
        defaultValue: 'Filtr per kanał czeka na epik 0.6 (channel_publications)',
      }),
    );
  };

  const toggleSelect = (id: string): void => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const toggleSelectAll = (): void => {
    setSelected((prev) => {
      const masters = visible.filter((r) => r.parentId === null);
      const allSelected = masters.every((m) => prev.has(m.id)) && prev.size === masters.length;
      if (allSelected) return new Set();
      return new Set(masters.map((m) => m.id));
    });
  };

  const handleApplySavedView = (view: { slug: string; config: Record<string, unknown> }): void => {
    setActiveViewSlug(view.slug);
    const cfg = view.config;
    const filt = cfg.filters;
    if (filt !== null && typeof filt === 'object' && !Array.isArray(filt)) {
      setFilters(filt as Record<string, string | string[]>);
    }
    const mode = cfg.variants_mode;
    if (mode === 'tree' || mode === 'flat') setVariantsMode(mode);
  };

  const handleToggleEnabled = (id: string, next: boolean): void => {
    void jsonFetch(`/api/products/${id}`, {
      method: 'PATCH',
      body: { enabled: next },
      contentType: 'application/merge-patch+json',
    })
      .then(() => refetch())
      .catch((err: unknown) => {
        toast.error(err instanceof Error ? err.message : 'unknown');
      });
  };

  const handleExcelCommit = async (
    row: ProductsGridRow,
    colKey: string,
    value: unknown,
  ): Promise<void> => {
    // Two writeable surfaces in Excel mode:
    //   - `enabled` is a top-level column on the product → PATCH at root
    //   - everything else is an attribute → wrap in `attributes`
    // Anything outside this set should not be reachable because
    // EXCEL_COLUMNS marks unsupported keys as readOnly, but we guard
    // defensively in case the column set changes.
    try {
      if (colKey === 'enabled') {
        await jsonFetch(`/api/products/${row.id}`, {
          method: 'PATCH',
          body: { enabled: Boolean(value) },
          contentType: 'application/merge-patch+json',
        });
      } else if (colKey === 'name' || colKey === 'brand') {
        await jsonFetch(`/api/products/${row.id}`, {
          method: 'PATCH',
          body: { attributes: { [colKey]: value } },
          contentType: 'application/merge-patch+json',
        });
      } else {
        return;
      }
      await refetch();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'unknown');
    }
  };

  const onBulkApplied = (): void => {
    setSelected(new Set());
    setShowSelectedOnly(false);
    void refetch();
  };

  const showEmptyState = !isLoading && baseRows.length === 0 && !isSearchActive;

  const brandOptions = useMemo(
    () => buildFacetOptions(searchResult?.facetDistribution ?? {}, 'brand'),
    [searchResult],
  );
  const familyOptions = useMemo(
    () => buildFacetOptions(searchResult?.facetDistribution ?? {}, 'family'),
    [searchResult],
  );

  return (
    <div id="products-list-page" className="space-y-5 pb-24">
      <div className="flex items-baseline justify-between gap-4">
        <div>
          <div className="text-[13px] text-zinc-500 font-medium">
            {t('products.header.workspace', { defaultValue: 'Workspace · katalog' })}
          </div>
          <h1 className="font-display text-[32px] font-semibold tracking-tight leading-none mt-1">
            {t('products.list_title')}
          </h1>
        </div>
        <div className="text-[12px] text-zinc-500 tabular-nums text-right">
          <span className="text-zinc-900 font-semibold">{totalHits.toLocaleString('pl-PL')}</span>{' '}
          {t('products.header.total_skus_suffix', { defaultValue: 'SKU' })}
          {' · '}
          {lastSyncMinutesAgo === null
            ? t('products.header.last_sync_unknown', { defaultValue: 'brak danych o sync' })
            : t('products.header.last_sync_minutes_ago', {
                minutes: lastSyncMinutesAgo,
                defaultValue: 'ostatnia synchronizacja {{minutes}} min temu',
              })}
        </div>
      </div>

      <SavedViewsRail
        activeSlug={activeViewSlug}
        onApply={(view) => {
          handleApplySavedView({ slug: view.slug, config: view.config });
        }}
        onSaveCurrent={() => {
          setShowSaveViewModal(true);
        }}
        currentTotal={totalHits}
      />

      <div className="flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[280px]">
          <Search
            className="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-zinc-400"
            aria-hidden="true"
          />
          <input
            type="search"
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
            }}
            placeholder={t('products.toolbar.search_placeholder', {
              defaultValue: 'Szukaj po SKU, nazwie, EAN, atrybucie…',
            })}
            aria-label={t('products.toolbar.search_aria', { defaultValue: 'Szukaj produktów' })}
            className="w-full h-11 pl-10 pr-4 rounded-2xl bg-white shadow-sm text-[14px] placeholder:text-zinc-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900"
          />
        </div>

        <FilterPill
          label={t('products.toolbar.filter_brand', { defaultValue: 'Marka' })}
          value={filters.brand as string | undefined}
          options={brandOptions}
          onChange={setPillFilter('brand')}
        />
        <FilterPill
          label={t('products.toolbar.filter_family', { defaultValue: 'Rodzina' })}
          value={filters.family as string | undefined}
          options={familyOptions}
          onChange={setPillFilter('family')}
        />
        <FilterPill
          label={t('products.toolbar.filter_channel', { defaultValue: 'Kanał' })}
          value={undefined}
          options={CHANNEL_VALUES.map((v) => ({ value: v, label: v }))}
          onChange={handleChannelChange}
        />
        <FilterPill
          label={t('products.toolbar.filter_status', { defaultValue: 'Status' })}
          value={filters.syncStatusAggregate as string | undefined}
          options={STATUS_VALUES.map((s) => ({ value: s.sync, label: s.label }))}
          onChange={setPillFilter('syncStatusAggregate')}
        />

        <AdvancedFilterBuilder
          filters={advancedFilters}
          onApply={setAdvancedFilters}
          onSaveAsView={() => {
            setShowSaveViewModal(true);
          }}
        />

        <VariantsToggle mode={variantsMode} onChange={setVariantsMode} />

        <ViewModeToggle mode={viewMode} onChange={handleViewModeChange} />

        <Button
          type="button"
          variant="outline"
          disabled
          aria-disabled="true"
          title={t('products.import_disabled', {
            defaultValue: 'Mock — wymaga oprogramowania importu CSV/XLSX',
          })}
          className="h-11 rounded-2xl"
        >
          <Upload className="size-4" />
          {t('products.toolbar.import', { defaultValue: 'Import' })}
        </Button>
        <Button asChild className="h-11 rounded-2xl px-4">
          <Link to="/products/new">
            <Plus className="size-4" />
            {t('products.toolbar.new_product', { defaultValue: 'Nowy produkt' })}
            <kbd className="ml-1.5 rounded bg-white/15 px-1 py-0.5 font-mono text-[10px]">⌘N</kbd>
          </Link>
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-2 text-[12px] text-zinc-500">
        <span className="tabular-nums">
          <span className="text-zinc-900 font-semibold">{totalHits.toLocaleString('pl-PL')}</span>{' '}
          {t('products.counter.results', {
            count: totalHits,
            defaultValue_one: '{{count}} wynik',
            defaultValue_other: '{{count}} wyników',
            defaultValue: '{{count}} wyników',
          })}
        </span>
        {selected.size > 0 ? (
          <>
            <span className="text-zinc-300">·</span>
            <span className="tabular-nums">
              <span className="text-zinc-900 font-semibold">{selected.size}</span>{' '}
              {t('products.counter.selected', {
                count: selected.size,
                defaultValue: 'zaznaczonych',
              })}
            </span>
            <button
              type="button"
              onClick={() => {
                setShowSelectedOnly((prev) => !prev);
              }}
              aria-pressed={showSelectedOnly}
              className={cn(
                'ml-1 inline-flex items-center gap-1.5 h-7 px-2.5 rounded-lg text-[12px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900',
                showSelectedOnly
                  ? 'bg-violet-600 text-white hover:bg-violet-500'
                  : 'bg-violet-100 text-violet-700 hover:bg-violet-200',
              )}
            >
              {showSelectedOnly
                ? t('products.counter.show_all', { defaultValue: 'Pokaż wszystkie' })
                : t('products.counter.show_selected_only', {
                    defaultValue: 'Pokaż tylko zaznaczone',
                  })}
            </button>
          </>
        ) : null}
        <span className="ml-auto inline-flex items-center gap-1.5 text-zinc-400">
          <kbd className="rounded border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-[10px]">
            ⌘C
          </kbd>
          {t('products.counter.shortcut_copy', { defaultValue: 'kopiuj' })}
          <kbd className="ml-2 rounded border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-[10px]">
            ⌘V
          </kbd>
          {t('products.counter.shortcut_paste', { defaultValue: 'wklej' })}
          <kbd className="ml-2 rounded border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-[10px]">
            ⇧↓
          </kbd>
          {t('products.counter.shortcut_select', { defaultValue: 'zaznacz' })}
          <kbd className="ml-2 rounded border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-[10px]">
            F2
          </kbd>
          {t('products.counter.shortcut_edit', { defaultValue: 'edytuj' })}
        </span>
      </div>

      {showEmptyState ? (
        <EmptyStateProducts />
      ) : viewMode === 'excel' ? (
        <ExcelLikeGrid<ExcelProductRow>
          rows={visible as ExcelProductRow[]}
          columns={EXCEL_COLUMNS}
          onCommit={(rowIdx, colKey, value) => {
            const row = visible[rowIdx];
            if (row === undefined) return;
            void handleExcelCommit(row, colKey, value);
          }}
        />
      ) : (
        <ProductsGrid
          rows={visible}
          selected={selected}
          onToggleSelect={toggleSelect}
          onToggleSelectAll={toggleSelectAll}
          expandedMasters={expandedMasters}
          onToggleExpand={toggleExpand}
          variantsByMasterCount={variantsByMasterCount}
          onToggleEnabled={handleToggleEnabled}
          onChangedRow={() => {
            void refetch();
          }}
          isLoading={isLoading}
        />
      )}

      <BulkBar
        selectedIds={Array.from(selected)}
        onClear={() => {
          setSelected(new Set());
          setShowSelectedOnly(false);
        }}
        onApplied={onBulkApplied}
      />

      {showSaveViewModal ? (
        <SaveViewModal
          resource="products"
          config={{ filters, variants_mode: variantsMode }}
          onClose={() => {
            setShowSaveViewModal(false);
          }}
          onSaved={(slug) => {
            setActiveViewSlug(slug);
          }}
        />
      ) : null}
    </div>
  );
}

function buildFacetOptions(
  distribution: Record<string, Record<string, number>>,
  field: string,
): Array<{ value: string; label: string }> {
  const dist = distribution[field];
  if (dist === undefined) return [];
  return Object.entries(dist)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 30)
    .map(([value]) => ({ value, label: value }));
}

function searchHitToRow(hit: CatalogSearchHit): ProductsGridRow {
  return buildRow({
    id: hit.id,
    code: hit.code ?? hit.id,
    enabled: hit.enabled,
    status: hit.status,
    attributesIndexed: hit.attributesIndexed,
    createdAt: undefined,
    updatedAt: undefined,
  });
}

function catalogObjectToRow(entry: CatalogObjectListEntry): ProductsGridRow {
  return buildRow(entry);
}

function buildRow(entry: CatalogObjectListEntry): ProductsGridRow {
  // `attributes_indexed` envelopes every reading as `{ value, ... }`; flatten
  // here so the column readers below can keep their `attrs.name` ergonomics.
  // Without this, every row falls back to `entry.code` (the SKU) and Excel
  // commits look like no-ops to operators even when the backend persists.
  const attrs = unwrapAttributesIndexed(entry.attributesIndexed);
  const name = typeof attrs.name === 'string' ? attrs.name : entry.code;
  const brand = typeof attrs.brand === 'string' ? attrs.brand : null;
  const family = readString(attrs, ['family', 'product_family']);
  const variantAxis = readString(attrs, ['variant_axis', 'axis']);
  const categories = readCategories(attrs);
  const price = readPrice(attrs);
  const parentId =
    typeof entry.parentId === 'string'
      ? entry.parentId
      : entry.parent && typeof entry.parent.id === 'string'
        ? entry.parent.id
        : null;
  return {
    id: entry.id,
    sku: entry.code,
    name,
    brand,
    family,
    categories,
    price,
    completenessPct: typeof entry.completenessPct === 'number' ? entry.completenessPct : 0,
    syncStatusAggregate: normaliseSyncAggregate(entry.syncStatusAggregate),
    enabled: entry.enabled !== false,
    status: typeof entry.status === 'string' ? entry.status : null,
    parentId,
    variantAxis,
  };
}

function readString(attrs: Record<string, unknown>, keys: ReadonlyArray<string>): string | null {
  for (const key of keys) {
    const value = attrs[key];
    if (typeof value === 'string' && value.length > 0) return value;
  }
  return null;
}

function readCategories(attrs: Record<string, unknown>): string[] | null {
  const raw = attrs.categories ?? attrs.category_codes;
  if (!Array.isArray(raw)) return null;
  const out: string[] = [];
  for (const entry of raw) {
    if (typeof entry === 'string') out.push(entry);
  }
  return out.length > 0 ? out : null;
}

function readPrice(attrs: Record<string, unknown>): { amount: number; currency: string } | null {
  const raw = attrs.price ?? attrs.list_price;
  if (raw === undefined || raw === null) return null;
  if (typeof raw === 'number') return { amount: raw, currency: 'PLN' };
  if (typeof raw === 'object') {
    const obj = raw as Record<string, unknown>;
    const amount = typeof obj.amount === 'number' ? obj.amount : null;
    const currency = typeof obj.currency === 'string' ? obj.currency : 'PLN';
    if (amount !== null) return { amount, currency };
  }
  return null;
}

function normaliseSyncAggregate(raw: string | undefined): SyncAggregate {
  if (raw === 'green' || raw === 'yellow' || raw === 'red' || raw === 'gray') {
    return raw;
  }
  return 'gray';
}
