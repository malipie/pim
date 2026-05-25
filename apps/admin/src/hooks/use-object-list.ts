import { type UseQueryResult, useQuery } from '@tanstack/react-query';

import { jsonFetch } from '@/lib/http';

/**
 * ULV-03 (#984) — `GET /api/objects?objectType=...` collection shape.
 *
 * The poly-kind /api/objects endpoint returns Hydra-shaped CatalogObject
 * rows for every kind narrowed by tenant + voter + (optionally) the new
 * ObjectTypeFilter from ULV-03. Each row carries the full ApiPlatform
 * normalisation (objectType reference, attributesIndexed JSONB, etc.).
 *
 * For the universal `ObjectListView` we only consume the flat top-level
 * fields + the attributesIndexed JSONB; deeper rendering is delegated to
 * ULV-07's column renderer registry.
 */
export interface ObjectListItem {
  id: string;
  code: string;
  kind: string;
  status: string;
  enabled: boolean;
  completenessPct?: number;
  updatedAt: string;
  attributesIndexed?: Record<string, unknown>;
  objectType?: {
    id: string;
    code: string;
    label: Record<string, string>;
  };
}

export interface ObjectListResponse {
  '@id': string;
  '@type': string;
  totalItems: number;
  member: ObjectListItem[];
  view?: {
    next?: string;
    previous?: string;
  };
}

export interface UseObjectListParams {
  objectTypeId: string | undefined;
  itemsPerPage?: number;
  cursorAfter?: string;
  cursorBefore?: string;
  /** #1012 — substring search on `CatalogObject.code` via the SkuFilter. */
  query?: string;
  /** #1012 — exact-match status filter (`published`, `draft`, `archived`). */
  status?: string;
}

/**
 * Fetches the cursor-paginated list of objects for the given ObjectType.
 *
 * `cursorAfter` / `cursorBefore` map to AP4's `?id[lt]=...` / `?id[gt]=...`
 * cursor params advertised by the IriTemplate on /api/objects. The
 * `query` param forwards as `?sku=...` (substring on code, existing
 * `SkuFilter`) and `status` as `?status=...` (existing `StatusFilter`).
 */
export function useObjectList(params: UseObjectListParams): UseQueryResult<ObjectListResponse> {
  const {
    objectTypeId,
    itemsPerPage = 30,
    cursorAfter,
    cursorBefore,
    query: searchQuery,
    status,
  } = params;

  return useQuery({
    queryKey: [
      'object-list',
      objectTypeId,
      itemsPerPage,
      cursorAfter,
      cursorBefore,
      searchQuery,
      status,
    ],
    enabled: Boolean(objectTypeId),
    staleTime: 30 * 1000,
    queryFn: async () => {
      const query: Record<string, string | number | undefined> = {
        objectType: objectTypeId,
        itemsPerPage,
      };
      if (cursorAfter) {
        query['id[lt]'] = cursorAfter;
      }
      if (cursorBefore) {
        query['id[gt]'] = cursorBefore;
      }
      if (searchQuery && searchQuery.length > 0) {
        query.sku = searchQuery;
      }
      if (status && status.length > 0) {
        query.status = status;
      }

      return jsonFetch<ObjectListResponse>('/api/objects', {
        accept: 'application/ld+json',
        query,
      });
    },
  });
}
