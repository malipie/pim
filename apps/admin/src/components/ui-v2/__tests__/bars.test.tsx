import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ProgressBar } from '../progress-bar';
import { ResultBar } from '../result-bar';

describe('ResultBar', () => {
  it('splits the bar proportionally and labels it for screen readers', () => {
    render(<ResultBar ok={50} warn={25} err={25} />);
    const bar = screen.getByRole('img');
    expect(bar.getAttribute('aria-label')).toContain('50');
    const segments = bar.children;
    expect((segments[0] as HTMLElement).style.width).toBe('50%');
    expect((segments[1] as HTMLElement).style.width).toBe('25%');
  });

  it('renders mono counts when showCounts is set', () => {
    render(<ResultBar ok={1} warn={2} err={3} showCounts />);
    expect(screen.getByText('✗3')).toBeInTheDocument();
  });
});

describe('ProgressBar', () => {
  it('clamps the value into 0..1 and exposes progressbar semantics', () => {
    render(<ProgressBar value={1.4} ariaLabel="Postęp eksportu" />);
    const bar = screen.getByRole('progressbar', { name: 'Postęp eksportu' });
    expect(bar).toHaveAttribute('aria-valuenow', '100');
  });

  it('drops the shimmer when not animated', () => {
    const { container } = render(<ProgressBar value={0.5} animated={false} />);
    expect(container.querySelector('.shimmer')).toBeNull();
  });
});
