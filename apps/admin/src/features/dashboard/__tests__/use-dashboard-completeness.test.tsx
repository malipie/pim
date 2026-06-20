import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { jsonFetch } from '@/lib/http';

import { useDashboardCompleteness } from '../use-dashboard-completeness';

vi.mock('@/lib/http', () => ({ jsonFetch: vi.fn() }));

const jsonFetchMock = vi.mocked(jsonFetch);

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

/** Resolve each `?completeness[gte]=N` (and the unfiltered total) to a count. */
function mockCounts(counts: { total: number; gte: Record<number, number> }) {
  jsonFetchMock.mockImplementation(async (_path, init) => {
    const gte = init?.query?.['completeness[gte]'];
    if (gte === undefined) {
      return { totalItems: counts.total };
    }
    return { totalItems: counts.gte[Number(gte)] ?? 0 };
  });
}

afterEach(() => {
  jsonFetchMock.mockReset();
});

describe('useDashboardCompleteness', () => {
  it('computes the publish-ready share from real bucket counts', async () => {
    mockCounts({ total: 6913, gte: { 25: 6911, 50: 1464, 80: 302, 100: 100 } });

    const { result } = renderHook(() => useDashboardCompleteness(), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const data = result.current.data;
    expect(data?.total).toBe(6913);
    expect(data?.publishReady).toBe(302);
    // 302 / 6913 = 4.37% → rounds to 4.
    expect(data?.publishReadyPct).toBe(4);
    expect(data?.buckets).toHaveLength(4);
  });

  it('degrades to null when the total count is unavailable', async () => {
    jsonFetchMock.mockRejectedValue(new Error('boom'));

    const { result } = renderHook(() => useDashboardCompleteness(), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data).toBeNull();
  });
});
