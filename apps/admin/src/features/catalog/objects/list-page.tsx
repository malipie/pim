import { useQuery } from '@tanstack/react-query';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { ObjectListView } from '@/components/objects/object-list-view';
import { jsonFetch } from '@/lib/http';

/**
 * ULV-08 (#990) + #1012 — `/objects/:slug` route renders the universal
 * `ObjectListView` for the ObjectType whose `code` (per ULV-01 reused as
 * URL slug) matches.
 *
 * Resolution: we query `/api/object_types?code={slug}&itemsPerPage=1`
 * (the `code` filter shipped in #1012 — without it the GetCollection
 * ignored the param and returned every type, so the FE picked
 * `members[0]` = `product` for every slug). Cached 5 min.
 *
 * Client-side guard: we still verify `member.code === slug` post-fetch
 * as defence-in-depth — if the filter ever regresses the page renders
 * the slug-not-found error instead of silently showing the wrong list.
 *
 * `/products`, `/categories`, `/assets` keep working through their own
 * routes; the cutover to consolidate them onto `/objects/{slug}` is the
 * deferred ULV-11 follow-up. 404-style error in the current tenant if
 * the slug does not match an ObjectType.
 */
interface ObjectTypeLookupRow {
  id: string;
  code: string;
}

interface ObjectTypeLookupResponse {
  member?: ObjectTypeLookupRow[];
  'hydra:member'?: ObjectTypeLookupRow[];
}

export function ObjectListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
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
      // Defence in depth — refuse to render if the row returned by the
      // backend does not match the slug we asked for. Protects against
      // a regression in the `code` filter where the GetCollection
      // returns every row regardless of the query param.
      if (first === undefined || first.code !== slug) {
        return null;
      }
      return first;
    },
  });

  // #1012 — built-in kinds keep their dedicated create flows
  // (`/products/new` etc.); custom kinds get a generic placeholder that
  // operators can replace once the dedicated wizard lands.
  const handleCreate = useCallback(() => {
    if (!lookup.data) return;
    const code = lookup.data.code;
    if (code === 'product') {
      navigate('/products/new');
      return;
    }
    if (code === 'category') {
      navigate('/modeling/categories?action=create');
      return;
    }
    if (code === 'asset') {
      navigate('/multimedia?action=upload');
      return;
    }
    navigate(`/objects/${code}/new`);
  }, [lookup.data, navigate]);

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

  return <ObjectListView objectTypeId={lookup.data.id} onCreate={handleCreate} />;
}

export default ObjectListPage;
