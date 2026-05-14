import { Clock, Loader2, Undo2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * VIEW-17b — topbar popover listujący wszystkie aktywne 24h bulk
 * sessions (`rollback_available_until > now()` AND `rolled_back_at IS NULL`).
 *
 * `RollbackToast` (sticky bottom-left) pokazuje tylko ostatnią
 * akcję i znika po nawigacji. Ten popover daje globalny dostęp z
 * każdego ekranu (Modeling, Settings, Excel mode) — operator wraca
 * po godzinie, klika ikonkę zegara, widzi listę i wybiera dowolną
 * sesję do rollbacku.
 *
 * Polling 30s. Ikona pokazuje badge z liczbą aktywnych sesji; gdy 0,
 * cały trigger jest ukryty, żeby topbar nie zaśmiecał się pustym
 * stanem.
 */

interface BulkSessionRow {
  id: string;
  action_type: string;
  target_count: number;
  success_count: number;
  rollback_available_until?: string | null;
  completed_at?: string | null;
  started_at: string;
  is_rollback_available: boolean;
  source: string;
}

const POLL_INTERVAL_MS = 30_000;

const ACTION_LABEL_FALLBACK: Record<string, string> = {
  set_attribute: 'Ustaw atrybut',
  clear_attribute: 'Wyczyść atrybut',
  append_value: 'Dodaj wartość',
  remove_value: 'Usuń wartość',
  increment_numeric: 'Operacja arytm.',
  multi_attribute_edit: 'Multi-atrybut',
  add_category: 'Dodaj kategorię',
  remove_category: 'Usuń kategorię',
  move_category: 'Przenieś kategorię',
  publish_channels: 'Publikuj kanały',
  unpublish_channels: 'Wycofaj z kanałów',
  delete: 'Usuń produkty',
  duplicate: 'Duplikuj produkty',
};

export function BulkSessionsPopover() {
  const { t } = useTranslation();
  const [sessions, setSessions] = useState<BulkSessionRow[]>([]);
  const [open, setOpen] = useState(false);
  const [rollingBack, setRollingBack] = useState<string | null>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);

  const refresh = useCallback(async (): Promise<void> => {
    try {
      const body = await jsonFetch<{ member?: BulkSessionRow[] }>(
        '/api/bulk-sessions?status=active&limit=10',
        { accept: 'application/json' },
      );
      setSessions(body.member ?? []);
    } catch {
      // Silent: the topbar must not bubble a failure.
    }
  }, []);

  useEffect(() => {
    // Hard reload races: AppLayout can mount before `authProvider.check()`
    // finishes its silent refresh, so the very first fetch may fire with
    // an empty `accessToken` and a `jsonFetch` retry through refresh.
    // Run an immediate fetch (handles the steady-state) plus a short
    // re-fetch 500ms later so the popover picks up data after the JWT
    // settles. The 30s poll keeps it fresh thereafter.
    void refresh();
    const retryHandle = window.setTimeout(() => void refresh(), 500);
    const pollHandle = window.setInterval(() => void refresh(), POLL_INTERVAL_MS);
    return () => {
      window.clearTimeout(retryHandle);
      window.clearInterval(pollHandle);
    };
  }, [refresh]);

  useEffect(() => {
    if (!open) return;
    const onClick = (event: MouseEvent): void => {
      const target = event.target as Node;
      if (triggerRef.current?.contains(target)) return;
      if (panelRef.current?.contains(target)) return;
      setOpen(false);
    };
    window.addEventListener('mousedown', onClick);
    return () => window.removeEventListener('mousedown', onClick);
  }, [open]);

  const hasSessions = sessions.length > 0;

  const rollback = async (sessionId: string): Promise<void> => {
    setRollingBack(sessionId);
    try {
      await jsonFetch(`/api/bulk-sessions/${sessionId}/rollback`, {
        method: 'POST',
        accept: 'application/json',
      });
      toast.success(t('bulk_sessions.rollback_ok', { defaultValue: 'Cofnięto' }));
      await refresh();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'rollback failed');
    } finally {
      setRollingBack(null);
    }
  };

  return (
    <div className="relative">
      <button
        ref={triggerRef}
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        aria-label={t('bulk_sessions.popover_aria', {
          count: sessions.length,
          defaultValue: 'Aktywne akcje do cofnięcia: {{count}}',
        })}
        aria-expanded={open}
        className={cn(
          'relative inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900',
          hasSessions ? 'text-zinc-700 hover:text-zinc-900' : 'text-zinc-400 hover:text-zinc-700',
        )}
      >
        <Clock className="size-4" aria-hidden="true" />
        {hasSessions ? (
          <span className="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-violet-600 px-1 font-mono text-[10px] font-semibold text-white">
            {sessions.length > 9 ? '9+' : sessions.length}
          </span>
        ) : null}
      </button>

      {open ? (
        <div
          ref={panelRef}
          role="dialog"
          aria-label={t('bulk_sessions.popover_title', {
            defaultValue: 'Cofalne akcje (24h)',
          })}
          className="absolute right-0 top-full z-50 mt-2 w-[360px] overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-xl"
        >
          <div className="flex items-center justify-between border-b border-zinc-100 px-4 py-2.5">
            <span className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
              {t('bulk_sessions.popover_title', { defaultValue: 'Cofalne akcje (24h)' })}
            </span>
            <span
              className={cn(
                'rounded-full px-2 py-0.5 font-mono text-[10.5px] font-semibold',
                hasSessions ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-500',
              )}
            >
              {sessions.length} {t('bulk_sessions.active_short', { defaultValue: 'aktywne' })}
            </span>
          </div>

          {!hasSessions ? (
            <div className="px-4 py-6 text-center text-[12.5px] text-zinc-500">
              {t('bulk_sessions.empty', {
                defaultValue: 'Brak akcji do cofnięcia w ostatnich 24h.',
              })}
            </div>
          ) : null}

          <ul className="max-h-[420px] divide-y divide-zinc-100 overflow-y-auto">
            {sessions.map((s) => (
              <li key={s.id} className="px-4 py-2.5">
                <div className="flex items-baseline justify-between gap-2">
                  <span className="truncate text-[12.5px] font-medium text-zinc-900">
                    {ACTION_LABEL_FALLBACK[s.action_type] ?? s.action_type}
                  </span>
                  <span className="shrink-0 font-mono text-[10.5px] text-zinc-400">
                    {formatTimeAgo(s.completed_at ?? s.started_at)}
                  </span>
                </div>
                <div className="mt-0.5 flex items-center gap-2 text-[11.5px] text-zinc-500">
                  <span className="tabular-nums">
                    {s.success_count}{' '}
                    {t('bulk_sessions.count_products', { defaultValue: 'produktów' })}
                  </span>
                  <span className="text-zinc-300">·</span>
                  <span className="inline-flex items-center gap-1 font-mono text-zinc-500">
                    <Clock className="size-3" aria-hidden="true" />
                    {formatRemaining(s.rollback_available_until)}
                  </span>
                </div>
                <div className="mt-2 flex justify-end">
                  <button
                    type="button"
                    disabled={!s.is_rollback_available || rollingBack === s.id}
                    onClick={() => void rollback(s.id)}
                    className={cn(
                      'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11.5px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900',
                      s.is_rollback_available && rollingBack !== s.id
                        ? 'bg-zinc-900 text-white hover:bg-zinc-800'
                        : 'bg-zinc-100 text-zinc-400',
                    )}
                  >
                    {rollingBack === s.id ? (
                      <Loader2 className="size-3 animate-spin" aria-hidden="true" />
                    ) : (
                      <Undo2 className="size-3" aria-hidden="true" />
                    )}
                    {t('bulk_sessions.rollback_button', { defaultValue: 'Wycofaj' })}
                  </button>
                </div>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}

function formatTimeAgo(iso: string): string {
  const past = new Date(iso).getTime();
  const diff = Date.now() - past;
  const minutes = Math.floor(diff / 60_000);
  if (minutes < 1) return 'teraz';
  if (minutes < 60) return `${minutes} min temu`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h temu`;
  return `${Math.floor(hours / 24)}d temu`;
}

function formatRemaining(iso?: string | null): string {
  if (!iso) return '—';
  const end = new Date(iso).getTime();
  const remaining = end - Date.now();
  if (remaining <= 0) return '0h';
  const hours = Math.floor(remaining / 3600_000);
  const minutes = Math.floor((remaining % 3600_000) / 60_000);
  return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
}
