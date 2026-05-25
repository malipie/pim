import { useQuery } from '@tanstack/react-query';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { CreateObjectDialog } from '@/components/objects/create-object-dialog';
import { ObjectListView } from '@/components/objects/object-list-view';
import { jsonFetch } from '@/lib/http';

/**
 * ULV-08 (#990) + #1012 + #1014 — `/objects/:slug` route renders the
 * universal `ObjectListView` for the ObjectType whose `code` (per ULV-01
 * reused as URL slug) matches.
 *
 * Slug resolution: `/api/object_types?code={slug}&itemsPerPage=1`
 * (filter shipped in #1012). Client-side guard verifies
 * `member.code === slug` post-fetch as defence-in-depth.
 *
 * Create flow (per kind, #1014 fix):
 *   - Built-in `product` / `category` / `asset` keep their dedicated
 *     create routes (`/products/new`, `/modeling/categories?action=create`,
 *     `/multimedia?action=upload`).
 *   - Built-in `brand` falls through to the modal — there is no dedicated
 *     brand create page; the modal handles it via the poly-kind POST.
 *   - Every other kind (custom) opens `CreateObjectDialog` in-page —
 *     pre-fix navigated to `/objects/{code}/new` which the App-level
 *     catch-all redirected to `/dashboard`, breaking the flow entirely.
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

const ROUTABLE_BUILT_IN_KINDS: Record<string, string> = {
  product: '/products/new',
  category: '/modeling/categories?action=create',
  asset: '/multimedia?action=upload',
};

export function ObjectListPage() {
  const { t, i18n } = useTranslation();
  const locale = i18n.language.split('-')[0] ?? 'en';
  const navigate = useNavigate();
  const { slug } = useParams<{ slug: string }>();
  const [createDialogOpen, setCreateDialogOpen] = useState(false);

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

  const handleCreate = useCallback(() => {
    if (!lookup.data) return;
    const code = lookup.data.code;
    const sugarPath = ROUTABLE_BUILT_IN_KINDS[code];
    if (sugarPath !== undefined) {
      navigate(sugarPath);
      return;
    }
    // Every other kind (custom + built-in brand) opens the in-page
    // dialog instead of navigating to a non-existent `/objects/{code}/new`
    // route (the App catch-all would redirect to /dashboard — #1014 bug A).
    setCreateDialogOpen(true);
  }, [lookup.data, navigate]);

  const typeLabel = useMemo(() => {
    if (!lookup.data) return '';
    const labels = lookup.data.label ?? {};
    return labels[locale] ?? labels.en ?? lookup.data.code;
  }, [lookup.data, locale]);

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

  return (
    <>
      <ObjectListView objectTypeId={lookup.data.id} onCreate={handleCreate} />
      <CreateObjectDialog
        open={createDialogOpen}
        onOpenChange={setCreateDialogOpen}
        objectTypeId={lookup.data.id}
        objectTypeCode={lookup.data.code}
        objectTypeLabel={typeLabel}
      />
    </>
  );
}

export default ObjectListPage;
