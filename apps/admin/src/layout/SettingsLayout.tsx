import {
  Building2,
  CreditCard,
  Key,
  KeyRound,
  Languages,
  type LucideIcon,
  Menu as MenuIcon,
  Share2,
  ShieldCheck,
  Sparkles,
  Users,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink, Outlet } from 'react-router';

import { cn } from '@/lib/utils';

interface SettingsNavItem {
  to: string;
  icon: LucideIcon;
  label: string;
}

const SETTINGS_NAV: readonly SettingsNavItem[] = [
  { to: '/settings/menu', icon: MenuIcon, label: 'settings.nav.menu' },
  { to: '/settings/locales', icon: Languages, label: 'settings.nav.locales' },
  { to: '/settings/channels', icon: Share2, label: 'settings.nav.channels' },
  { to: '/settings/users', icon: Users, label: 'settings.nav.users' },
  { to: '/settings/roles', icon: ShieldCheck, label: 'settings.nav.roles' },
  { to: '/settings/api-tokens', icon: Key, label: 'settings.nav.api_tokens' },
  { to: '/settings/security', icon: KeyRound, label: 'settings.nav.security' },
  { to: '/settings/tenant', icon: Building2, label: 'settings.nav.tenant' },
  { to: '/settings/billing', icon: CreditCard, label: 'settings.nav.billing' },
  { to: '/settings/ai', icon: Sparkles, label: 'settings.nav.ai' },
] as const;

const linkClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'group relative flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
    isActive
      ? 'bg-accent-violet/10 text-foreground before:absolute before:inset-y-1 before:left-0 before:w-0.5 before:rounded-r before:bg-accent-violet'
      : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
  );

export function SettingsLayout() {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-6 md:flex-row md:gap-8">
      <aside
        className="md:sticky md:top-6 md:h-fit md:w-64 md:shrink-0"
        aria-label={t('settings.subnav_aria')}
      >
        <header className="mb-3 px-3">
          <h1 className="display text-lg font-semibold tracking-tight">
            {t('settings.page_title')}
          </h1>
        </header>
        <nav className="flex flex-col gap-1">
          {SETTINGS_NAV.map((item) => (
            <NavLink key={item.to} to={item.to} className={linkClass} end={false}>
              <item.icon className="size-4" />
              <span className="flex-1">{t(item.label)}</span>
            </NavLink>
          ))}
        </nav>
      </aside>
      <section className="min-w-0 flex-1">
        <Outlet />
      </section>
    </div>
  );
}
