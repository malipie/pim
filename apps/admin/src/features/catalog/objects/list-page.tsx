import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useParams } from 'react-router';

import { ObjectListView } from '@/components/objects/object-list-view';
import { jsonFetch } from '@/lib/http';

/**
 * ULV-08 (#990) — `/objects/:slug` route renders the universal
 * `ObjectListView` for the ObjectType whose `code` (per ULV-01 reused as
 * URL slug) matches.
 *
 * Resolution: we query the existing `/api/object_types?code=...` filter
 * for a single match, then pass its UUID to `ObjectListView`. The lookup
 * is cached for 5 min — slugs are stable and the slug→id mapping is the
 * hottest part of the route hit.
 *
 * `/products`, `/categories`, `/assets` keep working through their own
 * routes (ULV-11 will collapse them into this path with regression
 * baseline). 404 if no matching ObjectType in the current tenant.
 */
interface ObjectTypeLookupResponse {
  member?: Array<{ id: string; code: string }>;
  'hydra:member'?: Array<{ id: string; code: string }>;
}

export function ObjectListPage() {
  const { t } = useTranslation();
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
      return members[0] ?? null;
    },
  });

  if (lookup.isLoading) {
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
          defaultValue: 'ObjectType "{{slug}}" was not found in this tenant.',
          slug,
        })}
      </div>
    );
  }

  return <ObjectListView objectTypeId={lookup.data.id} />;
}

export default ObjectListPage;
