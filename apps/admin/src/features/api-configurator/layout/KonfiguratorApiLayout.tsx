import { useTranslation } from 'react-i18next';
import { Outlet, useLocation, useNavigate } from 'react-router';

import { PillTabs } from '@/components/ui-v2/pill-tabs';

const BASE = '/integrations/api-configurator';

type ShellTab = 'connections' | 'producer' | 'monitor';

/**
 * APIC-P0-04 — shared shell for the Konfigurator API area (ADR-0022). Mirrors
 * the api-app.jsx prototype: a three-way split between the consumer side
 * (Połączenia), the producer side (Moje API — the existing ApiProfile screens),
 * and the sync Monitor. Pill tabs over an Outlet; the producer tab keeps the
 * existing `/integrations/api-configurator` routes untouched, the consumer hub
 * (P1-07) and monitor (P4-02) fill their placeholders next.
 */
export function KonfiguratorApiLayout() {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const navigate = useNavigate();

  const activeId: ShellTab = pathname.startsWith(`${BASE}/connections`)
    ? 'connections'
    : pathname.startsWith(`${BASE}/monitor`)
      ? 'monitor'
      : 'producer';

  return (
    <div className="space-y-5">
      <PillTabs
        ariaLabel={t('api_configurator.shell.tabs_aria')}
        activeId={activeId}
        onChange={(id) => {
          void navigate(id === 'producer' ? BASE : `${BASE}/${id}`);
        }}
        items={[
          { id: 'connections', label: t('api_configurator.shell.tabs.connections') },
          { id: 'producer', label: t('api_configurator.shell.tabs.producer') },
          { id: 'monitor', label: t('api_configurator.shell.tabs.monitor') },
        ]}
      />
      <Outlet />
    </div>
  );
}
