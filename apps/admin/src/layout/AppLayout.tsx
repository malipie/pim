import { Menu } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router';

import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

import { LanguageSwitcher } from './language-switcher';
import { NotificationsBell } from './notifications-bell';
import { SidebarNav } from './sidebar-nav';
import { UserMenu } from './user-menu';

export function AppLayout() {
  const { t } = useTranslation();
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-muted/30">
      <aside className="hidden w-60 shrink-0 border-r bg-background md:flex md:flex-col">
        <SidebarNav />
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 items-center gap-2 border-b bg-background px-3 md:px-6">
          <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
            <SheetTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                className="md:hidden"
                aria-label={t('app.toggle_nav', { defaultValue: 'Toggle navigation' })}
              >
                <Menu className="size-4" />
              </Button>
            </SheetTrigger>
            <SheetContent
              side="left"
              className="w-72 p-0"
              closeLabel={t('app.close', { defaultValue: 'Close' })}
            >
              <SheetTitle className="sr-only">{t('app.title')}</SheetTitle>
              <SidebarNav onNavigate={() => setMobileOpen(false)} />
            </SheetContent>
          </Sheet>

          <span className="text-sm font-medium text-muted-foreground md:hidden">
            {t('app.title')}
          </span>

          <div className="ml-auto flex items-center gap-2">
            <LanguageSwitcher />
            <NotificationsBell />
            <UserMenu />
          </div>
        </header>

        <main className="flex-1 overflow-auto p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
