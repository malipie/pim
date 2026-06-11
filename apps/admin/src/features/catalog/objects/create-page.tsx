import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, useParams } from 'react-router';

import { ProductDetailPage } from '@/features/catalog/products/components/product-detail-page';
import { useListSchema } from '@/hooks/use-list-schema';
import { jsonFetch } from '@/lib/http';

/**
 * UP-08 (#1029) — `/objects/:slug/new` route.
 *
 * Built-in product/category/asset redirect to their dedicated legacy
 * create routes. Custom kinds render the unified create form
 * (ProductDetailPage in create mode, #1415) — same component as
 * /products/new, POSTs the poly-kind /api/objects.
 */
interface ObjectTypeLookupRow {
  id: string;
  code: string;
  label?: Record<string, string>;
}

interface ObjectTypeLookupResponse {
  member?: ObjectTypeLookupRow[];
  'hydra:member'?: ObjectTypeLookupRow[];
}

const REDIRECT_TO_LEGACY: Record<string, string> = {
  product: '/products/new',
  category: '/modeling/categories?action=create',
  asset: '/multimedia?action=upload',
};

export function ObjectCreatePage() {
  const { t, i18n } = useTranslation();
  const locale = i18n.language.split('-')[0] ?? 'en';
  const { slug } = useParams<{ slug: string }>();

  const lookup = useQuery({
    queryKey: ['object-type-by-code', slug],
    enabled: typeof slug === 'string' && slug.length > 0,
    staleTime: 5 * 60 * 1000,
    queryFn: async () => {
      const response = await jsonFetch<ObjectTypeLookupResponse>('/api/object_types', {
        accept: 'application/ld+json',
        query: { code: slug, itemsPerPage: 1 },
      });
      const members = response.member ?? response['hydra:member'] ?? [];
      const first = members[0];
      if (first === undefined || first.code !== slug) {
        return null;
      }
      return first;
    },
  });

  const schemaQuery = useListSchema(lookup.data?.id);

  const typeLabel = useMemo(() => {
    if (!lookup.data) return '';
    const labels = lookup.data.label ?? {};
    return labels[locale] ?? labels.en ?? lookup.data.code;
  }, [lookup.data, locale]);

  if (lookup.isLoading || (lookup.data && schemaQuery.isLoading)) {
    return (
      <div
        aria-busy="true"
        className="flex h-64 items-center justify-center text-sm text-muted-foreground"
      >
        {t('object_list.resolving_slug', { defaultValue: 'Resolving ObjectType…' })}
      </div>
    );
  }

  if (lookup.isError || lookup.data === null || lookup.data === undefined) {
    return (
      <div className="rounded border border-destructive bg-destructive/5 p-6 text-sm text-destructive">
        {t('object_list.errors.slug_not_found', {
          defaultValue: 'ObjectType "{{slug}}" was not found.',
          slug,
        })}
      </div>
    );
  }

  const code = lookup.data.code;
  const legacyRedirect = REDIRECT_TO_LEGACY[code];
  if (legacyRedirect) {
    return <Navigate to={legacyRedirect} replace />;
  }

  // #1415 — capability flags (categories requirement, tab set) are
  // derived inside the unified component from this ObjectType's schema.
  return (
    <ProductDetailPage
      mode="create"
      createObjectTypeId={lookup.data.id}
      objectTypeLabel={typeLabel}
      backHref={`/objects/${code}`}
      detailPathFor={(id) => `/objects/${code}/${id}`}
    />
  );
}

export default ObjectCreatePage;
