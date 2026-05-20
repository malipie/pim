import { useList } from '@refinedev/core';
import {
  ChevronLeft,
  ChevronRight,
  MoreHorizontal,
  Search,
  ShieldCheck,
  UserCheck,
  UserMinus,
  UserPlus,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

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
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { useDebouncedCallback } from '@/lib/use-debounced-callback';
import { cn } from '@/lib/utils';

import { DeactivateUserModal } from './DeactivateUserModal';
import { StatusBadge } from './StatusBadge';
import type { UserListItem, UserStatus } from './types';
import { UserAvatar } from './UserAvatar';

const PAGE_SIZE = 50;
type StatusFilter = 'all' | UserStatus;

/**
 * RBAC-P5-001 (#691) — Settings → Users list with debounced search,
 * status filter, role filter, and pager.
 *
 * Sources data through Refine's `useList`, which hits `/api/users`
 * exposed by the new `users` resource registered in `App.tsx`. The
 * data-provider (lib/data-provider.ts) maps `eq` filters straight to
 * query params and unwraps the `member`/`totalItems` envelope returned
 * by {@link UsersListController}.
 *
 * Action affordances (3-dot menu, *Invite user* button, role chips) are
 * scaffolded here but their wiring lands in:
 *   - #692 invite user modal,
 *   - #693 edit user,
 *   - #694 deactivate/reactivate,
 *   - #696/#697 role permissions.
 *
 * Each of these tickets layers behaviour onto this same view without
 * rewriting it.
 */
export function UsersListView() {
  const { t } = useTranslation();
  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState('');
  const [searchValue, setSearchValue] = useState('');
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const [roleFilter, setRoleFilter] = useState<string>('all');

  const filters = useMemo(() => {
    const out: Array<{ field: string; operator: 'eq'; value: string }> = [];
    if (statusFilter !== 'all') {
      out.push({ field: 'status', operator: 'eq', value: statusFilter });
    }
    if (searchValue.trim().length > 0) {
      out.push({ field: 'search', operator: 'eq', value: searchValue.trim() });
    }
    if (roleFilter !== 'all') {
      // Role filter is sent as `role[]=<uuid>` so the backend can later
      // accept multiple values without a wire-format change — the
      // data-provider unwraps single-array values into the bracket form.
      out.push({ field: 'role[]', operator: 'eq', value: roleFilter });
    }
    return out;
  }, [statusFilter, searchValue, roleFilter]);

  const { result, query: listQuery } = useList<UserListItem>({
    resource: 'users',
    pagination: { currentPage: page, pageSize: PAGE_SIZE },
    filters,
  });
  const isLoading = listQuery.isLoading;
  const isError = listQuery.isError;
  const refetch = listQuery.refetch;

  // Deactivate flow state — shared modal lives at the list level so we
  // don't remount per-row when the table re-renders.
  const [deactivateTarget, setDeactivateTarget] = useState<UserListItem | null>(null);
  const [deactivateOpen, setDeactivateOpen] = useState(false);
  const openDeactivate = (user: UserListItem) => {
    setDeactivateTarget(user);
    setDeactivateOpen(true);
  };

  const handleReactivate = async (user: UserListItem) => {
    try {
      await jsonFetch(`/api/users/${user.id}/reactivate`, {
        method: 'POST',
        body: {},
        accept: 'application/json',
        contentType: 'application/json',
      });
      toast.success(t('settings.users.reactivate.toast_success', { name: user.display_name }));
      void refetch();
    } catch {
      toast.error(t('settings.users.reactivate.error_generic'));
    }
  };

  // Reset to page 1 whenever any filter changes — pager must not point at
  // a stale offset that no longer exists in the narrowed result set.
  useEffect(() => {
    setPage(1);
  }, [statusFilter, searchValue, roleFilter]);

  const debouncedSetSearch = useDebouncedCallback((value: string) => {
    setSearchValue(value);
  }, 300);

  const onSearchChange = (value: string) => {
    setSearchInput(value);
    debouncedSetSearch(value);
  };

  const total = result?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  const users: UserListItem[] = result?.data ?? [];

  return (
    <div className="space-y-4">
      <header className="flex items-start justify-between gap-4">
        <div className="space-y-1">
          <h2 className="display text-xl font-semibold tracking-tight">
            {t('settings.users.title')}
          </h2>
          <p className="max-w-2xl text-sm text-muted-foreground">{t('settings.users.intro')}</p>
        </div>
        <Button size="sm" className="gap-1.5" disabled aria-disabled="true">
          <UserPlus className="size-4" aria-hidden="true" />
          {t('settings.users.invite_cta')}
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
              placeholder={t('settings.users.search_placeholder')}
              value={searchInput}
              onChange={(e) => onSearchChange(e.target.value)}
              className="pl-9"
              aria-label={t('settings.users.search_aria')}
            />
          </div>
          <FilterSelect
            label={t('settings.users.filter_status')}
            value={statusFilter}
            onChange={(v) => setStatusFilter(v as StatusFilter)}
            options={[
              { value: 'all', label: t('settings.users.filter_status_all') },
              { value: 'active', label: t('settings.users.status.active') },
              { value: 'disabled', label: t('settings.users.status.disabled') },
            ]}
          />
          <FilterSelect
            label={t('settings.users.filter_role')}
            value={roleFilter}
            onChange={setRoleFilter}
            options={[{ value: 'all', label: t('settings.users.filter_role_all') }]}
            disabled
            disabledHint={t('settings.users.filter_role_pending_hint')}
          />
          <div className="text-xs text-muted-foreground sm:ml-auto">
            {t('settings.users.count', { count: total })}
          </div>
        </div>
      </div>

      <div className="overflow-hidden rounded-lg border bg-background shadow-sm">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="pl-5">{t('settings.users.col_user')}</TableHead>
              <TableHead>{t('settings.users.col_roles')}</TableHead>
              <TableHead>{t('settings.users.col_status')}</TableHead>
              <TableHead>{t('settings.users.col_last_login')}</TableHead>
              <TableHead className="pr-5 text-right" aria-label={t('settings.users.col_actions')} />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isError && (
              <TableRow>
                <TableCell colSpan={5} className="py-8 text-center text-sm text-rose-600">
                  {t('settings.users.error_loading')}
                </TableCell>
              </TableRow>
            )}
            {!isError && isLoading && users.length === 0 && <SkeletonRows />}
            {!isError && !isLoading && users.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-12 text-center">
                  <EmptyState />
                </TableCell>
              </TableRow>
            )}
            {users.map((user) => (
              <UserRow
                key={user.id}
                user={user}
                onDeactivate={openDeactivate}
                onReactivate={handleReactivate}
              />
            ))}
          </TableBody>
        </Table>
      </div>

      <DeactivateUserModal
        user={deactivateTarget}
        open={deactivateOpen}
        onOpenChange={setDeactivateOpen}
        onSuccess={() => {
          void refetch();
        }}
      />

      {totalPages > 1 && (
        <div className="flex items-center justify-between rounded-lg border bg-background px-3 py-2 text-sm shadow-sm">
          <span className="text-muted-foreground">
            {t('settings.users.pagination_status', { page, total_pages: totalPages })}
          </span>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
              aria-label={t('settings.users.pagination_prev')}
            >
              <ChevronLeft className="size-4" />
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page >= totalPages}
              aria-label={t('settings.users.pagination_next')}
            >
              <ChevronRight className="size-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

interface UserRowProps {
  user: UserListItem;
  onDeactivate: (user: UserListItem) => void;
  onReactivate: (user: UserListItem) => void;
}

function UserRow({ user, onDeactivate, onReactivate }: UserRowProps) {
  const { t, i18n } = useTranslation();
  const lastLogin = user.last_login_at
    ? new Date(user.last_login_at).toLocaleString(i18n.language)
    : t('settings.users.last_login_never');

  return (
    <TableRow className={cn(user.status === 'disabled' && 'opacity-60')}>
      <TableCell className="pl-5">
        <div className="flex items-center gap-3">
          <UserAvatar initial={user.avatar_initial} seed={user.email} />
          <div className="min-w-0">
            <div className="text-sm font-medium">{user.display_name}</div>
            <div className="truncate text-xs text-muted-foreground">{user.email}</div>
          </div>
        </div>
      </TableCell>
      <TableCell>
        {user.roles.length === 0 ? (
          <span className="text-xs text-muted-foreground">{t('settings.users.no_roles')}</span>
        ) : (
          <div className="flex flex-wrap gap-1">
            {user.roles.map((role) => (
              <RoleChip key={role.id} name={role.name} />
            ))}
          </div>
        )}
      </TableCell>
      <TableCell>
        <StatusBadge status={user.status} />
      </TableCell>
      <TableCell className="text-xs text-muted-foreground">{lastLogin}</TableCell>
      <TableCell className="pr-5 text-right">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" aria-label={t('settings.users.row_actions')}>
              <MoreHorizontal className="size-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {user.status === 'active' ? (
              <DropdownMenuItem
                onSelect={() => onDeactivate(user)}
                className="text-rose-600 focus:text-rose-700"
              >
                <UserMinus className="mr-2 size-4" aria-hidden="true" />
                {t('settings.users.action_deactivate')}
              </DropdownMenuItem>
            ) : (
              <DropdownMenuItem onSelect={() => onReactivate(user)}>
                <UserCheck className="mr-2 size-4" aria-hidden="true" />
                {t('settings.users.action_reactivate')}
              </DropdownMenuItem>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      </TableCell>
    </TableRow>
  );
}

function RoleChip({ name }: { name: string }) {
  return (
    <span className="inline-flex items-center gap-1 rounded-md bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700 ring-1 ring-violet-200">
      <ShieldCheck className="size-3" aria-hidden="true" />
      {name}
    </span>
  );
}

function SkeletonRows() {
  return (
    <>
      {[0, 1, 2].map((row) => (
        <TableRow key={row}>
          <TableCell className="pl-5">
            <div className="flex items-center gap-3">
              <div className="size-9 animate-pulse rounded-full bg-muted" />
              <div className="space-y-1.5">
                <div className="h-3 w-32 animate-pulse rounded bg-muted" />
                <div className="h-3 w-24 animate-pulse rounded bg-muted/60" />
              </div>
            </div>
          </TableCell>
          <TableCell>
            <div className="h-4 w-20 animate-pulse rounded bg-muted" />
          </TableCell>
          <TableCell>
            <div className="h-5 w-16 animate-pulse rounded bg-muted" />
          </TableCell>
          <TableCell>
            <div className="h-3 w-24 animate-pulse rounded bg-muted" />
          </TableCell>
          <TableCell className="pr-5" />
        </TableRow>
      ))}
    </>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center gap-2 text-center">
      <UserPlus className="size-8 text-muted-foreground" aria-hidden="true" />
      <div className="text-sm font-medium">{t('settings.users.empty_title')}</div>
      <div className="max-w-md text-xs text-muted-foreground">
        {t('settings.users.empty_description')}
      </div>
    </div>
  );
}

interface FilterSelectProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: ReadonlyArray<{ value: string; label: string }>;
  disabled?: boolean;
  disabledHint?: string;
}

function FilterSelect({
  label,
  value,
  onChange,
  options,
  disabled,
  disabledHint,
}: FilterSelectProps) {
  return (
    <label
      className={cn(
        'inline-flex items-center gap-2 text-xs text-muted-foreground',
        disabled && 'opacity-60',
      )}
      title={disabled ? disabledHint : undefined}
    >
      <span>{label}:</span>
      <select
        value={value}
        disabled={disabled}
        onChange={(e) => onChange(e.target.value)}
        className="h-9 rounded-md border border-input bg-background px-2 text-xs text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        {options.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </label>
  );
}
