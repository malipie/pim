import { useGetIdentity, useLogout } from '@refinedev/core';
import { Boxes, LogOut, Package } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink, Outlet } from 'react-router';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface Identity {
  name: string;
}

export function AppLayout() {
  const { t } = useTranslation();
  const { mutate: logout, isPending: loggingOut } = useLogout();
  const { data: identity } = useGetIdentity<Identity>();

  return (
    <div className="flex min-h-screen bg-muted/30">
      <aside className="hidden w-60 shrink-0 border-r bg-background md:flex md:flex-col">
        <div className="flex h-14 items-center gap-2 border-b px-4">
          <Boxes className="size-5 text-primary" />
          <span className="font-semibold tracking-tight">{t('app.title')}</span>
        </div>
        <nav className="flex flex-1 flex-col gap-1 p-3">
          <NavLink
            to="/products"
            className={({ isActive }) =>
              cn(
                'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                isActive
                  ? 'bg-secondary text-secondary-foreground'
                  : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
              )
            }
          >
            <Package className="size-4" />
            {t('nav.products')}
          </NavLink>
        </nav>
      </aside>
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 items-center justify-between border-b bg-background px-4 md:px-6">
          <span className="text-sm text-muted-foreground md:hidden">{t('app.title')}</span>
          <div className="ml-auto flex items-center gap-3">
            {identity?.name ? (
              <span className="text-sm text-muted-foreground">{identity.name}</span>
            ) : null}
            <Button variant="ghost" size="sm" onClick={() => logout()} disabled={loggingOut}>
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
