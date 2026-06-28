import { render, screen } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import { describe, expect, it } from 'vitest';

import { ConnectionCard, type ConnectionRow } from '../ConnectionCard';

expect.extend(toHaveNoViolations);

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
    render(<ConnectionCard connection={base} />);

    expect(screen.getByText('Shopify EU')).toBeInTheDocument();
    expect(screen.getByText('shopify-eu')).toBeInTheDocument();
    expect(screen.getByText('https://eu.shopify.example/api')).toBeInTheDocument();
    // status pill label resolves through i18n (pl is the test default)
    expect(screen.getByText('aktywne')).toBeInTheDocument();
  });

  it('shows the never-tested fallback when no health check ran', () => {
    render(<ConnectionCard connection={{ ...base, lastHealthCheckAt: null }} />);
    expect(screen.getByText('nie testowano')).toBeInTheDocument();
  });

  it('has no axe violations', async () => {
    const { container } = render(<ConnectionCard connection={base} />);
    expect(await axe(container)).toHaveNoViolations();
  });
});
