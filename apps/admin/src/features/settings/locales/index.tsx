import { Languages, MoreHorizontal, Star } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
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
import { cn } from '@/lib/utils';

import type { TenantLocaleListItem, TenantLocaleListResponse } from './types';

/**
 * LOC-07 (#875) — Settings → Languages / `/settings/locales`.
 *
 * Lists the tenant's active + inactive locales backed by
 * `GET /api/tenant-locales`. Operators flip mandatory inline, pick the
 * fallback, set a new default, and soft-deactivate / reactivate locales
 * via the 3-dot menu.
 *
 * Drag-to-reorder, the "Add locale" modal (LOC-08 #876), the channel ↔
 * locale matrix section (LOC-09 #877), and the typed-confirm purge flow
 * are explicit follow-ups; this PR ships the list surface + the inline
 * lifecycle controls operators reach most often.
 */
export function LocalesSettingsPage() {
  const { t } = useTranslation();
  const [rows, setRows] = useState<TenantLocaleListItem[] | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refetch = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await jsonFetch<TenantLocaleListResponse>('/api/tenant-locales', {
        accept: 'application/json',
      });
      setRows(response.items);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load locales.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void refetch();
  }, [refetch]);

  const activeCodes = useMemo(
    () => rows?.filter((r) => r.isActive).map((r) => r.code) ?? [],
    [rows],
  );

  const onSetDefault = async (code: string) => {
    try {
      await jsonFetch(`/api/tenant-locales/${encodeURIComponent(code)}`, {
        method: 'PATCH',
        body: { isDefault: true },
        contentType: 'application/json',
      });
      toast.success(t('settings.locales.toast.default_changed', { code }));
      void refetch();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Default switch failed.');
    }
  };

  const onToggleMandatory = async (row: TenantLocaleListItem) => {
    if (row.isDefault) return;
    try {
      await jsonFetch(`/api/tenant-locales/${encodeURIComponent(row.code)}`, {
        method: 'PATCH',
        body: { isMandatory: !row.isMandatory },
        contentType: 'application/json',
      });
      void refetch();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Update failed.');
    }
  };

  const onChangeFallback = async (row: TenantLocaleListItem, fallbackCode: string | null) => {
    try {
      await jsonFetch(`/api/tenant-locales/${encodeURIComponent(row.code)}`, {
        method: 'PATCH',
        body: { fallbackCode },
        contentType: 'application/json',
      });
      void refetch();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Fallback update failed.');
    }
  };

  const onDeactivate = async (row: TenantLocaleListItem) => {
    try {
      await jsonFetch(`/api/tenant-locales/${encodeURIComponent(row.code)}`, {
        method: 'DELETE',
      });
      toast.success(t('settings.locales.toast.deactivated', { code: row.code }));
      void refetch();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Deactivation failed.');
    }
  };

  const onReactivate = async (row: TenantLocaleListItem) => {
    try {
      await jsonFetch(`/api/tenant-locales/${encodeURIComponent(row.code)}/reactivate`, {
        method: 'POST',
        body: {},
        contentType: 'application/json',
      });
      void refetch();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Reactivation failed.');
    }
  };

  return (
    <div className="space-y-4">
      <header className="flex items-start justify-between gap-4">
        <div className="space-y-1">
          <h2 className="display text-xl font-semibold tracking-tight">
            {t('settings.locales.title')}
          </h2>
          <p className="max-w-2xl text-sm text-muted-foreground">
            {t('settings.locales.intro')}
          </p>
        </div>
      </header>

      <div className="overflow-hidden rounded-lg border bg-background shadow-sm">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="pl-5">{t('settings.locales.col_locale')}</TableHead>
              <TableHead>{t('settings.locales.col_default')}</TableHead>
              <TableHead>{t('settings.locales.col_mandatory')}</TableHead>
              <TableHead>{t('settings.locales.col_fallback')}</TableHead>
              <TableHead>{t('settings.locales.col_status')}</TableHead>
              <TableHead
                className="pr-5 text-right"
                aria-label={t('settings.locales.col_actions')}
              />
            </TableRow>
          </TableHeader>
          <TableBody>
            {error && (
              <TableRow>
                <TableCell colSpan={6} className="py-8 text-center text-sm text-rose-600">
                  {t('settings.locales.error_loading')}
                </TableCell>
              </TableRow>
            )}
            {!error && isLoading && rows === null && <SkeletonRows />}
            {!error && !isLoading && rows?.length === 0 && (
              <TableRow>
                <TableCell colSpan={6} className="py-12 text-center text-sm text-muted-foreground">
                  {t('settings.locales.empty')}
                </TableCell>
              </TableRow>
            )}
            {rows?.map((row) => (
              <LocaleRow
                key={row.id}
                row={row}
                availableFallbacks={activeCodes.filter((c) => c !== row.code)}
                onSetDefault={onSetDefault}
                onToggleMandatory={onToggleMandatory}
                onChangeFallback={onChangeFallback}
                onDeactivate={onDeactivate}
                onReactivate={onReactivate}
              />
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

function LocaleRow({
  row,
  availableFallbacks,
  onSetDefault,
  onToggleMandatory,
  onChangeFallback,
  onDeactivate,
  onReactivate,
}: {
  row: TenantLocaleListItem;
  availableFallbacks: string[];
  onSetDefault: (code: string) => void;
  onToggleMandatory: (row: TenantLocaleListItem) => void;
  onChangeFallback: (row: TenantLocaleListItem, fallbackCode: string | null) => void;
  onDeactivate: (row: TenantLocaleListItem) => void;
  onReactivate: (row: TenantLocaleListItem) => void;
}) {
  const { t, i18n } = useTranslation();
  const localised =
    row.displayName[i18n.language] ?? row.displayName.en ?? row.displayName.pl ?? row.label;

  return (
    <TableRow className={cn(!row.isActive && 'opacity-50')}>
      <TableCell className="pl-5">
        <div className="flex items-center gap-3">
          <span
            className="inline-grid size-8 place-items-center rounded-md bg-accent-violet/10 text-accent-violet"
            aria-hidden="true"
          >
            <Languages className="size-4" />
          </span>
          <div className="min-w-0">
            <div className="text-sm font-medium">{localised}</div>
            <div className="font-mono text-[11px] text-muted-foreground">{row.code}</div>
          </div>
        </div>
      </TableCell>
      <TableCell>
        {row.isDefault ? (
          <span className="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-700 ring-1 ring-amber-200">
            <Star className="size-3" aria-hidden="true" />
            {t('settings.locales.badge_default')}
          </span>
        ) : (
          <span className="text-xs text-muted-foreground">—</span>
        )}
      </TableCell>
      <TableCell>
        <label className="inline-flex cursor-pointer items-center gap-2">
          <input
            type="checkbox"
            checked={row.isMandatory}
            disabled={row.isDefault || !row.isActive}
            onChange={() => onToggleMandatory(row)}
            aria-label={t('settings.locales.col_mandatory')}
            className="size-4 rounded border-input"
          />
          <span className="text-xs text-muted-foreground">
            {row.isMandatory ? t('settings.locales.mandatory_on') : t('settings.locales.mandatory_off')}
          </span>
        </label>
      </TableCell>
      <TableCell>
        <Select
          value={row.fallbackCode ?? '__none__'}
          onValueChange={(value) =>
            onChangeFallback(row, value === '__none__' ? null : value)
          }
          disabled={!row.isActive}
        >
          <SelectTrigger className="h-8 w-[140px] text-xs">
            <SelectValue placeholder={t('settings.locales.fallback_none')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__none__">{t('settings.locales.fallback_none')}</SelectItem>
            {availableFallbacks.map((code) => (
              <SelectItem key={code} value={code} className="font-mono">
                {code}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </TableCell>
      <TableCell>
        <span
          className={cn(
            'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-medium ring-1',
            row.isActive
              ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
              : 'bg-rose-50 text-rose-700 ring-rose-200',
          )}
        >
          <span
            className={cn(
              'h-1.5 w-1.5 rounded-full',
              row.isActive ? 'bg-emerald-500' : 'bg-rose-500',
            )}
            aria-hidden="true"
          />
          {row.isActive ? t('settings.locales.status_active') : t('settings.locales.status_inactive')}
        </span>
      </TableCell>
      <TableCell className="pr-5 text-right">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" aria-label={t('settings.locales.row_actions')}>
              <MoreHorizontal className="size-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {!row.isDefault && row.isActive && (
              <DropdownMenuItem onSelect={() => onSetDefault(row.code)}>
                {t('settings.locales.action_set_default')}
              </DropdownMenuItem>
            )}
            <DropdownMenuSeparator />
            {row.isActive ? (
              <DropdownMenuItem
                onSelect={() => onDeactivate(row)}
                disabled={row.isDefault}
                className="text-rose-600 focus:text-rose-700"
              >
                {t('settings.locales.action_deactivate')}
              </DropdownMenuItem>
            ) : (
              <DropdownMenuItem onSelect={() => onReactivate(row)}>
                {t('settings.locales.action_reactivate')}
              </DropdownMenuItem>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      </TableCell>
    </TableRow>
  );
}

function SkeletonRows() {
  return (
    <>
      {[0, 1, 2].map((row) => (
        <TableRow key={row}>
          <TableCell className="pl-5">
            <div className="flex items-center gap-3">
              <div className="size-8 animate-pulse rounded-md bg-muted" />
              <div className="space-y-1.5">
                <div className="h-3 w-32 animate-pulse rounded bg-muted" />
                <div className="h-3 w-16 animate-pulse rounded bg-muted/60" />
              </div>
            </div>
          </TableCell>
          {[0, 1, 2, 3].map((cell) => (
            <TableCell key={cell}>
              <div className="h-4 w-20 animate-pulse rounded bg-muted" />
            </TableCell>
          ))}
          <TableCell className="pr-5" />
        </TableRow>
      ))}
    </>
  );
}
