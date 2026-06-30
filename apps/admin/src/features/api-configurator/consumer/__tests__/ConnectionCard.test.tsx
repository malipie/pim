import { type DataProvider, Refine } from '@refinedev/core';
import { render, screen } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import type { ReactElement } from 'react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';

import { ConnectionCard, type ConnectionRow } from '../ConnectionCard';

expect.extend(toHaveNoViolations);

// The card now uses a Link (router) and useDelete (Refine data context); a
// minimal data provider + MemoryRouter is enough to render it in isolation.
const mockDataProvider = {
  getList: async () => ({ data: [], total: 0 }),
  getOne: async () => ({ data: {} }),
  getMany: async () => ({ data: [] }),
  create: async () => ({ data: {} }),
  update: async () => ({ data: {} }),
  deleteOne: async () => ({ data: {} }),
  getApiUrl: () => 'http://test',
} as unknown as DataProvider;

function renderCard(ui: ReactElement) {
  return render(
    <MemoryRouter>
      <Refine dataProvider={mockDataProvider} options={{ disableTelemetry: true }}>
        {ui}
      </Refine>
    </MemoryRouter>,
  );
}

const base: ConnectionRow = {
  id: '0192f0aa-0000-7000-8000-000000000001',
  code: 'shopify-eu',
  name: 'Shopify EU',
  baseUrl: 'https://eu.shopify.example/api',
  authType: 'api_key',
  rateLimitHint: 40,
  status: 'active',
  lastHealthCheckAt: '2026-06-20T10:15:00+00:00',
  createdAt: '2026-06-01T00:00:00+00:00',
  updatedAt: '2026-06-20T10:15:00+00:00',
};

describe('ConnectionCard', () => {
  it('renders the connection identity, status and auth', () => {
    renderCard(<ConnectionCard connection={base} />);

    expect(screen.getByText('Shopify EU')).toBeInTheDocument();
    expect(screen.getByText('shopify-eu')).toBeInTheDocument();
    expect(screen.getByText('https://eu.shopify.example/api')).toBeInTheDocument();
    // status pill label resolves through i18n (pl is the test default)
    expect(screen.getByText('aktywne')).toBeInTheDocument();
  });

  it('links the whole card to the connection detail', () => {
    renderCard(<ConnectionCard connection={base} />);
    const link = screen.getByRole('link', { name: /Otwórz połączenie Shopify EU/i });
    expect(link).toHaveAttribute('href', `/integrations/api-configurator/connections/${base.id}`);
  });

  it('exposes a delete action', () => {
    renderCard(<ConnectionCard connection={base} />);
    expect(screen.getByRole('button', { name: /Usuń połączenie Shopify EU/i })).toBeInTheDocument();
  });

  it('shows the never-tested fallback when no health check ran', () => {
    renderCard(<ConnectionCard connection={{ ...base, lastHealthCheckAt: null }} />);
    expect(screen.getByText('nie testowano')).toBeInTheDocument();
  });

  it('has no axe violations', async () => {
    const { container } = renderCard(<ConnectionCard connection={base} />);
    expect(await axe(container)).toHaveNoViolations();
  });
});
