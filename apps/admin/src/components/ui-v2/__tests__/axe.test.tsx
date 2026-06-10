import { render } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';

import { EmptyState } from '../empty-state';
import { FormatPill } from '../format-pill';
import { HealthDot } from '../health-dot';
import { KpiCard } from '../kpi-card';
import { ModeBadge } from '../mode-badge';
import { PageHeader } from '../page-header';
import { PillTabs } from '../pill-tabs';
import { ProgressBar } from '../progress-bar';
import { ResultBar } from '../result-bar';
import { SelectableCard, SelectableCardGroup } from '../selectable-card';
import { StatusPill } from '../status-pill';
import { WizardStepper } from '../wizard-stepper';

expect.extend(toHaveNoViolations);

/**
 * Demo-page composition of every ui-v2 primitive — the axe acceptance
 * gate from EXR-02 (test-only render instead of a dev route).
 */
function DemoPage() {
  return (
    <MemoryRouter>
      <main>
        <PageHeader
          items={[{ label: 'Workspace', href: '/' }, { label: 'Eksporty' }]}
          actions={<button type="button">Nowy eksport</button>}
        />
        <PillTabs
          ariaLabel="Sekcje eksportów"
          activeId="sessions"
          onChange={() => {}}
          items={[
            { id: 'sessions', label: 'Sesje', count: 2 },
            { id: 'targets', label: 'Cele', disabled: true },
          ]}
        />
        <KpiCard label="Sesje 30 dni" value="12 847" sub="✓12 ⚠1 ✗0" trend={[1, 4, 2, 8]} />
        <StatusPill variant="running" />
        <ResultBar ok={10} warn={2} err={1} showCounts />
        <ProgressBar value={0.4} ariaLabel="Postęp eksportu" />
        <ModeBadge mode="UPDATE" />
        <FormatPill format="csv" />
        <HealthDot health="ok" label="Stan integracji: ok" />
        <SelectableCardGroup ariaLabel="Typ encji">
          <SelectableCard title="Produkty" description="Pełny konfigurator" selected />
          <SelectableCard title="Usługi" disabled />
        </SelectableCardGroup>
        <WizardStepper
          current={1}
          steps={[
            { id: 'type', label: 'Typ' },
            { id: 'scope', label: 'Zakres' },
          ]}
        />
        <EmptyState title="Brak aktywnych eksportów" description="Uruchom nowy eksport." />
      </main>
    </MemoryRouter>
  );
}

describe('ui-v2 a11y', () => {
  it('has no axe violations on the demo composition', async () => {
    const { container } = render(<DemoPage />);
    expect(await axe(container)).toHaveNoViolations();
  });
});
