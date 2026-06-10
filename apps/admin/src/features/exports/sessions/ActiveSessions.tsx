import { Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { EmptyState } from '@/components/ui-v2/empty-state';
import { FormatPill } from '@/components/ui-v2/format-pill';
import { ProgressBar } from '@/components/ui-v2/progress-bar';
import { exportStatusToPillVariant } from '@/components/ui-v2/status-maps';
import { StatusPill } from '@/components/ui-v2/status-pill';

import type { ExportSessionRow } from '../hooks/useExportSessions';
import { entityTypeLabelKey, fileNameOf } from './session-format';

interface ActiveSessionsProps {
  sessions: ExportSessionRow[];
}

/**
 * EXR-08 — "W toku" section: cards with a live progress bar per active
 * session, or a dashed empty state with the CTA. Cancellation lands in
 * EXR-15 (no cancel endpoint yet).
 */
export function ActiveSessions({ sessions }: ActiveSessionsProps) {
  const { t } = useTranslation();

  if (sessions.length === 0) {
    return (
      <section aria-label={t('exports.active.title')}>
        <h2 className="mb-2 text-[11px] font-medium tracking-wider text-zinc-400 uppercase">
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
      <h2 className="mb-2 text-[11px] font-medium tracking-wider text-zinc-400 uppercase">
        {t('exports.active.title')}
      </h2>
      <div className="space-y-3">
        {sessions.map((session) => {
          const progress =
            session.target_count > 0 ? session.success_count / session.target_count : 0;
          return (
            <div
              key={session.id}
              className="rounded-2xl border border-zinc-200 bg-surface p-5 shadow-card"
            >
              <div className="flex items-center gap-3">
                <span className="min-w-0 flex-1 truncate font-mono text-[13px] font-medium text-ink">
                  {fileNameOf(session) ?? t(entityTypeLabelKey(session.entity_type))}
                </span>
                <FormatPill format={session.format} />
                <StatusPill variant={exportStatusToPillVariant(session.status)} />
              </div>
              <div className="mt-3">
                <ProgressBar
                  value={progress}
                  ariaLabel={t('exports.active.progress_aria')}
                  animated={session.status === 'running'}
                />
              </div>
              <div className="num mt-2 font-mono text-[11.5px] text-zinc-500">
                {t('exports.active.progress', {
                  done: session.success_count,
                  total: session.target_count,
                })}
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}
