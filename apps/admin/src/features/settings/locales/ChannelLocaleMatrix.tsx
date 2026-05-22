import { Link as LinkIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
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
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

import type { TenantLocaleListItem } from './types';

interface ChannelLocaleMatrixRow {
  channelId: string;
  channelCode: string;
  localeCodes: string[];
}

interface ChannelLocaleMatrixResponse {
  items: ChannelLocaleMatrixRow[];
}

/**
 * LOC-09 (#877) — channel ↔ locale binding matrix section embedded inside
 * `/settings/locales` below the locale list.
 *
 * Rows = channels (returned by `GET /api/channel-locales`), columns = the
 * tenant's *active* locales. Toggling cells builds a local dirty plan;
 * "Save" commits the entire matrix in one `PUT /api/channel-locales`.
 * Server-side transaction means a single invalid entry rolls everything
 * back, so partial-save anomalies are not possible.
 */
export function ChannelLocaleMatrix({ activeLocales }: { activeLocales: TenantLocaleListItem[] }) {
  const { t } = useTranslation();
  const [rows, setRows] = useState<ChannelLocaleMatrixRow[] | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [plan, setPlan] = useState<Record<string, Set<string>>>({});
  const [dirty, setDirty] = useState(false);

  const refetch = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await jsonFetch<ChannelLocaleMatrixResponse>('/api/channel-locales', {
        accept: 'application/json',
      });
      setRows(response.items);
      const next: Record<string, Set<string>> = {};
      for (const row of response.items) {
        next[row.channelId] = new Set(row.localeCodes);
      }
      setPlan(next);
      setDirty(false);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load matrix.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void refetch();
  }, [refetch]);

  const activeCodes = useMemo(
    () => activeLocales.filter((l) => l.isActive).map((l) => l.code),
    [activeLocales],
  );

  const toggle = (channelId: string, code: string) => {
    setPlan((prev) => {
      const next = { ...prev };
      const set = new Set(next[channelId] ?? []);
      if (set.has(code)) {
        set.delete(code);
      } else {
        set.add(code);
      }
      next[channelId] = set;
      return next;
    });
    setDirty(true);
  };

  const discard = () => {
    if (!rows) return;
    const next: Record<string, Set<string>> = {};
    for (const row of rows) {
      next[row.channelId] = new Set(row.localeCodes);
    }
    setPlan(next);
    setDirty(false);
  };

  const save = async () => {
    setIsSaving(true);
    try {
      const payload = {
        items: Object.entries(plan).map(([channelId, set]) => ({
          channelId,
          localeCodes: Array.from(set),
        })),
      };
      await jsonFetch<ChannelLocaleMatrixResponse>('/api/channel-locales', {
        method: 'PUT',
        body: payload,
        contentType: 'application/json',
      });
      toast.success(t('settings.locales.channel_matrix.toast_saved'));
      void refetch();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Save failed.');
    } finally {
      setIsSaving(false);
    }
  };

  if (error) {
    return (
      <section className="rounded-lg border bg-background p-6">
        <p className="text-sm text-rose-600">
          {t('settings.locales.channel_matrix.error_loading')}
        </p>
      </section>
    );
  }

  if (isLoading && rows === null) {
    return (
      <section className="space-y-3 rounded-lg border bg-background p-6">
        <div className="h-5 w-48 animate-pulse rounded bg-muted" />
        <div className="h-32 animate-pulse rounded bg-muted/50" />
      </section>
    );
  }

  if (rows !== null && rows.length === 0) {
    return (
      <section className="rounded-lg border bg-background p-6">
        <h3 className="text-sm font-semibold">{t('settings.locales.channel_matrix.title')}</h3>
        <p className="mt-2 text-sm text-muted-foreground">
          {t('settings.locales.channel_matrix.empty')}
        </p>
      </section>
    );
  }

  return (
    <section className="space-y-3">
      <header className="flex items-end justify-between">
        <div>
          <h3 className="display flex items-center gap-2 text-base font-semibold tracking-tight">
            <LinkIcon className="size-4 text-muted-foreground" aria-hidden="true" />
            {t('settings.locales.channel_matrix.title')}
          </h3>
          <p className="max-w-2xl text-sm text-muted-foreground">
            {t('settings.locales.channel_matrix.intro')}
          </p>
        </div>
      </header>

      <div className="overflow-hidden rounded-lg border bg-background shadow-sm">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="pl-5">
                {t('settings.locales.channel_matrix.col_channel')}
              </TableHead>
              {activeCodes.map((code) => (
                <TableHead key={code} className="text-center font-mono text-[11px]">
                  {code}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows?.map((row) => (
              <TableRow key={row.channelId}>
                <TableCell className="pl-5 text-sm font-medium">{row.channelCode}</TableCell>
                {activeCodes.map((code) => {
                  const checked = plan[row.channelId]?.has(code) ?? false;
                  return (
                    <TableCell key={code} className="text-center">
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => toggle(row.channelId, code)}
                        aria-label={`${row.channelCode} ↔ ${code}`}
                        className="size-4 rounded border-input"
                      />
                    </TableCell>
                  );
                })}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <div
        className={cn(
          'flex items-center justify-end gap-2 transition-opacity',
          !dirty && 'opacity-0',
        )}
        aria-hidden={!dirty}
      >
        <Button variant="outline" size="sm" onClick={discard} disabled={!dirty || isSaving}>
          {t('settings.locales.channel_matrix.discard')}
        </Button>
        <Button size="sm" onClick={save} disabled={!dirty || isSaving}>
          {isSaving
            ? t('settings.locales.channel_matrix.saving')
            : t('settings.locales.channel_matrix.save')}
        </Button>
      </div>
    </section>
  );
}
