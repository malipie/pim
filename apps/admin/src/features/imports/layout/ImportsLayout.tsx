import { useTranslation } from 'react-i18next';
import { NavLink, Outlet, useLocation } from 'react-router';

interface TabDef {
  value: string;
  to: string;
  labelKey: string;
}

const TABS: readonly TabDef[] = [
  { value: 'sessions', to: '/integrations/imports/sessions', labelKey: 'imports.tabs.sessions' },
  { value: 'profiles', to: '/integrations/imports/profiles', labelKey: 'imports.tabs.profiles' },
  { value: 'sources', to: '/integrations/imports/sources', labelKey: 'imports.tabs.sources' },
  { value: 'schedule', to: '/integrations/imports/schedule', labelKey: 'imports.tabs.schedule' },
] as const;

const WIZARD_FALLBACK_VALUE = 'sessions';

function activeTabFor(pathname: string): string {
  const match = TABS.find((tab) => pathname.startsWith(tab.to));
  return match?.value ?? WIZARD_FALLBACK_VALUE;
}

export function ImportsLayout() {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const activeTab = activeTabFor(pathname);

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="display text-[28px] font-semibold tracking-tight">
          {t('imports.tabs.section_title')}
        </h1>
        <p className="text-sm text-muted-foreground">{t('imports.tabs.section_subtitle')}</p>
      </header>
      <div className="flex gap-1 border-b" role="tablist" aria-label={t('imports.tabs.aria_label')}>
        {TABS.map((tab) => {
          const isActive = activeTab === tab.value;
          return (
            <NavLink
              key={tab.value}
              to={tab.to}
              role="tab"
              aria-selected={isActive}
              aria-controls={`imports-panel-${tab.value}`}
              className={
                isActive
                  ? 'border-accent-violet text-foreground -mb-px flex items-center border-b-2 px-4 py-2 text-sm font-medium'
                  : 'text-muted-foreground hover:text-foreground -mb-px flex items-center border-b-2 border-transparent px-4 py-2 text-sm'
              }
            >
              <span>{t(tab.labelKey)}</span>
            </NavLink>
          );
        })}
      </div>
      <div role="tabpanel" id={`imports-panel-${activeTab}`}>
        <Outlet />
      </div>
    </div>
  );
}
