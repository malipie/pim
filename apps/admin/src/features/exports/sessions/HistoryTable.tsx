import {
  Boxes,
  ChevronRight,
  Download,
  FolderTree,
  Layers,
  Package,
  RefreshCw,
  Search,
  Tags,
  Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { EmptyState } from '@/components/ui-v2/empty-state';
import { ModeBadge } from '@/components/ui-v2/mode-badge';
import { ResultBar } from '@/components/ui-v2/result-bar';
import { exportStatusToPillVariant } from '@/components/ui-v2/status-maps';
import { StatusPill } from '@/components/ui-v2/status-pill';
import { cn } from '@/lib/utils';

import type { ExportSessionRow } from '../hooks/useExportSessions';
import { entityTypeLabelKey, fileNameOf, formatDuration, formatStartedAt } from './session-format';

const PAGE_SIZE = 20;

type Segment = 'all' | 'success' | 'errors' | 'running';

const SEGMENTS: Array<{ id: Segment; labelKey: string }> = [
  { id: 'all', labelKey: 'exports.history.segment_all' },
  { id: 'success', labelKey: 'exports.history.segment_success' },
  { id: 'errors', labelKey: 'exports.history.segment_errors' },
  { id: 'running', labelKey: 'exports.history.segment_running' },
];

function matchesSegment(session: ExportSessionRow, segment: Segment): boolean {
  switch (segment) {
    case 'success':
      return session.status === 'done';
    case 'errors':
      return session.status === 'error';
    case 'running':
      return session.status === 'running' || session.status === 'pending';
    default:
      return true;
  }
}

const ENTITY_ICONS: Record<string, typeof Package> = {
  products: Package,
  custom_module: Boxes,
  module_schema: Layers,
  attributes: Tags,
  categories: FolderTree,
};

interface HistoryTableProps {
  sessions: ExportSessionRow[];
  userName: string;
  onDownload: (id: string) => void;
  onRerun: (id: string) => void;
  onDelete: (id: string) => void;
}

/**
 * EXR-08 — "Historia" section (screen 1): v2 table pattern with search,
 * status segments and client-side pagination (the list endpoint returns
 * the whole self-audit set, no server paging yet).
 *
 * Column notes vs the design (documented deviations):
 * - TRYB renders the export target_scope (ALL / FILTER / SELECTED) via
 *   ModeBadge — exports have no import-style write mode.
 * - ROZKŁAD has no warning source in the backend, so warn is always 0
 *   (ok = success_count, err = remainder on failed sessions).
 * - UŻYTKOWNIK is the session owner — the endpoint is self-audit-only
 *   (PRD §8.5), so it is always the signed-in user.
 */
export function HistoryTable({
  sessions,
  userName,
  onDownload,
  onRerun,
  onDelete,
}: HistoryTableProps) {
  const { t } = useTranslation();
  const [segment, setSegment] = useState<Segment>('all');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);

  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return sessions.filter((session) => {
      if (!matchesSegment(session, segment)) return false;
      if (needle === '') return true;
      const haystack = [
        fileNameOf(session) ?? '',
        session.profile_name ?? '',
        userName,
        t(entityTypeLabelKey(session.entity_type)),
      ]
        .join(' ')
        .toLowerCase();
      return haystack.includes(needle);
    });
  }, [sessions, segment, search, userName, t]);

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const currentPage = Math.min(page, pageCount - 1);
  const pageRows = filtered.slice(currentPage * PAGE_SIZE, (currentPage + 1) * PAGE_SIZE);

  return (
    <section aria-label={t('exports.history.title')}>
      <div className="mb-2 flex flex-wrap items-center gap-3">
        <h2 className="text-[11px] font-medium tracking-wider text-zinc-400 uppercase">
          {t('exports.history.title', { count: filtered.length })}
        </h2>
        <div className="ml-auto flex flex-wrap items-center gap-2">
          <div className="relative">
            <Search
              className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-zinc-400"
              aria-hidden
            />
            <input
              type="search"
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(0);
              }}
              placeholder={t('exports.history.search_placeholder')}
              aria-label={t('exports.history.search_placeholder')}
              className="focus-ring h-9 rounded-xl border border-zinc-200 bg-surface pr-3 pl-8 text-[13px] placeholder:text-zinc-400"
            />
          </div>
          <fieldset
            aria-label={t('exports.history.segments_aria')}
            className="flex items-center gap-1"
          >
            {SEGMENTS.map(({ id, labelKey }) => (
              <button
                key={id}
                type="button"
                aria-pressed={segment === id}
                onClick={() => {
                  setSegment(id);
                  setPage(0);
                }}
                className={cn(
                  'focus-ring h-8 rounded-xl px-3 text-[12.5px] font-medium transition',
                  segment === id
                    ? 'bg-zinc-900 text-white'
                    : 'text-zinc-500 hover:bg-zinc-100 hover:text-ink',
                )}
              >
                {t(labelKey)}
              </button>
            ))}
          </fieldset>
        </div>
      </div>

      <div className="overflow-hidden rounded-2xl border border-zinc-200 bg-surface shadow-card">
        {pageRows.length === 0 ? (
          <EmptyState
            title={t('exports.history.empty_title')}
            description={t('exports.history.empty_subtitle')}
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-[13px]">
              <thead>
                <tr className="text-left">
                  {(
                    [
                      'col_file',
                      'col_profile',
                      'col_mode',
                      'col_rows',
                      'col_result',
                      'col_start',
                      'col_user',
                      'col_status',
                    ] as const
                  ).map((key) => (
                    <th
                      key={key}
                      className="px-4 py-3 text-[11px] font-medium tracking-wider text-zinc-400 uppercase"
                    >
                      {t(`exports.history.${key}`)}
                    </th>
                  ))}
                  <th aria-hidden className="w-10" />
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {pageRows.map((session) => {
                  const EntityIcon = ENTITY_ICONS[session.entity_type] ?? Package;
                  const fileName = fileNameOf(session);
                  const err =
                    session.status === 'error'
                      ? Math.max(0, session.target_count - session.success_count)
                      : 0;
                  return (
                    <tr key={session.id} className="transition-colors hover:bg-zinc-50">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2.5">
                          <span className="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-zinc-100 text-zinc-600">
                            <EntityIcon className="size-3.5" aria-hidden />
                          </span>
                          <div className="min-w-0">
                            <div className="truncate font-mono text-[12.5px] font-medium text-ink">
                              {fileName ?? '—'}
                            </div>
                            <div className="truncate text-[11px] text-zinc-400">
                              {t(entityTypeLabelKey(session.entity_type))}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-zinc-600">{session.profile_name ?? '—'}</td>
                      <td className="px-4 py-3">
                        <ModeBadge mode={session.target_scope.toUpperCase()} />
                      </td>
                      <td className="num px-4 py-3 font-mono text-zinc-600">
                        {session.success_count}/{session.target_count}
                      </td>
                      <td className="px-4 py-3">
                        <ResultBar
                          ok={session.success_count}
                          warn={0}
                          err={err}
                          total={session.target_count}
                          width={110}
                        />
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <div className="num font-mono text-[12px] text-zinc-600">
                          {formatStartedAt(session.started_at)}
                        </div>
                        <div className="text-[11px] text-zinc-400">
                          {formatDuration(session.duration_ms)}
                        </div>
                      </td>
                      <td className="px-4 py-3 text-zinc-600">{userName}</td>
                      <td className="px-4 py-3">
                        <StatusPill variant={exportStatusToPillVariant(session.status)} />
                      </td>
                      <td className="px-2 py-3">
                        <div className="flex items-center justify-end gap-0.5">
                          {session.status === 'done' && session.file_path !== null && (
                            <button
                              type="button"
                              onClick={() => onDownload(session.id)}
                              aria-label={t('exports.history.action_download')}
                              title={t('exports.history.action_download')}
                              className="focus-ring grid h-7 w-7 place-items-center rounded-md text-zinc-400 hover:bg-zinc-100 hover:text-ink"
                            >
                              <Download className="size-3.5" aria-hidden />
                            </button>
                          )}
                          <button
                            type="button"
                            onClick={() => onRerun(session.id)}
                            aria-label={t('exports.history.action_rerun')}
                            title={t('exports.history.action_rerun')}
                            className="focus-ring grid h-7 w-7 place-items-center rounded-md text-zinc-400 hover:bg-zinc-100 hover:text-ink"
                          >
                            <RefreshCw className="size-3.5" aria-hidden />
                          </button>
                          <button
                            type="button"
                            onClick={() => onDelete(session.id)}
                            aria-label={t('exports.history.action_delete')}
                            title={t('exports.history.action_delete')}
                            className="focus-ring grid h-7 w-7 place-items-center rounded-md text-zinc-400 hover:bg-brick-50 hover:text-brick-600"
                          >
                            <Trash2 className="size-3.5" aria-hidden />
                          </button>
                          <Link
                            to={`/integrations/exports/sessions/${session.id}`}
                            aria-label={t('exports.history.action_details')}
                            className="focus-ring grid h-7 w-7 place-items-center rounded-md text-zinc-400 hover:bg-zinc-100 hover:text-ink"
                          >
                            <ChevronRight className="size-4" aria-hidden />
                          </Link>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="mt-3 flex items-center justify-between text-[12.5px] text-zinc-500">
        <span className="num font-mono">
          {t('exports.history.shown', {
            shown: pageRows.length === 0 ? 0 : currentPage * PAGE_SIZE + pageRows.length,
            total: filtered.length,
          })}
        </span>
        <div className="flex items-center gap-2">
          <button
            type="button"
            disabled={currentPage === 0}
            onClick={() => setPage((value) => Math.max(0, value - 1))}
            className="focus-ring h-8 rounded-xl px-3 font-medium text-zinc-600 transition enabled:hover:bg-zinc-100 disabled:cursor-not-allowed disabled:text-zinc-300"
          >
            {t('exports.history.prev')}
          </button>
          <button
            type="button"
            disabled={currentPage >= pageCount - 1}
            onClick={() => setPage((value) => Math.min(pageCount - 1, value + 1))}
            className="focus-ring h-8 rounded-xl px-3 font-medium text-zinc-600 transition enabled:hover:bg-zinc-100 disabled:cursor-not-allowed disabled:text-zinc-300"
          >
            {t('exports.history.next')}
          </button>
        </div>
      </div>
    </section>
  );
}
