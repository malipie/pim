import { ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { ModeBadge, ResultBar, SourceIcon, StatusPill } from '../primitives';
import { type ImportSessionRow, pillFor, SOURCE_FALLBACK } from './types';

interface HistoryRowProps {
  row: ImportSessionRow;
}

function formatStarted(value: string | null): string {
  if (value === null) {
    return '—';
  }
  return new Intl.DateTimeFormat('pl-PL', { dateStyle: 'short', timeStyle: 'short' }).format(
    new Date(value),
  );
}

function formatDuration(seconds: number | null): string {
  if (seconds === null) {
    return '—';
  }
  if (seconds < 60) {
    return `${seconds}s`;
  }
  const minutes = Math.floor(seconds / 60);
  const remainder = seconds % 60;
  return `${minutes}m ${remainder}s`;
}

export function HistoryRow({ row }: HistoryRowProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const total = row.total_rows ?? row.success_count + row.error_count;
  const ok = row.success_count;
  const err = row.error_count;
  const warn = Math.max(0, total - ok - err);

  return (
    <button
      type="button"
      onClick={() => navigate(`/integrations/imports/${row.id}`)}
      className="w-full grid grid-cols-[28px_minmax(0,1.6fr)_minmax(0,1.2fr)_110px_90px_minmax(0,1fr)_130px_120px_36px] gap-3 items-center px-5 py-3 hover:bg-zinc-50/70 transition text-left"
    >
      <SourceIcon type={SOURCE_FALLBACK} />
      <div className="min-w-0">
        <div className="font-mono text-[12.5px] font-medium truncate">{row.file_name}</div>
        <div className="text-[11px] text-zinc-500">
          {row.target_object_type_code ?? t('imports.sessions.history.source_upload')}
        </div>
      </div>
      <div className="min-w-0">
        <div className="text-[12.5px] text-zinc-800 truncate">
          {row.profile_name ?? t('imports.sessions.history.no_profile')}
        </div>
      </div>
      <div>
        <ModeBadge mode={row.mode ?? 'UPDATE'} />
      </div>
      <div className="text-right text-[12.5px] num font-medium">
        {total.toLocaleString('pl-PL')}
      </div>
      <div className="flex items-center gap-2">
        <ResultBar ok={ok} warn={warn} err={err} total={total} width={120} />
        <div className="text-[10.5px] num text-zinc-500 flex items-center gap-1.5 shrink-0">
          {err > 0 ? <span className="text-rose-700">{err}</span> : null}
          {warn > 0 ? <span className="text-amber-700">{warn}</span> : null}
        </div>
      </div>
      <div className="text-[12px] text-zinc-700">
        <div>{formatStarted(row.started_at)}</div>
        <div className="font-mono text-[10.5px] text-zinc-500">
          {formatDuration(row.duration_sec)}
        </div>
      </div>
      <div>
        <StatusPill
          status={pillFor(row.status)}
          label={t(`imports.sessions.status.${row.status}`)}
        />
      </div>
      <div className="text-zinc-300" aria-hidden="true">
        <ChevronRight className="h-4 w-4" />
      </div>
    </button>
  );
}
