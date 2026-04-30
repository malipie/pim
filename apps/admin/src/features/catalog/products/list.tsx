import { useList } from '@refinedev/core';
import { Pencil, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

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

interface Product {
  id: string;
  sku: string;
  name: string;
  description: string | null;
  brand: string | null;
  createdAt: string;
}

const PRODUCT_FACETS = ['enabled', 'status'];

export function ProductListPage() {
  const { t, i18n } = useTranslation();
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState<Record<string, string | string[]>>({});

  const isSearchActive = query !== '' || Object.keys(filters).length > 0;

  const { result: searchResult, isLoading: isSearchLoading } = useCatalogSearch({
    kind: 'products',
    query,
    filters,
    facets: PRODUCT_FACETS,
    perPage: 30,
  });

  const { result, query: listQuery } = useList<Product>({
    resource: 'products',
    queryOptions: { enabled: !isSearchActive },
  });
  const products = result.data;
  const isListLoading = listQuery.isLoading;

  const searchProducts = useMemo<Product[]>(
    () => (searchResult?.hits ?? []).map(toProduct),
    [searchResult],
  );

  const visible = isSearchActive ? searchProducts : products;
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
                <TableHead className="w-[180px]">{t('products.fields.sku')}</TableHead>
                <TableHead>{t('products.fields.name')}</TableHead>
                <TableHead className="w-[160px]">{t('products.fields.brand')}</TableHead>
                <TableHead className="w-[180px]">{t('products.fields.created_at')}</TableHead>
                <TableHead className="w-[80px] text-right">
                  <span className="sr-only">{t('products.fields.actions')}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={5} className="py-10 text-center text-muted-foreground">
                    {t('app.loading')}
                  </TableCell>
                </TableRow>
              ) : visible.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="py-10 text-center text-muted-foreground">
                    {isSearchActive ? t('search.no_results') : t('products.empty')}
                  </TableCell>
                </TableRow>
              ) : (
                visible.map((product) => (
                  <TableRow key={product.id}>
                    <TableCell className="font-mono text-xs">{product.sku}</TableCell>
                    <TableCell className="font-medium">{product.name}</TableCell>
                    <TableCell>{product.brand ?? '—'}</TableCell>
                    <TableCell className="text-muted-foreground">
                      {formatDateTime(product.createdAt, i18n.language)}
                    </TableCell>
                    <TableCell className="text-right">
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

function toProduct(hit: CatalogSearchHit): Product {
  const attrs = (hit.attributesIndexed ?? {}) as Record<string, unknown>;
  const name = typeof attrs.name === 'string' ? attrs.name : (hit.code ?? hit.id);
  const description = typeof attrs.description === 'string' ? attrs.description : null;
  const brand = typeof attrs.brand === 'string' ? attrs.brand : null;
  return {
    id: hit.id,
    sku: hit.code ?? hit.id,
    name,
    description,
    brand,
    createdAt: '',
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
