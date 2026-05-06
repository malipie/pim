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
  { to: '/publications/imports', labelKey: 'publications.tabs.imports', enabled: true },
  {
    to: '/publications/exports',
    labelKey: 'publications.tabs.exports',
    enabled: false,
    comingSoonKey: 'publications.coming_soon',
  },
  {
    to: '/publications/integrations',
    labelKey: 'publications.tabs.integrations',
    enabled: false,
    comingSoonKey: 'publications.coming_soon',
  },
  {
    to: '/publications/api-configurator',
    labelKey: 'publications.tabs.api_configurator',
    enabled: false,
    comingSoonKey: 'publications.coming_soon',
  },
] as const;

/**
 * IMP-09 (#450) — top-level "Publikacje" shell. Imports is the only
 * sub-tab implemented in this slice; the other three sit behind a
 * disabled state with a tooltip until epik UI-04 fills them in.
 */
export function PublicationsLayout(): React.ReactElement {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="display text-[28px] font-semibold tracking-tight">
          {t('publications.title', { defaultValue: 'Publikacje' })}
        </h1>
        <p className="text-sm text-muted-foreground">
          {t('publications.description', {
            defaultValue: 'Imports, exports i integracje katalogu',
          })}
        </p>
      </header>
      <div
        className="flex gap-1 border-b"
        role="tablist"
        aria-label={t('publications.tabs_aria', { defaultValue: 'Sub-tabs Publikacje' })}
      >
        {TABS.map((tab) => {
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
                {t(tab.labelKey, { defaultValue: tab.to.replace('/publications/', '') })}
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
              {t(tab.labelKey, { defaultValue: tab.to.replace('/publications/', '') })}
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
