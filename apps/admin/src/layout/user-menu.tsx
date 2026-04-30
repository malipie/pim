import { useGetIdentity, useLogout } from '@refinedev/core';
import { LogOut, UserCircle2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Identity {
  id: string;
  name: string;
  email: string;
  roles: string[];
  tenant: { id: string; code: string; name: string } | null;
  lastLoginAt: string | null;
}

export function UserMenu() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { mutate: logout, isPending: loggingOut } = useLogout();
  const { data: identity } = useGetIdentity<Identity>();

  const handleLogout = (): void => {
    logout(undefined, {
      // Plain react-router-7 here — Refine's routerProvider would handle the
      // redirect for us; we navigate manually so logout actually leaves the
      // protected layout.
      onSuccess: () => navigate('/login', { replace: true }),
    });
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="sm"
          aria-label={t('user_menu.aria_label', { defaultValue: 'User menu' })}
        >
          <UserCircle2 className="size-4" />
          <span className="hidden sm:inline">{identity?.name ?? t('user_menu.account')}</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        {identity ? (
          <>
            <DropdownMenuLabel className="space-y-0.5">
              <span className="block text-sm font-medium text-foreground">{identity.name}</span>
              <span className="block text-xs font-normal text-muted-foreground">
                {identity.email}
              </span>
            </DropdownMenuLabel>
            {identity.tenant ? (
              <DropdownMenuLabel className="text-xs">
                {t('user_menu.tenant', { defaultValue: 'Tenant' })}: {identity.tenant.name}
              </DropdownMenuLabel>
            ) : null}
            <DropdownMenuSeparator />
          </>
        ) : null}
        <DropdownMenuItem onClick={handleLogout} disabled={loggingOut}>
          <LogOut className="size-4" />
          <span>{t('app.logout')}</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
