import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';

import { PageHeader } from '../page-header';

const ITEMS = [
  { label: 'Workspace', href: '/' },
  { label: 'Integracje', href: '/integrations' },
  { label: 'Eksporty' },
];

describe('PageHeader', () => {
  it('renders intermediate segments as links and the last as current page', () => {
    render(
      <MemoryRouter>
        <PageHeader items={ITEMS} />
      </MemoryRouter>,
    );
    expect(screen.getByRole('link', { name: 'Integracje' })).toHaveAttribute(
      'href',
      '/integrations',
    );
    const current = screen.getByText('Eksporty');
    expect(current).toHaveAttribute('aria-current', 'page');
    expect(current.closest('nav')).toHaveAttribute('aria-label', 'breadcrumb');
  });

  it('renders the actions slot', () => {
    render(
      <MemoryRouter>
        <PageHeader items={ITEMS} actions={<button type="button">Nowy eksport</button>} />
      </MemoryRouter>,
    );
    expect(screen.getByRole('button', { name: 'Nowy eksport' })).toBeInTheDocument();
  });
});
