import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, useParams } from 'react-router';

import { UniversalDetailPage } from '@/components/objects/universal-detail-page';
import { useListSchema } from '@/hooks/use-list-schema';
import { jsonFetch } from '@/lib/http';

/**
 * UP-07 (#1023) — `/objects/:slug/:id` route → UniversalDetailPage.
 *
 * Built-in `product` redirects to `/products/{id}` so operators land on
 * the full-feature legacy ProductDetailPage (variants tab, multimedia,
 * sync, duplicate, preview) — dual maintenance per UP-10. Custom kinds
 * render UniversalDetailPage with attribute groups + capability-gated
 * categories tab.
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

const REDIRECT_TO_LEGACY: Record<string, (id: string) => string> = {
  product: (id) => `/products/${id}`,
  category: (id) => `/modeling/categories/${id}`,
  asset: (id) => `/multimedia/${id}`,
};

export function ObjectShowPage() {
  const { t, i18n } = useTranslation();
  const locale = i18n.language.split('-')[0] ?? 'en';
  const { slug, id } = useParams<{ slug: string; id: string }>();

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
  if (legacyRedirect && id) {
    return <Navigate to={legacyRedirect(id)} replace />;
  }

  if (schemaQuery.isError || !schemaQuery.data || !id) {
    return (
      <div className="rounded border border-destructive bg-destructive/5 p-6 text-sm text-destructive">
        {t('object_list.errors.schema_fetch', { defaultValue: 'Could not load list schema.' })}
      </div>
    );
  }

  return (
    <UniversalDetailPage
      objectId={id}
      objectTypeCode={code}
      objectTypeLabel={typeLabel}
      backHref={`/objects/${code}`}
      isCategorizable={schemaQuery.data.objectType.is_categorizable}
      hasMultimedia={schemaQuery.data.objectType.has_multimedia}
      hasVariants={schemaQuery.data.objectType.has_variants}
    />
  );
}

export default ObjectShowPage;
