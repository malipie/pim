import { useGetIdentity, useLogout } from '@refinedev/core';
import { LogOut, Settings as SettingsIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

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

function initials(name: string | undefined): string {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  const first = parts[0] ?? '';
  if (parts.length === 1) return first.slice(0, 2).toUpperCase();
  const last = parts[parts.length - 1] ?? '';
  return ((first[0] ?? '') + (last[0] ?? '')).toUpperCase();
}

export function UserMenu() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { mutate: logout, isPending: loggingOut } = useLogout();
  const { data: identity } = useGetIdentity<Identity>();

  const handleLogout = (): void => {
    logout(undefined, {
      onSuccess: () => navigate('/login', { replace: true }),
    });
  };

  const handleSettings = (): void => {
    // react-router 7's navigate() returns `void | Promise<void>`; wrap so
    // the implicit return doesn't leak through into the explicit `void`
    // signature TypeScript checks against onClick handlers.
    void navigate('/settings/profile');
  };

  const name = identity?.name ?? t('user_menu.account');
  const subtitle = identity?.email ?? '';

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        className="group flex w-full items-center gap-2.5 rounded-2xl bg-white px-3 py-2.5 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)] transition hover:bg-zinc-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label={t('user_menu.aria_label', { defaultValue: 'User menu' })}
      >
        <div
          className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-amber-200 to-rose-300 text-[12px] font-semibold text-zinc-800"
          aria-hidden
        >
          {initials(identity?.name)}
        </div>
        <div className="min-w-0 flex-1 leading-tight">
          <div className="truncate text-[13px] font-medium text-zinc-900">{name}</div>
          <div className="truncate text-[11px] text-zinc-500">{subtitle}</div>
        </div>
        <SettingsIcon
          className="size-4 shrink-0 text-zinc-500 transition group-hover:text-zinc-700"
          aria-hidden
        />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" side="top" className="w-56">
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
        <DropdownMenuItem onClick={handleSettings}>
          <SettingsIcon className="size-4" />
          <span>{t('user_menu.settings', { defaultValue: 'Ustawienia konta' })}</span>
        </DropdownMenuItem>
        <DropdownMenuItem onClick={handleLogout} disabled={loggingOut}>
          <LogOut className="size-4" />
          <span>{t('app.logout')}</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
