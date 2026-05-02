import { History, Menu } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router';
import { Button } from '@/components/ui/button';
import { MockBadge } from '@/components/ui/mock-badge';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

import { AgentSearch } from './agent-search';
import { LanguageSwitcher } from './language-switcher';
import { NotificationsBell } from './notifications-bell';
import { SidebarNav } from './sidebar-nav';
import { TopbarBreadcrumb } from './topbar-breadcrumb';

export function AppLayout() {
  const { t } = useTranslation();
  const [mobileOpen, setMobileOpen] = useState(false);

  const auditTooltip = t('topbar.audit_log_tooltip', {
    defaultValue: 'MOCK · Audit log wymaga endpointu BE',
  });

  return (
    <TooltipProvider delayDuration={150}>
      <div className="flex min-h-screen bg-muted/30">
        <aside className="hidden w-60 shrink-0 border-r bg-background md:flex md:flex-col">
          <SidebarNav />
        </aside>

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="flex h-14 items-center gap-3 border-b bg-background px-3 md:px-6">
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

            <div className="hidden md:block">
              <TopbarBreadcrumb />
            </div>

            <div className="ml-auto flex items-center gap-2">
              <div className="hidden md:block">
                <AgentSearch />
              </div>
              <LanguageSwitcher />
              <NotificationsBell />
              <Tooltip>
                <TooltipTrigger asChild>
                  <span className="relative inline-flex">
                    <Button
                      variant="ghost"
                      size="icon"
                      disabled
                      aria-label={t('topbar.audit_log', { defaultValue: 'Audit log' })}
                    >
                      <History className="size-4" />
                    </Button>
                    <MockBadge tooltip={auditTooltip} className="absolute -right-1 -top-1" />
                  </span>
                </TooltipTrigger>
                <TooltipContent>{auditTooltip}</TooltipContent>
              </Tooltip>
            </div>
          </header>

          <main className="flex-1 overflow-auto p-4 md:p-6">
            <Outlet />
          </main>
        </div>
      </div>
    </TooltipProvider>
  );
}
