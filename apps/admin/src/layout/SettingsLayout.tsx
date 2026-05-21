import {
  ArrowRight,
  Boxes,
  Building2,
  CreditCard,
  FileLock2,
  Globe,
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

import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface SettingsNavItem {
  to: string;
  icon: LucideIcon;
  labelKey: string;
  /**
   * Workspace primary entries (Users, Roles) get a small accent dot next to
   * the label when inactive — matches `page.jsx` `primary: true` flag.
   */
  primary?: boolean;
  /** TENANT scope items only Tenant Owner can manage — renders amber `owner` badge. */
  ownerOnly?: boolean;
}

interface SettingsNavGroup {
  id: 'account' | 'workspace' | 'tenant';
  labelKey: string;
  items: readonly SettingsNavItem[];
}

const NAV_GROUPS: readonly SettingsNavGroup[] = [
  {
    id: 'account',
    labelKey: 'settings.nav_group_account',
    items: [{ to: '/settings/security', icon: KeyRound, labelKey: 'settings.nav.security' }],
  },
  {
    id: 'workspace',
    labelKey: 'settings.nav_group_workspace',
    items: [
      { to: '/settings/users', icon: Users, labelKey: 'settings.nav.users', primary: true },
      {
        to: '/settings/roles',
        icon: ShieldCheck,
        labelKey: 'settings.nav.roles',
        primary: true,
      },
      { to: '/settings/api-tokens', icon: Key, labelKey: 'settings.nav.api_tokens' },
      { to: '/settings/sso', icon: Globe, labelKey: 'settings.nav.sso' },
      { to: '/settings/menu', icon: MenuIcon, labelKey: 'settings.nav.menu' },
      { to: '/settings/locales', icon: Languages, labelKey: 'settings.nav.locales' },
      { to: '/settings/channels', icon: Share2, labelKey: 'settings.nav.channels' },
      { to: '/settings/ai', icon: Sparkles, labelKey: 'settings.nav.ai' },
    ],
  },
  {
    id: 'tenant',
    labelKey: 'settings.nav_group_tenant',
    items: [
      { to: '/settings/tenant', icon: Building2, labelKey: 'settings.nav.tenant' },
      {
        to: '/settings/billing',
        icon: CreditCard,
        labelKey: 'settings.nav.billing',
        ownerOnly: true,
      },
    ],
  },
] as const;

const baseItemClass =
  'group flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] font-medium transition text-left';

const linkClass = ({ isActive }: { isActive: boolean }): string =>
  cn(baseItemClass, isActive ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-100');

interface NavEntryProps {
  item: SettingsNavItem;
}

function NavEntry({ item }: NavEntryProps) {
  const { t } = useTranslation();
  const Icon = item.icon;

  return (
    <NavLink to={item.to} className={linkClass} end={false}>
      {({ isActive }) => (
        <>
          <Icon className={cn('size-4', isActive ? 'text-white/80' : 'text-zinc-400')} />
          <span className="flex flex-1 items-center gap-1.5">
            {t(item.labelKey)}
            {item.primary && !isActive ? (
              <span className="size-1.5 rounded-full bg-zinc-900/40" aria-hidden />
            ) : null}
          </span>
          {item.ownerOnly && !isActive ? (
            <Tooltip>
              <TooltipTrigger asChild>
                <span className="text-[9.5px] font-medium text-amber-700">
                  {t('settings.owner_only_badge', { defaultValue: 'owner' })}
                </span>
              </TooltipTrigger>
              <TooltipContent side="right">
                {t('settings.owner_only_tooltip', {
                  defaultValue: 'Tenant Owner only',
                })}
              </TooltipContent>
            </Tooltip>
          ) : null}
        </>
      )}
    </NavLink>
  );
}

function AuditCard() {
  const { t } = useTranslation();
  const tooltip = t('settings.audit_card_coming_soon_tooltip', {
    defaultValue: 'Audit log UI lands in Phase 7 (#724).',
  });

  return (
    <div className="mt-6 rounded-2xl border border-zinc-200 bg-zinc-50 p-3">
      <div className="mb-1 flex items-center gap-1.5 text-[10.5px] font-medium uppercase tracking-wider text-zinc-500">
        <FileLock2 className="size-3 text-zinc-400" aria-hidden />
        {t('settings.audit_card_title', { defaultValue: 'Audyt zmian' })}
      </div>
      <p className="text-[11.5px] leading-snug text-zinc-700">
        {t('settings.audit_card_body', {
          defaultValue: 'Każda zmiana w Ustawieniach jest logowana z user_id, IP, old/new value.',
        })}
      </p>
      <Tooltip>
        <TooltipTrigger asChild>
          <button
            type="button"
            disabled
            className="mt-2 inline-flex cursor-not-allowed items-center gap-1 text-[11.5px] font-medium text-zinc-500"
          >
            {t('settings.audit_card_link', { defaultValue: 'Zobacz audit log' })}
            <ArrowRight className="size-3" aria-hidden />
          </button>
        </TooltipTrigger>
        <TooltipContent side="right">{tooltip}</TooltipContent>
      </Tooltip>
    </div>
  );
}

export function SettingsLayout() {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-6 md:flex-row md:gap-8">
      <aside
        className="md:sticky md:top-20 md:h-fit md:w-[244px] md:shrink-0"
        aria-label={t('settings.subnav_aria')}
      >
        <header className="mb-4 px-3">
          <div className="text-[10.5px] font-medium uppercase tracking-wider text-zinc-400">
            <Boxes className="mr-1 inline size-3" aria-hidden />
            {t('settings.page_title')}
          </div>
          <h1 className="mt-1 text-[15px] font-semibold tracking-tight text-zinc-900">
            {t('settings.page_subtitle', {
              defaultValue: 'Ustawienia · administracja workspace',
            })}
          </h1>
        </header>

        <nav className="space-y-5">
          {NAV_GROUPS.map((group) => (
            <div key={group.id}>
              <div className="mb-1 px-3 text-[10.5px] font-medium uppercase tracking-wider text-zinc-400">
                {t(group.labelKey)}
              </div>
              <div className="flex flex-col gap-0.5">
                {group.items.map((item) => (
                  <NavEntry key={item.to} item={item} />
                ))}
              </div>
            </div>
          ))}
        </nav>

        <AuditCard />
      </aside>

      <section className="min-w-0 flex-1">
        <Outlet />
      </section>
    </div>
  );
}
