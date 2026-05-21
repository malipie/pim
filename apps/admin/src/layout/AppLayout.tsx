import { Menu } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router';

import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { TooltipProvider } from '@/components/ui/tooltip';

import { AppFooter } from './app-footer';
import { AuditLogStatus } from './audit-log-status';
import { BulkSessionsPopover } from './bulk-sessions-popover';
import { LanguageSwitcher } from './language-switcher';
import { NotificationsBell } from './notifications-bell';
import { SidebarNav } from './sidebar-nav';
import { TopbarBreadcrumb } from './topbar-breadcrumb';

export function AppLayout() {
  const { t } = useTranslation();
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <TooltipProvider delayDuration={150}>
      <div className="flex min-h-screen bg-[#fafaf9]">
        <aside className="sticky top-0 hidden h-screen w-[260px] shrink-0 flex-col px-4 py-5 md:flex">
          <SidebarNav />
        </aside>

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-zinc-100 bg-white/80 px-3 backdrop-blur md:px-6">
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
                className="w-[260px] bg-[#fafaf9] p-4"
                closeLabel={t('app.close', { defaultValue: 'Close' })}
              >
                <SheetTitle className="sr-only">{t('app.title')}</SheetTitle>
                <SidebarNav onNavigate={() => setMobileOpen(false)} />
              </SheetContent>
            </Sheet>

            <span className="text-sm font-medium text-muted-foreground md:hidden">
              {t('app.title')}
            </span>

            <div className="hidden md:block">
              <TopbarBreadcrumb />
            </div>

            <div className="ml-auto flex items-center gap-2">
              <LanguageSwitcher />
              <BulkSessionsPopover />
              <NotificationsBell />
              <div className="hidden md:block">
                <AuditLogStatus />
              </div>
            </div>
          </header>

          <main className="flex-1 overflow-auto p-4 md:p-6">
            <Outlet />
          </main>

          <AppFooter />
        </div>
      </div>
    </TooltipProvider>
  );
}
