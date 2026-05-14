import { Check, Undo2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';

/**
 * VIEW-17 (#544) — sticky 24h rollback toast — mockup
 * `list-v2-overlays.jsx` l. 532-567.
 *
 * Pojawia się po każdym Apply z BulkWizard. Shows session counts +
 * 24h progress bar (live update every minute) + Wycofaj button →
 * POST /api/bulk-sessions/{id}/rollback. Dismiss usuwa toast bez
 * rollback (sesja wciąż dostępna przez Audit panel).
 */

export interface RollbackSession {
  session_id: string;
  action: string;
  target_count: number;
  success_count: number;
  skipped_count: number;
  error_count: number;
  rollback_available_until?: string;
  completed_at?: string;
}

interface RollbackToastProps {
  session: RollbackSession | null;
  onDismiss: () => void;
  onRolledBack?: () => void;
}

export function RollbackToast({ session, onDismiss, onRolledBack }: RollbackToastProps) {
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(false);
  const [progress, setProgress] = useState<{ pct: number; label: string }>({
    pct: 100,
    label: '24h 0m',
  });

  useEffect(() => {
    if (!session?.rollback_available_until) return undefined;
    const updateProgress = (): void => {
      if (!session.rollback_available_until) return;
      const end = new Date(session.rollback_available_until).getTime();
      const start = session.completed_at
        ? new Date(session.completed_at).getTime()
        : end - 24 * 3600_000;
      const now = Date.now();
      const totalMs = end - start;
      const remainingMs = Math.max(0, end - now);
      const pct = totalMs > 0 ? Math.round((remainingMs / totalMs) * 100) : 0;
      const hours = Math.floor(remainingMs / 3600_000);
      const minutes = Math.floor((remainingMs % 3600_000) / 60_000);
      setProgress({ pct, label: `${hours}h ${minutes}m` });
    };
    updateProgress();
    const handle = window.setInterval(updateProgress, 60_000);
    return () => window.clearInterval(handle);
  }, [session?.rollback_available_until, session?.completed_at]);

  if (!session) return null;

  const handleRollback = async (): Promise<void> => {
    setIsLoading(true);
    try {
      const response = await jsonFetch<{ restored: number; rolled_back_at: string }>(
        `/api/bulk-sessions/${session.session_id}/rollback`,
        { method: 'POST' },
      );
      toast.success(
        t('products.rollback_toast.rolled_back', {
          count: response.restored,
          defaultValue: `Wycofano ${response.restored} zmian`,
        }),
      );
      onRolledBack?.();
      onDismiss();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'rollback failed');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="fixed bottom-6 left-6 z-40 w-[380px]">
      <div className="rounded-2xl bg-zinc-900 text-white shadow-2xl overflow-hidden">
        <div className="px-4 pt-3.5 pb-3 flex items-start gap-3">
          <span className="h-8 w-8 rounded-xl bg-emerald-500/15 text-emerald-300 grid place-items-center mt-0.5">
            <Check className="size-4" />
          </span>
          <div className="flex-1 leading-snug">
            <div className="text-[13px] font-semibold tracking-tight">
              {t('products.rollback_toast.applied', {
                defaultValue: 'Zastosowano akcję zbiorczą',
              })}
            </div>
            <div className="text-[12px] text-white/70 mt-0.5 font-mono">{session.action}</div>
            <div className="text-[11px] text-white/50 mt-1 tabular-nums">
              {session.success_count} zmienione · {session.skipped_count} pominięte ·{' '}
              {session.error_count} błędów · sesja{' '}
              <span className="font-mono">{session.session_id.slice(0, 8)}</span>
            </div>
          </div>
          <button
            type="button"
            onClick={onDismiss}
            aria-label="Dismiss"
            className="text-white/40 hover:text-white"
          >
            <X className="size-4" />
          </button>
        </div>
        <div className="px-4 pb-3 flex items-center gap-2">
          <div className="flex-1 h-1 rounded-full bg-white/10 overflow-hidden">
            <div className="h-full bg-white/60" style={{ width: `${progress.pct}%` }} />
          </div>
          <span className="font-mono text-[10.5px] text-white/60 tabular-nums">
            {progress.label}
          </span>
        </div>
        <div className="px-3 pb-3 flex items-center gap-2">
          <button
            type="button"
            onClick={() => void handleRollback()}
            disabled={isLoading}
            className="flex-1 h-8 rounded-lg bg-white/10 hover:bg-white/15 text-[12px] font-medium inline-flex items-center justify-center gap-1.5 disabled:opacity-50"
          >
            <Undo2 className="size-3.5 text-white/70" />
            {isLoading
              ? t('products.rollback_toast.rolling_back', { defaultValue: 'Wycofuję…' })
              : t('products.rollback_toast.undo', { defaultValue: 'Wycofaj' })}
          </button>
        </div>
      </div>
    </div>
  );
}
