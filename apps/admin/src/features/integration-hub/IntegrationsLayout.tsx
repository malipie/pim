import { useTranslation } from 'react-i18next';
import { NavLink, Outlet } from 'react-router';

import { cn } from '@/lib/utils';

interface TabDef {
  to: string;
  labelKey: string;
  enabled: boolean;
  /** Tooltip copy for disabled tabs. */
  comingSoonKey?: string;
}

const TABS: readonly TabDef[] = [
  { to: '/integrations/imports', labelKey: 'integrations.tabs.imports', enabled: true },
  {
    to: '/integrations/exports',
    labelKey: 'integrations.tabs.exports',
    enabled: false,
    comingSoonKey: 'integrations.coming_soon',
  },
  {
    to: '/integrations/connectors',
    labelKey: 'integrations.tabs.connectors',
    enabled: false,
    comingSoonKey: 'integrations.coming_soon',
  },
  {
    to: '/integrations/api-configurator',
    labelKey: 'integrations.tabs.api_configurator',
    enabled: true,
  },
] as const;

/**
 * IMP-09 (#450) + Publications/Integrations consolidation (PR follow-up #472).
 *
 * Top-level "Integracje" hub łączy 4 powierzchnie syndykacji danych:
 *   - Imports (zaimplementowane w epiku 0.13 / UI-09 — Imports MVP),
 *   - Exports (placeholder, epik 0.10 API Configurator),
 *   - Connectors (placeholder, BaseLinker/Shopify w Fazie 1 — epiki 0.8/0.9),
 *   - API Configurator (Profile API z VIEW-08 — drugi USP, epik 0.10).
 *
 * Wcześniej: top-level "Publikacje" (route /publications) + osobny
 * top-level "Integracje" (route /api-profiles dla Profile API). Operator
 * wskazał konfuzję — Profile API zostało zmigrowane do sub-tab
 * `api-configurator`, top-level "Publikacje" usunięty.
 */
export function IntegrationsLayout(): React.ReactElement {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="display text-[28px] font-semibold tracking-tight">
          {t('integrations.title', { defaultValue: 'Integracje' })}
        </h1>
        <p className="text-sm text-muted-foreground">
          {t('integrations.description', {
            defaultValue: 'Imports, exports, konektory i API Configurator',
          })}
        </p>
      </header>
      <div
        className="flex gap-1 border-b"
        role="tablist"
        aria-label={t('integrations.tabs_aria', { defaultValue: 'Sub-tabs Integracje' })}
      >
        {TABS.map((tab) => {
          const fallbackLabel = tab.to.replace('/integrations/', '');
          if (!tab.enabled) {
            return (
              <button
                key={tab.to}
                type="button"
                role="tab"
                aria-selected="false"
                aria-disabled="true"
                disabled
                title={
                  tab.comingSoonKey !== undefined
                    ? t(tab.comingSoonKey, { defaultValue: 'Coming with epik UI-04' })
                    : undefined
                }
                className="-mb-px flex cursor-not-allowed items-center border-b-2 border-transparent px-4 py-2 text-sm text-muted-foreground/60"
              >
                {t(tab.labelKey, { defaultValue: fallbackLabel })}
              </button>
            );
          }
          return (
            <NavLink
              key={tab.to}
              to={tab.to}
              role="tab"
              className={({ isActive }) =>
                cn(
                  '-mb-px flex items-center border-b-2 px-4 py-2 text-sm',
                  isActive
                    ? 'border-accent-violet text-foreground font-medium'
                    : 'border-transparent text-muted-foreground hover:text-foreground',
                )
              }
            >
              {t(tab.labelKey, { defaultValue: fallbackLabel })}
            </NavLink>
          );
        })}
      </div>
      <div>
        <Outlet />
      </div>
    </div>
  );
}
