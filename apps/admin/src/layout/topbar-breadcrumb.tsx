import { ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useLocation } from 'react-router';

const ROUTE_LABEL_KEYS: Array<{ match: RegExp; key: string }> = [
  { match: /^\/dashboard/, key: 'nav.dashboard' },
  { match: /^\/products/, key: 'nav.products' },
  { match: /^\/modeling/, key: 'nav.modeling' },
  { match: /^\/assets/, key: 'nav.multimedia' },
  { match: /^\/catalogs-pdf/, key: 'nav.catalogsPdf' },
  { match: /^\/integrations/, key: 'nav.integrations' },
  { match: /^\/settings/, key: 'nav.settings' },
];

export function TopbarBreadcrumb() {
  const { t } = useTranslation();
  const { pathname } = useLocation();

  const currentRoute = ROUTE_LABEL_KEYS.find(({ match }) => match.test(pathname));
  const currentLabel = currentRoute
    ? t(currentRoute.key)
    : t('topbar.workspace', { defaultValue: 'Workspace' });

  return (
    <nav
      aria-label={t('topbar.breadcrumb_aria', { defaultValue: 'Breadcrumb' })}
      className="flex items-center gap-1.5 text-sm"
    >
      <span className="text-muted-foreground">
        {t('topbar.workspace', { defaultValue: 'Workspace' })}
      </span>
      <ChevronRight className="size-3.5 text-muted-foreground/60" aria-hidden />
      <span className="font-medium text-foreground">{currentLabel}</span>
    </nav>
  );
}
