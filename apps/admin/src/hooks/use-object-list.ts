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
}

/**
 * Fetches the cursor-paginated list of objects for the given ObjectType.
 *
 * `cursorAfter` / `cursorBefore` map to AP4's `?id[lt]=...` / `?id[gt]=...`
 * cursor params advertised by the IriTemplate on /api/objects. The
 * upstream filters (?status, ?completeness etc.) are forwarded later
 * via ULV-06 / ULV-07 filter UI; this hook keeps the core wiring lean.
 */
export function useObjectList(params: UseObjectListParams): UseQueryResult<ObjectListResponse> {
  const { objectTypeId, itemsPerPage = 30, cursorAfter, cursorBefore } = params;

  return useQuery({
    queryKey: ['object-list', objectTypeId, itemsPerPage, cursorAfter, cursorBefore],
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

      return jsonFetch<ObjectListResponse>('/api/objects', {
        accept: 'application/ld+json',
        query,
      });
    },
  });
}
