import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { DashboardMockBanner } from '../DashboardMockBanner';

describe('DashboardMockBanner', () => {
  it('renders an explicit page-level demo-data notice', () => {
    render(<DashboardMockBanner />);

    const banner = screen.getByRole('status');
    expect(banner).toBeInTheDocument();
    expect(banner).toHaveTextContent('Część widżetów pokazuje dane demonstracyjne');
    // Names the live blocks so the notice is not mistaken for "everything is fake".
    expect(banner).toHaveTextContent(/KPI/);
    expect(banner).toHaveTextContent(/Kompletność/);
  });
});
