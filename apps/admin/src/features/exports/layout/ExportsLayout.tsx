import { useTranslation } from 'react-i18next';
import { NavLink, Outlet, useLocation } from 'react-router';

interface TabDef {
  value: string;
  to: string;
  labelKey: string;
}

const TABS: readonly TabDef[] = [
  {
    value: 'sessions',
    to: '/integrations/exports/sessions',
    labelKey: 'exports.tabs.sessions',
  },
  {
    value: 'profiles',
    to: '/integrations/exports/profiles',
    labelKey: 'exports.tabs.profiles',
  },
] as const;

const DEFAULT_TAB = 'sessions';

function activeTabFor(pathname: string): string {
  const match = TABS.find((tab) => pathname.startsWith(tab.to));
  return match?.value ?? DEFAULT_TAB;
}

/**
 * EXP-09 (#588) — Exports hub layout (analog do ImportsLayout VIEW-IMP-00).
 *
 * Two tabs in MVP:
 *   - Recent exports (lista sesji per user, EXP-13)
 *   - Saved profiles (CRUD profili per user, EXP-14)
 *
 * "New export" lives at `/integrations/exports/new` as a stand-alone
 * full-page form (EXP-12). It's reachable from the tab strip via a CTA
 * button rather than another tab so the hub stays clean (PRD §13.1).
 */
export function ExportsLayout(): React.ReactElement {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const activeTab = activeTabFor(pathname);

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="display text-[28px] font-semibold tracking-tight">
          {t('exports.section_title', { defaultValue: 'Eksporty' })}
        </h1>
        <p className="text-sm text-muted-foreground">
          {t('exports.section_subtitle', {
            defaultValue:
              'Eksportuj produkty do XLSX / CSV — z modalem z listy lub z dedykowanej formy.',
          })}
        </p>
      </header>
      <div
        className="flex items-center justify-between gap-4 border-b"
        role="tablist"
        aria-label={t('exports.tabs.aria_label', { defaultValue: 'Zakładki eksportu' })}
      >
        <div className="flex gap-1">
          {TABS.map((tab) => {
            const isActive = activeTab === tab.value;
            return (
              <NavLink
                key={tab.value}
                to={tab.to}
                role="tab"
                aria-selected={isActive}
                className={({ isActive: navActive }) =>
                  [
                    'inline-flex items-center border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                    navActive || isActive
                      ? 'border-foreground text-foreground'
                      : 'border-transparent text-muted-foreground hover:border-muted-foreground/40 hover:text-foreground',
                  ].join(' ')
                }
              >
                {t(tab.labelKey)}
              </NavLink>
            );
          })}
        </div>
        <NavLink
          to="/integrations/exports/new"
          className="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90"
        >
          {t('exports.tabs.new_cta', { defaultValue: 'Nowy eksport' })}
        </NavLink>
      </div>
      <Outlet />
    </div>
  );
}

export default ExportsLayout;
