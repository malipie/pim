import { useList } from '@refinedev/core';
import { Plus, Sheet as SheetIcon, Table as TableIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { AdvancedFilterBuilder } from '@/components/catalog/advanced-filter-builder';
import { BulkActionsToolbar } from '@/components/catalog/bulk-actions-toolbar';
import {
  ChannelInlineIcons,
  type ChannelStatusEntry,
} from '@/components/catalog/channel-inline-icons';
import { CompletenessBadge } from '@/components/catalog/completeness-badge';
import { EmptyStateProducts } from '@/components/catalog/empty-state-products';
import { type ExcelColumn, ExcelLikeGrid } from '@/components/catalog/excel-like-grid';
import {
  type FilterValue,
  formatChipLabel,
  ProductFilterChips,
} from '@/components/catalog/product-filter-chips';
import { ProductRowActions } from '@/components/catalog/product-row-actions';
import { SaveViewModal } from '@/components/catalog/save-view-modal';
import { SavedViewsDropdown } from '@/components/catalog/saved-views-dropdown';
import { SyncAggregateIcon } from '@/components/catalog/sync-aggregate-icon';
import { type VariantsMode, VariantsToggle } from '@/components/catalog/variants-toggle';
import { type Provenance, ProvenanceBadge } from '@/components/provenance-badge';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { CatalogFacetList } from '@/features/catalog/search/catalog-facet-list';
import { CatalogSearchBox } from '@/features/catalog/search/catalog-search-box';
import {
  type CatalogSearchHit,
  useCatalogSearch,
} from '@/features/catalog/search/use-catalog-search';
import { jsonFetch } from '@/lib/http';

interface CatalogObjectListEntry {
  id: string;
  code: string;
  enabled?: boolean;
  status?: string;
  createdAt?: string;
  attributesIndexed?: Record<string, unknown>;
  completenessPct?: number;
  syncStatusAggregate?: string;
}

interface ProductRow {
  id: string;
  sku: string;
  name: string;
  description: string | null;
  brand: string | null;
  createdAt: string;
  enabled: boolean;
  status: string | null;
  completenessPct: number;
  syncStatusAggregate: SyncAggregate;
}

type SyncAggregate = 'green' | 'yellow' | 'red' | 'gray';
type ViewMode = 'table' | 'excel';

const PRODUCT_FACETS = ['enabled', 'status'];
const PROVENANCE_OPTIONS: ReadonlyArray<Provenance> = ['manual', 'import', 'integration', 'agent'];

export function ProductListPage() {
  const { t, i18n } = useTranslation();
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState<Record<string, string | string[]>>({});
  const [advancedFilters, setAdvancedFilters] = useState<Record<string, FilterValue>>({});
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [showSelectedOnly, setShowSelectedOnly] = useState(false);
  const [variantsMode, setVariantsMode] = useState<VariantsMode>('tree');
  const [viewMode, setViewMode] = useState<ViewMode>('table');
  const [activeViewSlug, setActiveViewSlug] = useState<string | null>(null);
  const [showSaveViewModal, setShowSaveViewModal] = useState(false);

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

  const baseRows = useMemo<ProductRow[]>(() => {
    if (isSearchActive) {
      return (searchResult?.hits ?? []).map(searchHitToProduct);
    }
    return (products ?? []).map(catalogObjectToProduct);
  }, [isSearchActive, products, searchResult]);

  const visible = useMemo<ProductRow[]>(() => {
    if (showSelectedOnly && selected.size > 0) {
      return baseRows.filter((row) => selected.has(row.id));
    }
    return baseRows;
  }, [baseRows, showSelectedOnly, selected]);

  const isLoading = isSearchActive ? isSearchLoading : isListLoading;

  const toggleFacet = (facet: string, value: string): void => {
    setFilters((prev) => {
      const current = prev[facet];
      const currentArray = Array.isArray(current)
        ? current
        : current !== undefined
          ? [current]
          : [];
      const next = currentArray.includes(value)
        ? currentArray.filter((entry) => entry !== value)
        : [...currentArray, value];
      const updated = { ...prev };
      if (next.length === 0) {
        delete updated[facet];
      } else {
        updated[facet] = next;
      }
      return updated;
    });
  };

  const removeFilterChip = (key: string): void => {
    setFilters((prev) => {
      const next = { ...prev };
      delete next[key];
      return next;
    });
    setAdvancedFilters((prev) => {
      const next = { ...prev };
      delete next[key];
      return next;
    });
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
      if (prev.size === visible.length) return new Set();
      return new Set(visible.map((row) => row.id));
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

  const openSaveViewModal = (): void => {
    setShowSaveViewModal(true);
  };

  const allSelected = visible.length > 0 && selected.size === visible.length;
  const selectedIds = Array.from(selected);

  const filterChips = useMemo(() => {
    const chips: Array<{ key: string; label: string; value: FilterValue }> = [];
    for (const [key, value] of Object.entries(filters)) {
      chips.push({ key, label: formatChipLabel(key, value), value });
    }
    for (const [key, value] of Object.entries(advancedFilters)) {
      if (key in filters) continue;
      chips.push({ key, label: formatChipLabel(key, value), value });
    }
    return chips;
  }, [filters, advancedFilters]);

  const showEmptyState = !isLoading && baseRows.length === 0 && !isSearchActive;

  return (
    <div className="space-y-6 pb-24">
      <div className="flex items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t('products.list_title')}</h1>
          <p className="text-sm text-muted-foreground">{t('products.list_subtitle')}</p>
        </div>
        <Button asChild>
          <Link to="/products/new">
            <Plus className="size-4" />
            {t('products.create')}
          </Link>
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <CatalogSearchBox value={query} onChange={setQuery} isLoading={isSearchLoading} />
        <SavedViewsDropdown
          activeSlug={activeViewSlug}
          onApply={(view) => handleApplySavedView({ slug: view.slug, config: view.config })}
          onSaveCurrent={openSaveViewModal}
        />
        <VariantsToggle mode={variantsMode} onChange={setVariantsMode} />
        <AdvancedFilterBuilder
          filters={advancedFilters}
          onApply={(next) => setAdvancedFilters(next)}
          onSaveAsView={openSaveViewModal}
        />
        <div className="ml-auto flex items-center gap-1 rounded-md border bg-card p-1">
          <Button
            variant={viewMode === 'table' ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => setViewMode('table')}
            aria-label={t('products.view_mode.table', { defaultValue: 'Table view' })}
          >
            <TableIcon className="size-4" />
          </Button>
          <Button
            variant={viewMode === 'excel' ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => setViewMode('excel')}
            aria-label={t('products.view_mode.excel', { defaultValue: 'Excel view' })}
          >
            <SheetIcon className="size-4" />
          </Button>
        </div>
      </div>

      <ProductFilterChips chips={filterChips} onRemove={removeFilterChip} />

      <div className="flex flex-wrap items-center gap-2">
        <span className="text-xs uppercase tracking-wide text-muted-foreground">
          {t('products.filter_provenance')}
        </span>
        <Button
          type="button"
          variant={filters.provenance === undefined ? 'secondary' : 'ghost'}
          size="sm"
          onClick={() => {
            setFilters((prev) => {
              const { provenance: _omit, ...rest } = prev;
              return rest;
            });
          }}
        >
          {t('products.filter_provenance_all', { defaultValue: 'All' })}
        </Button>
        {PROVENANCE_OPTIONS.map((option) => (
          <Button
            key={option}
            type="button"
            variant={filters.provenance === option ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => setFilters((prev) => ({ ...prev, provenance: option }))}
          >
            <ProvenanceBadge provenance={option} className="px-1 py-0" />
          </Button>
        ))}
      </div>

      {showEmptyState ? (
        <EmptyStateProducts />
      ) : (
        <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
          <aside className="space-y-3">
            <h2 className="text-sm font-medium">{t('search.facets_title')}</h2>
            <CatalogFacetList
              distribution={searchResult?.facetDistribution ?? {}}
              active={filters}
              onToggle={toggleFacet}
            />
          </aside>

          <div className="rounded-xl border bg-card">
            {viewMode === 'excel' ? (
              <div className="overflow-x-auto p-2">
                <ExcelLikeGrid
                  rows={visible.map((row) => ({
                    sku: row.sku,
                    name: row.name,
                    brand: row.brand ?? '',
                    completeness: row.completenessPct,
                  }))}
                  columns={EXCEL_COLUMNS}
                  onCommit={(rowIdx, colKey, value) => {
                    const target = visible[rowIdx];
                    if (target === undefined) return;
                    void jsonFetch(`/api/products/${target.id}`, {
                      method: 'PATCH',
                      body: { attributesIndexed: { [colKey]: value } },
                      contentType: 'application/merge-patch+json',
                    }).then(() => refetch());
                  }}
                />
              </div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-[40px]">
                      <input
                        type="checkbox"
                        aria-label={t('products.actions.select_all', {
                          defaultValue: 'Select all',
                        })}
                        checked={allSelected}
                        onChange={toggleSelectAll}
                        className="size-4"
                      />
                    </TableHead>
                    <TableHead className="w-[180px]">{t('products.fields.sku')}</TableHead>
                    <TableHead>{t('products.fields.name')}</TableHead>
                    <TableHead className="w-[160px]">{t('products.fields.brand')}</TableHead>
                    <TableHead className="w-[140px]">
                      {t('products.fields.completeness', { defaultValue: 'Compl.' })}
                    </TableHead>
                    <TableHead className="w-[60px]">
                      {t('products.fields.sync', { defaultValue: 'Sync' })}
                    </TableHead>
                    <TableHead className="w-[120px]">
                      {t('products.fields.channels', { defaultValue: 'Channels' })}
                    </TableHead>
                    <TableHead className="w-[110px]">{t('products.fields.status')}</TableHead>
                    <TableHead className="w-[180px]">{t('products.fields.created_at')}</TableHead>
                    <TableHead className="w-[60px] text-right">
                      <span className="sr-only">{t('products.fields.actions')}</span>
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {isLoading ? (
                    <TableRow>
                      <TableCell colSpan={10} className="py-10 text-center text-muted-foreground">
                        {t('app.loading')}
                      </TableCell>
                    </TableRow>
                  ) : visible.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={10} className="py-10 text-center text-muted-foreground">
                        {isSearchActive
                          ? t('search.no_results')
                          : t('products.empty', { defaultValue: 'No products yet' })}
                      </TableCell>
                    </TableRow>
                  ) : (
                    visible.map((product) => (
                      <TableRow key={product.id}>
                        <TableCell>
                          <input
                            type="checkbox"
                            aria-label={t('products.actions.select_row', {
                              defaultValue: 'Select row',
                            })}
                            checked={selected.has(product.id)}
                            onChange={() => toggleSelect(product.id)}
                            className="size-4"
                          />
                        </TableCell>
                        <TableCell className="font-mono text-xs">
                          <Link to={`/products/${product.id}`} className="hover:underline">
                            {product.sku}
                          </Link>
                        </TableCell>
                        <TableCell className="font-medium">{product.name}</TableCell>
                        <TableCell>{product.brand ?? '—'}</TableCell>
                        <TableCell>
                          <CompletenessBadge pct={product.completenessPct} />
                        </TableCell>
                        <TableCell>
                          <SyncAggregateIcon status={product.syncStatusAggregate} />
                        </TableCell>
                        <TableCell>
                          <ChannelInlineIcons channels={[] as ChannelStatusEntry[]} />
                        </TableCell>
                        <TableCell>
                          <StatusBadge enabled={product.enabled} status={product.status} />
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                          {formatDateTime(product.createdAt, i18n.language)}
                        </TableCell>
                        <TableCell className="text-right">
                          <ProductRowActions
                            productId={product.id}
                            enabled={product.enabled}
                            onChanged={refetch}
                          />
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            )}
          </div>
        </div>
      )}

      <BulkActionsToolbar
        ids={selectedIds}
        showSelectedOnly={showSelectedOnly}
        onToggleShowSelectedOnly={setShowSelectedOnly}
        onCleared={() => {
          setSelected(new Set());
          setShowSelectedOnly(false);
          refetch();
        }}
      />

      {showSaveViewModal ? (
        <SaveViewModal
          resource="products"
          config={{ filters, variants_mode: variantsMode }}
          onClose={() => setShowSaveViewModal(false)}
          onSaved={(slug) => setActiveViewSlug(slug)}
        />
      ) : null}
    </div>
  );
}

type ExcelRow = { sku: string; name: string; brand: string; completeness: number };

const EXCEL_COLUMNS: ExcelColumn<ExcelRow>[] = [
  { key: 'sku', label: 'SKU', type: 'text', width: 160, readOnly: true },
  { key: 'name', label: 'Name', type: 'text' },
  { key: 'brand', label: 'Brand', type: 'text' },
  { key: 'completeness', label: 'Compl.', type: 'number', readOnly: true, width: 100 },
];

function StatusBadge({ enabled, status }: { enabled: boolean; status: string | null }) {
  const tone = enabled
    ? 'bg-emerald-100 text-emerald-900'
    : 'bg-muted text-muted-foreground line-through';
  return (
    <span className={`inline-flex rounded px-2 py-0.5 text-xs font-medium ${tone}`}>
      {status ?? (enabled ? 'enabled' : 'disabled')}
    </span>
  );
}

function searchHitToProduct(hit: CatalogSearchHit): ProductRow {
  return buildRow({
    id: hit.id,
    code: hit.code ?? hit.id,
    enabled: hit.enabled,
    status: hit.status,
    attributesIndexed: hit.attributesIndexed,
    createdAt: undefined,
  });
}

function catalogObjectToProduct(entry: CatalogObjectListEntry): ProductRow {
  return buildRow(entry);
}

function buildRow(entry: CatalogObjectListEntry): ProductRow {
  const attrs = (entry.attributesIndexed ?? {}) as Record<string, unknown>;
  const name = typeof attrs.name === 'string' ? attrs.name : entry.code;
  const description = typeof attrs.description === 'string' ? attrs.description : null;
  const brand = typeof attrs.brand === 'string' ? attrs.brand : null;
  return {
    id: entry.id,
    sku: entry.code,
    name,
    description,
    brand,
    createdAt: entry.createdAt ?? '',
    enabled: entry.enabled !== false,
    status: typeof entry.status === 'string' ? entry.status : null,
    completenessPct: typeof entry.completenessPct === 'number' ? entry.completenessPct : 0,
    syncStatusAggregate: normaliseSyncAggregate(entry.syncStatusAggregate),
  };
}

function normaliseSyncAggregate(raw: string | undefined): SyncAggregate {
  if (raw === 'green' || raw === 'yellow' || raw === 'red' || raw === 'gray') {
    return raw;
  }
  return 'gray';
}

function formatDateTime(value: string, locale: string): string {
  if (value === '') return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(date);
}
