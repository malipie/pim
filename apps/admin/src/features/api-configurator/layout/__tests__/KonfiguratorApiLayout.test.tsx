import { render, screen } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import { MemoryRouter, Route, Routes } from 'react-router';
import { describe, expect, it } from 'vitest';

import { ConnectionsHubPlaceholder } from '../../consumer/ConnectionsHubPlaceholder';
import { ApiMonitorPlaceholder } from '../../monitor/ApiMonitorPlaceholder';
import { KonfiguratorApiLayout } from '../KonfiguratorApiLayout';

expect.extend(toHaveNoViolations);

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route element={<KonfiguratorApiLayout />}>
          <Route
            path="/integrations/api-configurator/connections"
            element={<ConnectionsHubPlaceholder />}
          />
          <Route
            path="/integrations/api-configurator/monitor"
            element={<ApiMonitorPlaceholder />}
          />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe('KonfiguratorApiLayout', () => {
  it('renders the three shell tabs and the connections placeholder', () => {
    renderAt('/integrations/api-configurator/connections');

    expect(screen.getByRole('tab', { name: 'Połączenia' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Moje API' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Monitor' })).toBeInTheDocument();
    expect(screen.getByText('Połączenia — wkrótce')).toBeInTheDocument();
  });

  it('routes the monitor tab to its placeholder', () => {
    renderAt('/integrations/api-configurator/monitor');
    expect(screen.getByText('Monitor synchronizacji — wkrótce')).toBeInTheDocument();
  });

  it('has no axe violations', async () => {
    const { container } = renderAt('/integrations/api-configurator/connections');
    expect(await axe(container)).toHaveNoViolations();
  });
});
