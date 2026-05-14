import { useList } from '@refinedev/core';
import { Plus, Search, Upload } from 'lucide-react';
import { lazy, Suspense, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { BulkBar } from '@/components/catalog/bulk-bar';

// VIEW-37 (#577) — lazy-load wszystkich modali + wizard + Cmd+K palette
// + Advanced filter panel. Te komponenty mają ~150-200KB gzip razem
// i renderują się tylko po user interaction. Code-split daje fast
// initial paint przy zachowaniu pełnego UX.
const CmdKPalette = lazy(() =>
  import('@/components/agent/cmd-k-palette').then((m) => ({ default: m.CmdKPalette })),
);
const AdvancedFilterPanel = lazy(() =>
  import('@/components/catalog/advanced-filter-panel').then((m) => ({
    default: m.AdvancedFilterPanel,
  })),
);
const BulkCategoryModal = lazy(() =>
  import('@/components/catalog/bulk-actions/category-modal').then((m) => ({
    default: m.BulkCategoryModal,
  })),
);
const BulkDuplicateModal = lazy(() =>
  import('@/components/catalog/bulk-actions/duplicate-modal').then((m) => ({
    default: m.BulkDuplicateModal,
  })),
);
const BulkDeleteConfirmModal = lazy(() =>
  import('@/components/catalog/bulk-actions/hard-confirm-modal').then((m) => ({
    default: m.BulkDeleteConfirmModal,
  })),
);
const BulkPublishModal = lazy(() =>
  import('@/components/catalog/bulk-actions/publish-modal').then((m) => ({
    default: m.BulkPublishModal,
  })),
);
const BulkWizard = lazy(() =>
  import('@/components/catalog/bulk-wizard/bulk-wizard').then((m) => ({ default: m.BulkWizard })),
);

import { EmptyStateProducts } from '@/components/catalog/empty-state-products';
import { type ExcelColumn, ExcelLikeGrid } from '@/components/catalog/excel-like-grid';
import { FilterChipsBar } from '@/components/catalog/filter-chips-bar';
import {
  PAGE_SIZE_OPTIONS,
  type PageSize,
  PaginationBar,
} from '@/components/catalog/pagination-bar';
import { ProductsGrid, type ProductsGridRow } from '@/components/catalog/products-grid';
import { type RollbackSession, RollbackToast } from '@/components/catalog/rollback-toast';
import { SaveAsSmartPresetModal } from '@/components/catalog/save-as-smart-preset-modal';
import { SaveViewModal } from '@/components/catalog/save-view-modal';
import { SavedViewsRail } from '@/components/catalog/saved-views-rail';
import { SelectionToolbar } from '@/components/catalog/selection-toolbar';
import { SmartFilterPresetsRow } from '@/components/catalog/smart-filter-presets-row';
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
import {
  conditionsToDsl,
  dslToFlatConditions,
  type FilterCondition,
} from '@/lib/filters/filter-dsl';
import { dslToBase64 } from '@/lib/filters/url-serializer';
import { type SmartFilterPreset, useSmartPresets } from '@/lib/filters/use-smart-presets';
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

function readInitialPageSize(): PageSize {
  if (typeof window === 'undefined') return 50;
  const urlParam = new URLSearchParams(window.location.search).get('pageSize');
  const parsedUrl = urlParam !== null ? Number(urlParam) : Number.NaN;
  if (PAGE_SIZE_OPTIONS.includes(parsedUrl as PageSize)) {
    return parsedUrl as PageSize;
  }
  const stored = window.localStorage.getItem('pim.products.pageSize');
  const parsedStored = stored !== null ? Number(stored) : Number.NaN;
  if (PAGE_SIZE_OPTIONS.includes(parsedStored as PageSize)) {
    return parsedStored as PageSize;
  }
  return 50;
}

function readInitialPage(): number {
  if (typeof window === 'undefined') return 1;
  const urlParam = new URLSearchParams(window.location.search).get('page');
  const parsed = urlParam !== null ? Number(urlParam) : Number.NaN;
  return Number.isFinite(parsed) && parsed >= 1 ? parsed : 1;
}

export function ProductListPage() {
  const { t } = useTranslation();
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState<Record<string, string | string[]>>({});
  // VIEW-26 (#557) — pager + page size selector. Init kolejność:
  // URL `?page=` + `?pageSize=` → localStorage → default 50.
  const [page, setPage] = useState<number>(() => readInitialPage());
  const [pageSize, setPageSize] = useState<PageSize>(() => readInitialPageSize());

  useEffect(() => {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem('pim.products.pageSize', String(pageSize));
  }, [pageSize]);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    params.set('pageSize', String(pageSize));
    const url = `${window.location.pathname}?${params.toString()}${window.location.hash}`;
    window.history.replaceState(null, '', url);
  }, [page, pageSize]);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [showSelectedOnly, setShowSelectedOnly] = useState(false);
  // VIEW-11 (#542) — cross-page selection escalation. The per-row `selected`
  // Set above stays the source of truth for grid/bulk; `crossPageSelection`
  // tracks the "select all matching" path (server resolved up to 10k IDs).
  const [crossPageSelection, setCrossPageSelection] = useState<{
    active: boolean;
    totalMatched: number;
    capped: boolean;
  }>({ active: false, totalMatched: 0, capped: false });
  const [crossPageLoading, setCrossPageLoading] = useState(false);
  // VIEW-12 (#543) — bulk wizard open/close.
  // VIEW-17 (#544) — sticky 24h rollback toast for the last applied session.
  const [bulkWizardOpen, setBulkWizardOpen] = useState(false);
  const [bulkCategoryOpen, setBulkCategoryOpen] = useState(false);
  const [bulkPublishOpen, setBulkPublishOpen] = useState(false);
  const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
  const [bulkDuplicateOpen, setBulkDuplicateOpen] = useState(false);
  const [cmdKOpen, setCmdKOpen] = useState(false);
  const [lastBulkSession, setLastBulkSession] = useState<RollbackSession | null>(null);

  useEffect(() => {
    const handler = (event: KeyboardEvent): void => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setCmdKOpen((prev) => !prev);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);
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
  // VIEW-09: smart presets + push-down advanced filter panel + filter chips bar.
  // VIEW-21 (#552) removed the legacy FilterPill row + AdvancedFilterBuilder
  // Sheet — only the push-down panel + chips bar drive filters now.
  const {
    presets: smartPresets,
    isLoading: smartPresetsLoading,
    create: createSmartPreset,
  } = useSmartPresets({ withCounts: true });
  const [activeSmartPresetId, setActiveSmartPresetId] = useState<string | null>(null);
  const [advancedPanelOpen, setAdvancedPanelOpen] = useState(false);
  const [panelConditions, setPanelConditions] = useState<FilterCondition[]>([]);
  const [matchOperator, setMatchOperator] = useState<'AND' | 'OR'>('AND');
  const [showSaveAsPresetModal, setShowSaveAsPresetModal] = useState(false);
  // VIEW-09b (#540) — query mode editor state. When `advancedMode === 'query'`,
  // `queryDsl` is the recursive AND/OR tree. Toggling to grid mode flattens
  // a single root group; toggling back rebuilds from `panelConditions`.
  const [advancedMode, setAdvancedMode] = useState<'grid' | 'query'>('grid');
  const [queryDsl, setQueryDsl] = useState<import('@/lib/filters/filter-dsl').FilterGroup | null>(
    null,
  );

  // VIEW-21 (#552) — `advancedFilters` legacy state was removed with the
  // AdvancedFilterBuilder Sheet. Range/extra filters now flow through the
  // panel `conditions` → DSL → `?q=<base64>` BE resolver (VIEW-10).
  const { searchFilters, rangeFilters } = useMemo(() => {
    const sf: Record<string, string | string[]> = { ...filters };
    const rf: Record<string, { gte?: number; lte?: number }> = {};
    return { searchFilters: sf, rangeFilters: rf };
  }, [filters]);

  const isSearchActive =
    query !== '' ||
    Object.keys(searchFilters).length > 0 ||
    Object.keys(rangeFilters).length > 0 ||
    activeSmartPresetId !== null ||
    panelConditions.length > 0 ||
    (queryDsl?.conditions.length ?? 0) > 0;

  // VIEW-10 (#538) — when a smart preset is active OR the panel has
  // conditions, push them to the BE resolver through the new
  // `smart_preset` / `q` query params on `/api/search/products`.
  const activePreset = activeSmartPresetId
    ? smartPresets.find((p) => p.id === activeSmartPresetId)
    : undefined;
  const filterBlob = useMemo<string | undefined>(() => {
    if (activePreset !== undefined) return undefined;
    // VIEW-09b (#540) — query mode wins when active and has conditions.
    if (advancedMode === 'query' && queryDsl && queryDsl.conditions.length > 0) {
      try {
        return dslToBase64(queryDsl);
      } catch {
        return undefined;
      }
    }
    if (panelConditions.length === 0) return undefined;
    const dsl = conditionsToDsl(panelConditions, matchOperator);
    if (dsl === null) return undefined;
    try {
      return dslToBase64(dsl);
    } catch {
      return undefined;
    }
  }, [activePreset, panelConditions, matchOperator, advancedMode, queryDsl]);

  // VIEW-26 (#557) — reset pagera do strony 1 gdy filter/query/preset
  // się zmienia, żeby operator nie wylądował na page 5 po zmianie
  // filtra który ma tylko 2 strony wyników.
  // biome-ignore lint/correctness/useExhaustiveDependencies: intentional — `setPage` is stable, page tracked elsewhere
  useEffect(() => {
    setPage(1);
  }, [query, filters, activeSmartPresetId, filterBlob, variantsMode]);

  const { result: searchResult, isLoading: isSearchLoading } = useCatalogSearch({
    kind: 'products',
    query,
    filters: searchFilters,
    rangeFilters,
    smartPresetId: activePreset?.slug ?? activePreset?.id,
    filterBlob,
    facets: PRODUCT_FACETS,
    page,
    perPage: pageSize,
  });

  // Tree mode (#514): only fetch master products. Without this filter
  // a single page (default 30) can fill up entirely with variants of
  // one freshly generated master, pushing every other product —
  // including the master itself — off the visible page. Variants
  // load lazily on chevron expand below. Flat mode keeps the full
  // mixed-row listing.
  const masterFilter = useMemo(
    () =>
      variantsMode === 'tree'
        ? [{ field: 'parent_id', operator: 'eq' as const, value: 'null' }]
        : [],
    [variantsMode],
  );

  const { result, query: listQuery } = useList<CatalogObjectListEntry>({
    resource: 'products',
    filters: masterFilter,
    queryOptions: { enabled: !isSearchActive },
  });
  const refetch = listQuery.refetch;
  const products = result.data;
  const isListLoading = listQuery.isLoading;

  // Lazy-loaded variants per expanded master. Populated on toggleExpand
  // by a single call to /api/products?parent_id={masterId}.
  const [variantsByMasterId, setVariantsByMasterId] = useState<Record<string, ProductsGridRow[]>>(
    {},
  );

  const fetchVariantsForMaster = async (masterId: string): Promise<void> => {
    if (variantsByMasterId[masterId] !== undefined) return;
    try {
      const body = await jsonFetch<{
        member?: CatalogObjectListEntry[];
        'hydra:member'?: CatalogObjectListEntry[];
      }>(`/api/products?parent_id=${masterId}&itemsPerPage=200`);
      const list = body.member ?? body['hydra:member'] ?? [];
      setVariantsByMasterId((prev) => ({
        ...prev,
        [masterId]: list.map(catalogObjectToRow),
      }));
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'unknown');
    }
  };

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
    // In tree mode `filteredRows` only contains masters (the API
    // filter takes care of that). Variants come from the lazy-loaded
    // `variantsByMasterId` map and are spliced in below each expanded
    // master.
    const out: ProductsGridRow[] = [];
    for (const row of filteredRows) {
      if (row.parentId !== null) continue;
      out.push(row);
      if (expandedMasters.has(row.id)) {
        out.push(...(variantsByMasterId[row.id] ?? []));
      }
    }
    return out;
  }, [filteredRows, variantsMode, expandedMasters, variantsByMasterId]);

  const toggleExpand = (masterId: string): void => {
    setExpandedMasters((prev) => {
      const next = new Set(prev);
      if (next.has(masterId)) next.delete(masterId);
      else next.add(masterId);
      return next;
    });
    if (variantsMode === 'tree' && !expandedMasters.has(masterId)) {
      void fetchVariantsForMaster(masterId);
    }
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

  const handleApplySmartPreset = (preset: SmartFilterPreset | null): void => {
    if (preset === null) {
      setActiveSmartPresetId(null);
      setPanelConditions([]);
      return;
    }

    setActiveSmartPresetId(preset.id);
    const conditions = dslToFlatConditions(preset.query);
    if (conditions === null) {
      toast.info(
        t('products.smart_filters.nested_unsupported', {
          defaultValue: 'Preset zawiera zagnieżdżone grupy AND/OR (Query mode w VIEW-09b).',
        }),
      );
      setPanelConditions([]);
      return;
    }

    setPanelConditions(conditions);
  };

  const handleApplyAdvancedPanel = (): void => {
    setAdvancedPanelOpen(false);
    setActiveSmartPresetId(null);
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

      <SmartFilterPresetsRow
        presets={smartPresets}
        activeId={activeSmartPresetId}
        onSelect={handleApplySmartPreset}
        onCreate={() => {
          if (panelConditions.length === 0) {
            toast.info(
              t('products.smart_filters.create_requires_conditions', {
                defaultValue: 'Najpierw dodaj warunek w panelu zaawansowanym.',
              }),
            );
            setAdvancedPanelOpen(true);
            return;
          }
          setShowSaveAsPresetModal(true);
        }}
        isLoading={smartPresetsLoading}
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

        <Button
          type="button"
          variant={advancedPanelOpen ? 'default' : 'outline'}
          onClick={() => setAdvancedPanelOpen((prev) => !prev)}
          className="h-11 rounded-2xl"
          aria-expanded={advancedPanelOpen}
          aria-controls="advanced-filter-panel"
        >
          {t('products.toolbar.filter_by_attribute_button', {
            defaultValue: 'Filtruj zaawansowane',
          })}
          {panelConditions.length > 0 && (
            <span className="ml-1.5 rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[10px] text-zinc-700">
              {panelConditions.length}
            </span>
          )}
        </Button>

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

      <div id="advanced-filter-panel">
        {advancedPanelOpen ? (
          <Suspense fallback={null}>
            <AdvancedFilterPanel
              open={advancedPanelOpen}
              conditions={panelConditions}
              setConditions={setPanelConditions}
              matchOperator={matchOperator}
              setMatchOperator={setMatchOperator}
              onApply={handleApplyAdvancedPanel}
              onClose={() => setAdvancedPanelOpen(false)}
              onClear={() => {
                setPanelConditions([]);
                setQueryDsl(null);
                setActiveSmartPresetId(null);
              }}
              onSaveAsView={() => {
                setShowSaveViewModal(true);
              }}
              onSaveAsPreset={() => {
                setShowSaveAsPresetModal(true);
              }}
              resultCount={totalHits}
              mode={advancedMode}
              setMode={(next) => {
                // Toggle: flat conditions → root group when entering query;
                // query → first-level flat when entering grid (drops nested).
                if (next === 'query' && advancedMode === 'grid') {
                  setQueryDsl({
                    operator: matchOperator,
                    conditions: panelConditions.length > 0 ? panelConditions : [],
                  });
                }
                if (next === 'grid' && advancedMode === 'query' && queryDsl) {
                  const flat = queryDsl.conditions.filter(
                    (c): c is FilterCondition => !('operator' in c),
                  );
                  setPanelConditions(flat);
                }
                setAdvancedMode(next);
              }}
              queryDsl={queryDsl}
              setQueryDsl={(next) => setQueryDsl(next)}
            />
          </Suspense>
        ) : null}
      </div>

      <FilterChipsBar
        chips={panelConditions}
        attrLabelMap={{
          brand: t('products.toolbar.filter_brand', { defaultValue: 'Marka' }),
          family: t('products.toolbar.filter_family', { defaultValue: 'Rodzina' }),
          category: t('products.fields.categories', { defaultValue: 'Kategoria' }),
          completeness_pct: t('products.fields.completeness', { defaultValue: 'Compl.' }),
          enabled: t('products.fields.enabled', { defaultValue: 'Aktywny' }),
          price: t('products.fields.price', { defaultValue: 'Cena' }),
          'description.pl': t('products.fields.description_pl', { defaultValue: 'Opis · PL' }),
          'description.en': t('products.fields.description_en', { defaultValue: 'Opis · EN' }),
          main_image: t('products.fields.main_image', { defaultValue: 'Główne zdjęcie' }),
          meta_description: t('products.fields.meta_description', {
            defaultValue: 'Meta description',
          }),
        }}
        onRemove={(idx) => {
          const next = panelConditions.filter((_, i) => i !== idx);
          setPanelConditions(next);
          if (next.length === 0) setActiveSmartPresetId(null);
        }}
        onClearAll={() => {
          setPanelConditions([]);
          setActiveSmartPresetId(null);
        }}
        onEditChip={() => setAdvancedPanelOpen(true)}
      />

      <SelectionToolbar
        mode={crossPageSelection.active ? 'all-matching' : selected.size > 0 ? 'page' : 'none'}
        perPageCount={selected.size}
        matchingCount={totalHits}
        totalMatched={crossPageSelection.totalMatched}
        capped={crossPageSelection.capped}
        isLoading={crossPageLoading}
        onSelectAllMatching={() => {
          void (async () => {
            setCrossPageLoading(true);
            try {
              const body: Record<string, unknown> = {};
              if (activePreset !== undefined) {
                body.smart_preset = activePreset.slug ?? activePreset.id;
              } else if (filterBlob !== undefined) {
                body.filter = filterBlob;
              }
              if (query !== '') body.q = query;
              const response = await jsonFetch<{
                ids: string[];
                totalMatched: number;
                capped: boolean;
              }>('/api/products/select-all-matching', {
                method: 'POST',
                body,
              });
              setSelected(new Set(response.ids));
              setCrossPageSelection({
                active: true,
                totalMatched: response.totalMatched,
                capped: response.capped,
              });
            } catch (err) {
              toast.error(err instanceof Error ? err.message : 'unknown');
            } finally {
              setCrossPageLoading(false);
            }
          })();
        }}
        onClear={() => {
          setSelected(new Set());
          setCrossPageSelection({ active: false, totalMatched: 0, capped: false });
          setShowSelectedOnly(false);
        }}
      />

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
          alwaysShowChevronOnMasters={variantsMode === 'tree' && !isSearchActive}
        />
      )}

      <PaginationBar
        page={page}
        pageSize={pageSize}
        totalItems={totalHits}
        onPageChange={setPage}
        onPageSizeChange={(next) => {
          setPageSize(next);
          setPage(1);
        }}
      />

      <BulkBar
        selectedIds={Array.from(selected)}
        onClear={() => {
          setSelected(new Set());
          setShowSelectedOnly(false);
        }}
        onApplied={onBulkApplied}
        onOpenWizard={() => setBulkWizardOpen(true)}
        onOpenCategoryModal={() => setBulkCategoryOpen(true)}
        onOpenPublishModal={() => setBulkPublishOpen(true)}
        onOpenDeleteModal={() => setBulkDeleteOpen(true)}
        onOpenDuplicateModal={() => setBulkDuplicateOpen(true)}
        onOpenCmdK={() => setCmdKOpen(true)}
      />

      <Suspense fallback={null}>
        {bulkWizardOpen ? (
          <BulkWizard
            open={bulkWizardOpen}
            selectedIds={Array.from(selected)}
            onClose={() => setBulkWizardOpen(false)}
            onApplied={(result) => {
              setLastBulkSession(result);
              setSelected(new Set());
              setShowSelectedOnly(false);
              void refetch();
            }}
          />
        ) : null}

        {bulkCategoryOpen ? (
          <BulkCategoryModal
            selectedIds={Array.from(selected)}
            onClose={() => setBulkCategoryOpen(false)}
            onApplied={(result) => {
              setLastBulkSession(result);
              setSelected(new Set());
              setShowSelectedOnly(false);
              void refetch();
            }}
          />
        ) : null}

        {bulkPublishOpen ? (
          <BulkPublishModal
            selectedIds={Array.from(selected)}
            onClose={() => setBulkPublishOpen(false)}
            onApplied={(result) => {
              setLastBulkSession(result);
              setSelected(new Set());
              setShowSelectedOnly(false);
              void refetch();
            }}
          />
        ) : null}

        {bulkDeleteOpen ? (
          <BulkDeleteConfirmModal
            selectedIds={Array.from(selected)}
            onClose={() => setBulkDeleteOpen(false)}
            onApplied={(result) => {
              setLastBulkSession(result);
              setSelected(new Set());
              setShowSelectedOnly(false);
              void refetch();
            }}
          />
        ) : null}

        {bulkDuplicateOpen ? (
          <BulkDuplicateModal
            selectedIds={Array.from(selected)}
            onClose={() => setBulkDuplicateOpen(false)}
            onApplied={(result) => {
              setLastBulkSession(result);
              setSelected(new Set());
              setShowSelectedOnly(false);
              void refetch();
            }}
          />
        ) : null}

        <CmdKPalette
          open={cmdKOpen}
          onClose={() => setCmdKOpen(false)}
          selectedIds={Array.from(selected)}
          totalMatching={
            crossPageSelection.active ? crossPageSelection.totalMatched : selected.size
          }
          onApplied={(result) => {
            setLastBulkSession(result);
            setSelected(new Set());
            setShowSelectedOnly(false);
            void refetch();
          }}
        />
      </Suspense>

      <RollbackToast
        session={lastBulkSession}
        onDismiss={() => setLastBulkSession(null)}
        onRolledBack={() => {
          void refetch();
        }}
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

      {showSaveAsPresetModal ? (
        <SaveAsSmartPresetModal
          query={conditionsToDsl(panelConditions, matchOperator)}
          create={createSmartPreset}
          onClose={() => setShowSaveAsPresetModal(false)}
          onSaved={(preset) => {
            toast.success(
              t('products.smart_filters.save_success', {
                defaultValue: 'Smart Preset zapisany',
              }),
            );
            setActiveSmartPresetId(preset.id);
          }}
        />
      ) : null}
    </div>
  );
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
