import { useList } from '@refinedev/core';
import {
  MoreHorizontal,
  Pencil,
  Plus,
  Search,
  ShieldCheck,
  Users as UsersIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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
import type { RoleListItem, RoleListType } from './types';

type TypeFilter = 'all' | RoleListType;

/**
 * Role list polish (marathon-3 / #847) — pixel-perfect-ish to PRD-PIM-rbac
 * §5.3 + §5.4 surrounding context. Adds a toolbar (search + type filter),
 * a clickable row (entire row → editor), a footer count, and uses
 * `<RoleTypeBadge>` for system/custom discrimination.
 *
 * The PRD doesn't dedicate a separate "role list" mockup but it sits next
 * to the §5.4 users list visually — toolbar layout + footer count + row
 * affordances mirror that one so the Settings → Users and Settings →
 * Roles surfaces feel consistent.
 */
export function RolesListView() {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState<TypeFilter>('all');

  const { result, query: listQuery } = useList<RoleListItem>({
    resource: 'roles',
    pagination: { mode: 'off' },
  });
  const isLoading = listQuery.isLoading;
  const isError = listQuery.isError;
  const roles: RoleListItem[] = result?.data ?? [];

  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return roles.filter((role) => {
      if (typeFilter !== 'all' && role.type !== typeFilter) return false;
      if (needle.length > 0) {
        if (!`${role.code} ${role.name}`.toLowerCase().includes(needle)) return false;
      }
      return true;
    });
  }, [roles, search, typeFilter]);

  return (
    <div className="space-y-4">
      <header className="flex items-start justify-between gap-4">
        <div className="space-y-1">
          <h2 className="display text-xl font-semibold tracking-tight">
            {t('settings.roles.title')}
          </h2>
          <p className="max-w-2xl text-sm text-muted-foreground">{t('settings.roles.intro')}</p>
        </div>
        <Button size="sm" className="gap-1.5" onClick={() => navigate('/settings/roles/new')}>
          <Plus className="size-4" aria-hidden="true" />
          {t('settings.roles.create_cta')}
        </Button>
      </header>

      <div className="rounded-lg border bg-background p-3 shadow-sm">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <Search
              className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
              aria-hidden="true"
            />
            <Input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t('settings.roles.search_placeholder')}
              className="pl-9"
            />
          </div>
          <label className="inline-flex items-center gap-2 text-xs text-muted-foreground">
            <span>{t('settings.roles.filter_type')}:</span>
            <select
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value as TypeFilter)}
              className="h-9 rounded-md border border-input bg-background px-2 text-xs"
            >
              <option value="all">{t('settings.roles.filter_type_all')}</option>
              <option value="system">{t('settings.roles.type.system')}</option>
              <option value="custom">{t('settings.roles.type.custom')}</option>
            </select>
          </label>
          <div className="text-xs text-muted-foreground sm:ml-auto">
            {t('settings.roles.showing_count', {
              shown: filtered.length,
              total: roles.length,
            })}
          </div>
        </div>
      </div>

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
            {!isError && isLoading && filtered.length === 0 && <SkeletonRows />}
            {!isError && !isLoading && filtered.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-12 text-center">
                  <EmptyState />
                </TableCell>
              </TableRow>
            )}
            {filtered.map((role) => (
              <RoleRow
                key={role.id}
                role={role}
                locale={i18n.language}
                onEdit={() => navigate(`/settings/roles/${role.id}/edit`)}
              />
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

function RoleRow({
  role,
  locale,
  onEdit,
}: {
  role: RoleListItem;
  locale: string;
  onEdit: () => void;
}) {
  const { t } = useTranslation();
  const createdAt = new Date(role.created_at).toLocaleDateString(locale);

  return (
    <TableRow
      className="cursor-pointer transition-colors hover:bg-muted/30"
      onClick={(e) => {
        // Avoid hijacking clicks on the row actions menu.
        if ((e.target as HTMLElement).closest('button, [role="menu"]')) return;
        onEdit();
      }}
    >
      <TableCell className="pl-5">
        <div className="flex items-center gap-3">
          <span
            className={cn(
              'inline-grid size-8 place-items-center rounded-md',
              role.type === 'system'
                ? 'bg-slate-100 text-slate-600'
                : 'bg-accent-violet/10 text-accent-violet',
            )}
            aria-hidden="true"
          >
            <ShieldCheck className="size-4" />
          </span>
          <div className="min-w-0">
            <div className="truncate text-sm font-medium">{role.name}</div>
            <div className="truncate font-mono text-[11px] text-muted-foreground">{role.code}</div>
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
          <span className="inline-flex items-center gap-1 text-sm text-foreground">
            <UsersIcon className="size-3" aria-hidden="true" />
            {role.user_count}
          </span>
          <span className={cn(0 === role.user_count && 'text-muted-foreground/70')}>
            {t('settings.roles.created_at', { date: createdAt })}
          </span>
        </div>
      </TableCell>
      <TableCell className="pr-5 text-right">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" aria-label={t('settings.roles.row_actions')}>
              <MoreHorizontal className="size-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onSelect={onEdit}>
              <Pencil className="mr-2 size-4" aria-hidden="true" />
              {role.type === 'system'
                ? t('settings.roles.action_view_permissions')
                : t('settings.roles.action_edit')}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </TableCell>
    </TableRow>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center gap-2 text-center">
      <ShieldCheck className="size-8 text-muted-foreground" aria-hidden="true" />
      <div className="text-sm font-medium">{t('settings.roles.empty_title')}</div>
      <div className="max-w-md text-xs text-muted-foreground">
        {t('settings.roles.empty_description')}
      </div>
    </div>
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
