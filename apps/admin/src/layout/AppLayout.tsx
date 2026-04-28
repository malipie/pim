import { useGetIdentity, useLogout } from '@refinedev/core';
import {
  Boxes,
  FolderTree,
  Image,
  Layers,
  ListTree,
  LogOut,
  type LucideIcon,
  Package,
  Radio,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink, Outlet, useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface Identity {
  name: string;
}

interface NavItem {
  to: string;
  icon: LucideIcon;
  label: string;
  comingSoon?: boolean;
}

// Sidebar entries land here as the corresponding admin resources arrive in
// epic 0.6 (#54-#62). The `comingSoon` flag drives a muted visual state — the
// route still works and renders <ComingSoon /> with a link to the tracking
// issue, so users get a deterministic "not yet" rather than a 404.
const NAV_ITEMS: NavItem[] = [
  { to: '/products', icon: Package, label: 'nav.products' },
  { to: '/attributes', icon: Layers, label: 'nav.attributes', comingSoon: true },
  { to: '/object-types', icon: ListTree, label: 'nav.object_types', comingSoon: true },
  { to: '/categories', icon: FolderTree, label: 'nav.categories', comingSoon: true },
  { to: '/assets', icon: Image, label: 'nav.assets', comingSoon: true },
  { to: '/channels', icon: Radio, label: 'nav.channels', comingSoon: true },
];

export function AppLayout() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { mutate: logout, isPending: loggingOut } = useLogout();
  const { data: identity } = useGetIdentity<Identity>();

  const handleLogout = () => {
    logout(undefined, {
      // Plain react-router-7 here — Refine's routerProvider would handle the
      // redirect for us; we navigate manually so logout actually leaves the
      // protected layout.
      onSuccess: () => navigate('/login', { replace: true }),
    });
  };

  return (
    <div className="flex min-h-screen bg-muted/30">
      <aside className="hidden w-60 shrink-0 border-r bg-background md:flex md:flex-col">
        <div className="flex h-14 items-center gap-2 border-b px-4">
          <Boxes className="size-5 text-primary" />
          <span className="font-semibold tracking-tight">{t('app.title')}</span>
        </div>
        <nav className="flex flex-1 flex-col gap-1 p-3">
          {NAV_ITEMS.map(({ to, icon: Icon, label, comingSoon }) => (
            <NavLink
              key={to}
              to={to}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-secondary text-secondary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                )
              }
            >
              <Icon className="size-4" />
              <span className="flex-1">{t(label)}</span>
              {comingSoon ? (
                <span className="rounded bg-muted px-1.5 py-0.5 text-xs uppercase text-muted-foreground">
                  {t('nav.soon')}
                </span>
              ) : null}
            </NavLink>
          ))}
        </nav>
      </aside>
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 items-center justify-between border-b bg-background px-4 md:px-6">
          <span className="text-sm text-muted-foreground md:hidden">{t('app.title')}</span>
          <div className="ml-auto flex items-center gap-3">
            {identity?.name ? (
              <span className="text-sm text-muted-foreground">{identity.name}</span>
            ) : null}
            <Button variant="ghost" size="sm" onClick={handleLogout} disabled={loggingOut}>
              <LogOut className="size-4" />
              {t('app.logout')}
            </Button>
          </div>
        </header>
        <main className="flex-1 overflow-auto p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
