import { useQuery } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { FormatPill } from '@/components/ui-v2/format-pill';
import { exportStatusToPillVariant } from '@/components/ui-v2/status-maps';
import { StatusPill } from '@/components/ui-v2/status-pill';
import { jsonFetch } from '@/lib/http';

import type { ExportSessionRow } from '../hooks/useExportSessions';
import {
  entityTypeLabelKey,
  fileNameOf,
  formatDuration,
  formatStartedAt,
} from '../sessions/session-format';

interface ExportSessionFull extends ExportSessionRow {
  encoding: string | null;
  selected_columns: string[];
  locales: string[] | null;
  channels: string[] | null;
  include_variants: boolean;
  file_size_bytes: number | null;
}

/**
 * EXR-08 — minimal session detail (v2 tokens). Deliberately shallow:
 * a definition-list card, no log viewer — the full detail redesign is
 * out of the EXR epic scope (documented in the PR).
 */
export function ExportSessionShowPage(): React.ReactElement {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();

  const { data: session, isLoading } = useQuery<ExportSessionFull>({
    queryKey: ['exports', 'session', id],
    enabled: id !== undefined,
    queryFn: () =>
      jsonFetch<ExportSessionFull>(`/api/exports/sessions/${id}`, { accept: 'application/json' }),
  });

  if (isLoading || !session) {
    return <div className="h-48 animate-pulse rounded-2xl bg-zinc-100" aria-busy="true" />;
  }

  const rows: Array<[string, React.ReactNode]> = [
    [t('exports.show.entity'), t(entityTypeLabelKey(session.entity_type))],
    [t('exports.show.format'), <FormatPill key="f" format={session.format} />],
    [t('exports.show.scope'), session.target_scope],
    [
      t('exports.show.rows'),
      <span key="r" className="num font-mono">
        {session.success_count}/{session.target_count}
      </span>,
    ],
    [t('exports.show.profile'), session.profile_name ?? '—'],
    [t('exports.show.columns'), session.selected_columns.join(', ') || '—'],
    [t('exports.show.locales'), session.locales?.join(', ') ?? '—'],
    [t('exports.show.channels'), session.channels?.join(', ') ?? '—'],
    [t('exports.show.started'), formatStartedAt(session.started_at)],
    [t('exports.show.duration'), formatDuration(session.duration_ms)],
    [t('exports.show.file'), fileNameOf(session) ?? '—'],
    [
      t('exports.show.size'),
      session.file_size_bytes === null ? '—' : `${Math.round(session.file_size_bytes / 1024)} KiB`,
    ],
  ];
  if (session.error_message) {
    rows.push([
      t('exports.show.error'),
      <span key="e" className="text-brick-600">
        {session.error_message}
      </span>,
    ]);
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Link
          to="/integrations/exports/sessions"
          className="focus-ring inline-flex items-center gap-1.5 rounded-xl text-[13px] font-medium text-zinc-500 hover:text-ink"
        >
          <ArrowLeft className="size-4" aria-hidden />
          {t('exports.show.back')}
        </Link>
        <StatusPill variant={exportStatusToPillVariant(session.status)} />
      </div>
      <div className="rounded-2xl border border-zinc-200 bg-surface p-6 shadow-card">
        <h1 className="font-mono text-[15px] font-semibold tracking-tight text-ink">
          {fileNameOf(session) ?? t(entityTypeLabelKey(session.entity_type))}
        </h1>
        <dl className="mt-4 grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
          {rows.map(([label, value]) => (
            <div key={label} className="flex items-baseline justify-between gap-4 sm:justify-start">
              <dt className="w-36 shrink-0 text-[11px] font-medium tracking-wider text-zinc-500 uppercase">
                {label}
              </dt>
              <dd className="min-w-0 truncate text-[13px] text-zinc-700">{value}</dd>
            </div>
          ))}
        </dl>
      </div>
    </div>
  );
}

export default ExportSessionShowPage;
