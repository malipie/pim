import { useList } from '@refinedev/core';
import { Eye, Pencil, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

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

import { ProductBulkBar } from './product-bulk-bar';

interface CatalogObjectListEntry {
  id: string;
  code: string;
  enabled?: boolean;
  status?: string;
  createdAt?: string;
  attributesIndexed?: Record<string, unknown>;
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
}

const PRODUCT_FACETS = ['enabled', 'status'];
const PROVENANCE_OPTIONS: ReadonlyArray<Provenance> = ['manual', 'import', 'integration', 'agent'];

export function ProductListPage() {
  const { t, i18n } = useTranslation();
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState<Record<string, string | string[]>>({});
  const [selected, setSelected] = useState<Set<string>>(new Set());

  const isSearchActive = query !== '' || Object.keys(filters).length > 0;

  const { result: searchResult, isLoading: isSearchLoading } = useCatalogSearch({
    kind: 'products',
    query,
    filters,
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

  const visible = useMemo<ProductRow[]>(() => {
    if (isSearchActive) {
      return (searchResult?.hits ?? []).map(searchHitToProduct);
    }
    return (products ?? []).map(catalogObjectToProduct);
  }, [isSearchActive, products, searchResult]);

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

  const resetFilters = (): void => {
    setQuery('');
    setFilters({});
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

  const allSelected = visible.length > 0 && selected.size === visible.length;
  const selectedIds = Array.from(selected);

  return (
    <div className="space-y-6">
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
        {isSearchActive ? (
          <Button type="button" variant="ghost" size="sm" onClick={resetFilters}>
            {t('search.reset_filters')}
          </Button>
        ) : null}
        {isSearchActive && searchResult ? (
          <span className="text-xs text-muted-foreground">
            {t('search.results_count', {
              count: searchResult.totalHits,
              defaultValue_one: '{{count}} result',
              defaultValue_other: '{{count}} results',
            })}
          </span>
        ) : null}
      </div>

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

      {selectedIds.length > 0 ? (
        <ProductBulkBar
          ids={selectedIds}
          onCleared={() => {
            setSelected(new Set());
            refetch();
          }}
        />
      ) : null}

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
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[40px]">
                  <input
                    type="checkbox"
                    aria-label={t('products.actions.select_all', { defaultValue: 'Select all' })}
                    checked={allSelected}
                    onChange={toggleSelectAll}
                    className="size-4"
                  />
                </TableHead>
                <TableHead className="w-[180px]">{t('products.fields.sku')}</TableHead>
                <TableHead>{t('products.fields.name')}</TableHead>
                <TableHead className="w-[160px]">{t('products.fields.brand')}</TableHead>
                <TableHead className="w-[110px]">{t('products.fields.status')}</TableHead>
                <TableHead className="w-[180px]">{t('products.fields.created_at')}</TableHead>
                <TableHead className="w-[120px] text-right">
                  <span className="sr-only">{t('products.fields.actions')}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={7} className="py-10 text-center text-muted-foreground">
                    {t('app.loading')}
                  </TableCell>
                </TableRow>
              ) : visible.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="py-10 text-center text-muted-foreground">
                    {isSearchActive ? t('search.no_results') : t('products.empty')}
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
                    <TableCell className="font-mono text-xs">{product.sku}</TableCell>
                    <TableCell className="font-medium">{product.name}</TableCell>
                    <TableCell>{product.brand ?? '—'}</TableCell>
                    <TableCell>
                      <StatusBadge enabled={product.enabled} status={product.status} />
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {formatDateTime(product.createdAt, i18n.language)}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button asChild variant="ghost" size="sm">
                        <Link to={`/products/${product.id}`}>
                          <Eye className="size-4" />
                          <span className="sr-only">{t('products.actions.view')}</span>
                        </Link>
                      </Button>
                      <Button asChild variant="ghost" size="sm">
                        <Link to={`/products/${product.id}/edit`}>
                          <Pencil className="size-4" />
                          <span className="sr-only">{t('products.actions.edit')}</span>
                        </Link>
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>
    </div>
  );
}

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
  };
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
