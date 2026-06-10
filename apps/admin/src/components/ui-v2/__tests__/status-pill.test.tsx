import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { exportStatusToPillVariant } from '../status-maps';
import { StatusPill } from '../status-pill';

describe('StatusPill', () => {
  it('renders the translated label per variant', () => {
    render(<StatusPill variant="success" />);
    expect(screen.getByText('sukces')).toBeInTheDocument();
  });

  it('pulses the dot only for the running variant', () => {
    const { container, rerender } = render(<StatusPill variant="running" />);
    expect(container.querySelector('.pulse-dot')).not.toBeNull();
    rerender(<StatusPill variant="error" />);
    expect(container.querySelector('.pulse-dot')).toBeNull();
  });

  it('accepts a custom label override', () => {
    render(<StatusPill variant="partial" label="50/100" />);
    expect(screen.getByText('50/100')).toBeInTheDocument();
  });
});

describe('exportStatusToPillVariant', () => {
  it('maps backend ExportStatus values onto pill variants', () => {
    expect(exportStatusToPillVariant('done')).toBe('success');
    expect(exportStatusToPillVariant('running')).toBe('running');
    expect(exportStatusToPillVariant('error')).toBe('error');
    expect(exportStatusToPillVariant('pending')).toBe('queued');
    expect(exportStatusToPillVariant('cancelled')).toBe('cancelled');
  });
});
