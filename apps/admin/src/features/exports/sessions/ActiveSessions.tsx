import { Download, XCircle } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { toast } from '@/components/ui/toast';
import { EmptyState } from '@/components/ui-v2/empty-state';
import { FormatPill } from '@/components/ui-v2/format-pill';
import { ProgressBar } from '@/components/ui-v2/progress-bar';
import { exportStatusToPillVariant } from '@/components/ui-v2/status-maps';
import { StatusPill } from '@/components/ui-v2/status-pill';
import { jsonFetch } from '@/lib/http';

import type { ExportSessionRow } from '../hooks/useExportSessions';
import { entityTypeLabelKey, fileNameOf } from './session-format';

export interface LiveProgress {
  rowsDone: number;
  rowsTotal: number;
  /** rows/s computed from consecutive progress events. */
  throughput: number | null;
}

interface ActiveSessionsProps {
  sessions: ExportSessionRow[];
  /** EXR-12 — session id to scroll to + pulse after the async redirect. */
  highlightId?: string | null;
  /** EXR-15 — Mercure progress overlay keyed by session id. */
  liveProgress?: Record<string, LiveProgress>;
  /** EXR-15 — called after a successful cancel POST. */
  onCancelled?: () => void;
}

/**
 * EXR-08 — "W toku" section: cards with a live progress bar per active
 * session, or a dashed empty state with the CTA. Cancellation lands in
 * EXR-15 (no cancel endpoint yet).
 */
export function ActiveSessions({
  sessions,
  highlightId = null,
  liveProgress = {},
  onCancelled,
}: ActiveSessionsProps) {
  const { t } = useTranslation();
  const highlightRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (highlightId !== null) {
      highlightRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }, [highlightId]);

  if (sessions.length === 0) {
    return (
      <section aria-label={t('exports.active.title')}>
        <h2 className="mb-2 text-[11px] font-medium tracking-wider text-zinc-500 uppercase">
          {t('exports.active.title')}
        </h2>
        <div className="rounded-2xl border border-dashed border-zinc-200 bg-surface">
          <EmptyState
            icon={<Download className="size-5" />}
            title={t('exports.active.empty_title')}
            description={t('exports.active.empty_subtitle')}
            action={
              <Link
                to="/integrations/exports/new"
                className="focus-ring inline-flex h-9 items-center rounded-xl bg-cta px-3.5 text-[13px] font-semibold text-cta-foreground transition hover:bg-accent-hover"
              >
                {t('exports.new_cta')}
              </Link>
            }
          />
        </div>
      </section>
    );
  }

  return (
    <section aria-label={t('exports.active.title')}>
      <h2 className="mb-2 text-[11px] font-medium tracking-wider text-zinc-500 uppercase">
        {t('exports.active.title')}
      </h2>
      <div className="space-y-3">
        {sessions.map((session) => {
          const live = liveProgress[session.id];
          const done = live?.rowsDone ?? session.success_count;
          const total = live?.rowsTotal ?? session.target_count;
          const progress = total > 0 ? done / total : 0;
          const cancelSession = async () => {
            if (!window.confirm(t('exports.active.cancel_confirm'))) return;
            try {
              await jsonFetch(`/api/exports/sessions/${session.id}/cancel`, {
                method: 'POST',
                accept: 'application/json',
              });
              toast.success(t('exports.active.cancelled_toast'));
              onCancelled?.();
            } catch {
              toast.error(t('exports.active.cancel_failed'));
            }
          };
          return (
            <div
              key={session.id}
              ref={session.id === highlightId ? highlightRef : undefined}
              className={
                session.id === highlightId
                  ? 'animate-pulse rounded-2xl border border-orange-300 bg-surface p-5 shadow-card'
                  : 'rounded-2xl border border-zinc-200 bg-surface p-5 shadow-card'
              }
            >
              <div className="flex items-center gap-3">
                <span className="min-w-0 flex-1 truncate font-mono text-[13px] font-medium text-ink">
                  {fileNameOf(session) ?? t(entityTypeLabelKey(session.entity_type))}
                </span>
                <FormatPill format={session.format} />
                <StatusPill variant={exportStatusToPillVariant(session.status)} />
                <button
                  type="button"
                  onClick={() => void cancelSession()}
                  aria-label={t('exports.active.cancel')}
                  title={t('exports.active.cancel')}
                  className="focus-ring inline-flex h-7 items-center gap-1 rounded-md px-2 text-[12px] font-medium text-zinc-500 hover:bg-brick-50 hover:text-brick-600"
                >
                  <XCircle className="size-3.5" aria-hidden />
                  {t('exports.active.cancel')}
                </button>
              </div>
              <div className="mt-3">
                <ProgressBar
                  value={progress}
                  ariaLabel={t('exports.active.progress_aria')}
                  animated={session.status === 'running'}
                />
              </div>
              <div className="num mt-2 flex items-center gap-3 font-mono text-[11.5px] text-zinc-500">
                <span>{t('exports.active.progress', { done, total })}</span>
                {live?.throughput != null && live.throughput > 0 && (
                  <span>
                    {t('exports.active.throughput', { value: Math.round(live.throughput) })}
                  </span>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}
