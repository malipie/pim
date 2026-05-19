import { useList } from '@refinedev/core';
import { MoreHorizontal, Plus, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

import { RoleTypeBadge } from './RoleTypeBadge';
import type { RoleListItem } from './types';

/**
 * RBAC-P5-005 (#695) — Settings → Roles list. Surfaces every seeded
 * system role plus the caller tenant's custom roles, each annotated
 * with the in-tenant user count. Action affordances (View permissions,
 * Edit, Duplicate, Delete) ship as visually disabled stubs until the
 * dedicated tickets land:
 *   - #696 — custom role builder UI (Create / Edit),
 *   - #697 — per-attribute permissions tab,
 *   - #698 — auto-grant + scope panel.
 *
 * Delete is permanently disabled for system roles per PRD §3.2 macierz
 * (seeded templates are immutable code). For custom roles delete will
 * unlock once the backend gains the soft-delete + last-role-protection
 * guarantees expected by #696.
 */
export function RolesListView() {
  const { t, i18n } = useTranslation();

  const { result, query: listQuery } = useList<RoleListItem>({
    resource: 'roles',
    pagination: { mode: 'off' },
  });
  const isLoading = listQuery.isLoading;
  const isError = listQuery.isError;
  const roles: RoleListItem[] = result?.data ?? [];

  return (
    <div className="space-y-4">
      <header className="flex items-start justify-between gap-4">
        <div className="space-y-1">
          <h2 className="display text-xl font-semibold tracking-tight">
            {t('settings.roles.title')}
          </h2>
          <p className="max-w-2xl text-sm text-muted-foreground">{t('settings.roles.intro')}</p>
        </div>
        <Button
          size="sm"
          className="gap-1.5"
          disabled
          aria-disabled="true"
          title={t('settings.roles.create_pending_hint')}
        >
          <Plus className="size-4" aria-hidden="true" />
          {t('settings.roles.create_cta')}
        </Button>
      </header>

      <div className="overflow-hidden rounded-lg border bg-background shadow-sm">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="pl-5">{t('settings.roles.col_name')}</TableHead>
              <TableHead>{t('settings.roles.col_type')}</TableHead>
              <TableHead>{t('settings.roles.col_permissions')}</TableHead>
              <TableHead>{t('settings.roles.col_user_count')}</TableHead>
              <TableHead className="pr-5 text-right" aria-label={t('settings.roles.col_actions')} />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isError && (
              <TableRow>
                <TableCell colSpan={5} className="py-8 text-center text-sm text-rose-600">
                  {t('settings.roles.error_loading')}
                </TableCell>
              </TableRow>
            )}
            {!isError && isLoading && roles.length === 0 && <SkeletonRows />}
            {!isError && !isLoading && roles.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-12 text-center text-sm text-muted-foreground">
                  {t('settings.roles.empty')}
                </TableCell>
              </TableRow>
            )}
            {roles.map((role) => (
              <RoleRow key={role.id} role={role} locale={i18n.language} />
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

function RoleRow({ role, locale }: { role: RoleListItem; locale: string }) {
  const { t } = useTranslation();
  const createdAt = new Date(role.created_at).toLocaleDateString(locale);

  return (
    <TableRow>
      <TableCell className="pl-5">
        <div className="flex items-center gap-3">
          <span
            className="inline-grid size-8 place-items-center rounded-md bg-accent-violet/10 text-accent-violet"
            aria-hidden="true"
          >
            <ShieldCheck className="size-4" />
          </span>
          <div className="min-w-0">
            <div className="text-sm font-medium">{role.name}</div>
            <div className="font-mono text-[11px] text-muted-foreground">{role.code}</div>
          </div>
        </div>
      </TableCell>
      <TableCell>
        <RoleTypeBadge type={role.type} />
      </TableCell>
      <TableCell className="text-xs text-muted-foreground">
        {t('settings.roles.permissions_count', { count: role.permissions_count })}
      </TableCell>
      <TableCell className="text-xs text-muted-foreground">
        <div className="flex flex-col">
          <span className="text-sm text-foreground">{role.user_count}</span>
          <span className={cn(0 === role.user_count && 'text-muted-foreground/70')}>
            {t('settings.roles.created_at', { date: createdAt })}
          </span>
        </div>
      </TableCell>
      <TableCell className="pr-5 text-right">
        <Button
          variant="ghost"
          size="icon"
          disabled
          aria-label={t('settings.roles.row_actions')}
          aria-disabled="true"
          title={
            'system' === role.type
              ? t('settings.roles.system_immutable_hint')
              : t('settings.roles.actions_pending_hint')
          }
        >
          <MoreHorizontal className="size-4" />
        </Button>
      </TableCell>
    </TableRow>
  );
}

function SkeletonRows() {
  return (
    <>
      {[0, 1, 2, 3].map((row) => (
        <TableRow key={row}>
          <TableCell className="pl-5">
            <div className="flex items-center gap-3">
              <div className="size-8 animate-pulse rounded-md bg-muted" />
              <div className="space-y-1.5">
                <div className="h-3 w-32 animate-pulse rounded bg-muted" />
                <div className="h-3 w-20 animate-pulse rounded bg-muted/60" />
              </div>
            </div>
          </TableCell>
          <TableCell>
            <div className="h-4 w-16 animate-pulse rounded bg-muted" />
          </TableCell>
          <TableCell>
            <div className="h-3 w-24 animate-pulse rounded bg-muted" />
          </TableCell>
          <TableCell>
            <div className="h-3 w-12 animate-pulse rounded bg-muted" />
          </TableCell>
          <TableCell className="pr-5" />
        </TableRow>
      ))}
    </>
  );
}
