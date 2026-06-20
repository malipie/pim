import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';

import type { DashboardCompleteness } from '../../use-dashboard-completeness';
import { CompletenessMetrics } from '../CompletenessMetrics';

const mockUseDashboardCompleteness = vi.fn();

vi.mock('../../use-dashboard-completeness', async () => {
  const actual = await vi.importActual<typeof import('../../use-dashboard-completeness')>(
    '../../use-dashboard-completeness',
  );
  return {
    ...actual,
    useDashboardCompleteness: () => mockUseDashboardCompleteness(),
  };
});

function renderWidget() {
  return render(
    <TooltipProvider>
      <CompletenessMetrics />
    </TooltipProvider>,
  );
}

afterEach(() => {
  mockUseDashboardCompleteness.mockReset();
});

describe('CompletenessMetrics', () => {
  it('renders the live overall ring from real bucket counts', () => {
    const data: DashboardCompleteness = {
      total: 6913,
      publishReady: 302,
      publishReadyPct: 4,
      buckets: [
        { gte: 25, count: 6911 },
        { gte: 50, count: 1464 },
        { gte: 80, count: 302 },
        { gte: 100, count: 100 },
      ],
    };
    mockUseDashboardCompleteness.mockReturnValue({ data, isPending: false });

    renderWidget();

    // Live percentage rendered in the overall ring.
    expect(screen.getByText('4%')).toBeInTheDocument();
    // Real publish-ready stat line with thin-space grouped totals.
    expect(screen.getByText(/302 z 6\s?913 ≥ 80%/)).toBeInTheDocument();
  });

  it('falls back to the mock overall slice when the count query fails', () => {
    mockUseDashboardCompleteness.mockReturnValue({ data: null, isPending: false });

    renderWidget();

    // Mock overall percent (87%) from mock-data, no publish-ready stat line.
    expect(screen.getByText('87%')).toBeInTheDocument();
    expect(screen.queryByText(/≥ 80%/)).toBeNull();
  });

  it('shows a pending placeholder before the first count resolves', () => {
    mockUseDashboardCompleteness.mockReturnValue({ data: undefined, isPending: true });

    renderWidget();

    expect(screen.getByText('…')).toBeInTheDocument();
  });
});
