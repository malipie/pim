import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { FormatPill } from '../format-pill';
import { HealthDot } from '../health-dot';
import { ModeBadge } from '../mode-badge';
import { Sparkline } from '../sparkline';

describe('ModeBadge', () => {
  it('renders the mode code with a per-mode tint', () => {
    render(<ModeBadge mode="UPDATE" />);
    expect(screen.getByText('UPDATE')).toHaveClass('bg-sky-50');
  });

  it('falls back to the neutral tint for unknown modes', () => {
    render(<ModeBadge mode="SOMETHING" />);
    expect(screen.getByText('SOMETHING')).toHaveClass('bg-zinc-100');
  });
});

describe('FormatPill', () => {
  it('uppercases the format code', () => {
    render(<FormatPill format="xlsx" />);
    expect(screen.getByText('XLSX')).toHaveClass('bg-emerald-50');
  });
});

describe('HealthDot', () => {
  it('exposes an accessible label when given one', () => {
    render(<HealthDot health="ok" label="Shopify: ok" />);
    expect(screen.getByRole('img', { name: 'Shopify: ok' })).toHaveClass('bg-emerald-500');
  });

  it('is aria-hidden without a label', () => {
    const { container } = render(<HealthDot health="off" />);
    expect(container.firstElementChild).toHaveAttribute('aria-hidden', 'true');
  });
});

describe('Sparkline', () => {
  it('renders nothing for empty data', () => {
    const { container } = render(<Sparkline data={[]} />);
    expect(container.firstElementChild).toBeNull();
  });

  it('renders an svg path for data points', () => {
    const { container } = render(<Sparkline data={[1, 5, 3]} />);
    expect(container.querySelectorAll('path')).toHaveLength(2);
  });
});
