import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { jsonFetch } from '@/lib/http';

import { LocalesSettingsPage, tenantLocaleKeys } from '../index';
import type { TenantLocaleListItem } from '../types';

// AUD-055 (ADR-0021) — proof that migrating LocalesSettingsPage from
// jsonFetch+useEffect to useQuery fixes the stale-data class: invalidating
// the list key (what every mutation handler now does, and what an external
// screen could do) re-renders the table with fresh server data. The old
// useEffect-with-[] read was immune to that.

vi.mock('@/lib/http', () => ({ jsonFetch: vi.fn() }));
// The "Add locale" modal does its own fetching; not under test here.
vi.mock('../AddLocaleModal', () => ({ AddLocaleModal: () => null }));
vi.mock('@/components/ui/toast', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

const jsonFetchMock = vi.mocked(jsonFetch);

function makeLocale(
  code: string,
  overrides: Partial<TenantLocaleListItem> = {},
): TenantLocaleListItem {
  return {
    id: `id-${code}`,
    code,
    label: code.toUpperCase(),
    language: code,
    region: null,
    // Distinct from `code` so getByText(code) targets the mono code cell,
    // not the localized display name.
    displayName: { pl: `Język ${code}`, en: `Lang ${code}` },
    isDefault: false,
    isMandatory: false,
    fallbackCode: null,
    sortOrder: 0,
    isActive: true,
    createdAt: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
  const result = render(<LocalesSettingsPage />, { wrapper });
  return { client, ...result };
}

afterEach(() => {
  jsonFetchMock.mockReset();
});

describe('LocalesSettingsPage data fetching (ADR-0021)', () => {
  it('refreshes the list when the query key is invalidated', async () => {
    // First read: one locale.
    jsonFetchMock.mockResolvedValue({ items: [makeLocale('pl', { isDefault: true })] });

    const { client } = renderPage();

    // The mono code cell carries the locale code; assert on it specifically
    // (the code also leaks into other rows' fallback <option>s once active).
    const codeCell = (code: string) =>
      screen.queryAllByText(code).find((el) => el.classList.contains('font-mono'));

    await waitFor(() => expect(codeCell('pl')).toBeDefined());
    expect(codeCell('de')).toBeUndefined();

    // Simulate a mutation landing a new locale server-side, then the exact
    // invalidation every handler performs.
    jsonFetchMock.mockResolvedValue({
      items: [makeLocale('pl', { isDefault: true }), makeLocale('de')],
    });
    await client.invalidateQueries({ queryKey: tenantLocaleKeys.list() });

    // The table re-renders with the new row — no manual refetch wiring.
    await waitFor(() => expect(codeCell('de')).toBeDefined());
    expect(codeCell('pl')).toBeDefined();
  });
});
