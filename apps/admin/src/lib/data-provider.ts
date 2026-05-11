import type { DataProvider } from '@refinedev/core';

import { jsonFetch } from './http';

/**
 * Minimal Hydra-aware DataProvider for API Platform 4. Only the operations
 * Refine needs for the Sprint-0 admin slice (list + getOne + create + update)
 * are implemented; deleteOne, getMany etc. land alongside the tickets that
 * actually use them.
 *
 * The collection response is a Hydra Collection: `member` is the data array
 * and `totalItems` the total. Cursor pagination via `id[lt]` / `id[gt]` is
 * available on /api/products (ticket #3) but Refine's offset model isn't a
 * great fit yet — getList exposes only `?page=` and reads the totalItems
 * count for now. Full cursor wiring is a follow-up in epic 0.4.
 */
interface HydraCollection<T> {
  member: T[];
  totalItems: number;
}

interface HydraResource {
  '@id'?: string;
  id?: string;
}

const API_BASE = '/api';

export const dataProvider: DataProvider = {
  getApiUrl: () => API_BASE,

  async getList({ resource, pagination, filters }) {
    const query: Record<string, string | number | undefined> = {};
    if (pagination?.currentPage) {
      query.page = pagination.currentPage;
    }
    // Forward simple `eq` field filters as query params. The custom
    // collection extensions per resource read these directly (the
    // Asset DAM pipeline relies on `?search=` + `?mimeGroup=`).
    if (filters) {
      for (const filter of filters) {
        if (
          'field' in filter &&
          filter.operator === 'eq' &&
          filter.value !== undefined &&
          filter.value !== ''
        ) {
          query[filter.field] = String(filter.value);
        }
      }
    }
    const data = await jsonFetch<HydraCollection<HydraResource>>(`${API_BASE}/${resource}`, {
      query,
    });
    return {
      data: (data.member ?? []) as never[],
      total: data.totalItems ?? 0,
    };
  },

  async getOne({ resource, id }) {
    const data = await jsonFetch<HydraResource>(`${API_BASE}/${resource}/${id}`);
    return { data: data as never };
  },

  async create({ resource, variables }) {
    const data = await jsonFetch<HydraResource>(`${API_BASE}/${resource}`, {
      method: 'POST',
      body: variables,
    });
    return { data: data as never };
  },

  async update({ resource, id, variables }) {
    const data = await jsonFetch<HydraResource>(`${API_BASE}/${resource}/${id}`, {
      method: 'PATCH',
      body: variables,
      contentType: 'application/merge-patch+json',
    });
    return { data: data as never };
  },

  async deleteOne({ resource, id }) {
    await jsonFetch(`${API_BASE}/${resource}/${id}`, { method: 'DELETE' });
    return { data: {} as never };
  },

  async getMany({ resource, ids }) {
    const fetched = await Promise.all(
      ids.map((id) => jsonFetch<HydraResource>(`${API_BASE}/${resource}/${id}`)),
    );
    return { data: fetched as never[] };
  },

  // Forwards Refine's `useCustom` / `useCustomMutation` to `jsonFetch` so
  // non-Hydra endpoints (auto-map, backup status, profile test-connection,
  // token rotation, …) flow through the same auth pipeline as the rest of
  // the admin. Without this, hooks fail silently and the UI looks blank —
  // exactly the symptom that surfaced on the Mapping step of the import
  // wizard before IMP-10 follow-up.
  async custom({ url, method, payload, query }) {
    const upper = method.toUpperCase();
    if (
      upper !== 'GET' &&
      upper !== 'POST' &&
      upper !== 'PATCH' &&
      upper !== 'PUT' &&
      upper !== 'DELETE'
    ) {
      throw new Error(`dataProvider.custom: unsupported method "${method}"`);
    }
    const data = await jsonFetch(url, {
      method: upper,
      body: payload,
      query: query as Record<string, string | number | undefined> | undefined,
      contentType: 'application/json',
      accept: 'application/json',
    });
    return { data: data as never };
  },
};
