import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { EmptyState } from '../empty-state';
import { KpiCard } from '../kpi-card';

describe('KpiCard', () => {
  it('renders label, value and sub-line', () => {
    render(<KpiCard label="Sesje" value="12 847" sub="✓0 ⚠1 ✗0" />);
    expect(screen.getByText('Sesje')).toBeInTheDocument();
    expect(screen.getByText('12 847')).toBeInTheDocument();
    expect(screen.getByText('✓0 ⚠1 ✗0')).toBeInTheDocument();
  });

  it('renders a trend sparkline only with 2+ points', () => {
    const { container, rerender } = render(<KpiCard label="X" value="1" trend={[1, 2, 3]} />);
    expect(container.querySelector('svg')).not.toBeNull();
    rerender(<KpiCard label="X" value="1" trend={[1]} />);
    expect(container.querySelector('svg')).toBeNull();
  });
});

describe('EmptyState', () => {
  it('renders title, description and the action slot', () => {
    render(
      <EmptyState
        title="Brak aktywnych eksportów"
        description="Uruchom nowy eksport, aby zobaczyć postęp."
        action={<button type="button">Nowy eksport</button>}
      />,
    );
    expect(screen.getByText('Brak aktywnych eksportów')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Nowy eksport' })).toBeInTheDocument();
  });
});
