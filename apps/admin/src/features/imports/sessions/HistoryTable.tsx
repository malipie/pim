import { useTranslation } from 'react-i18next';

import { HistoryRow } from './HistoryRow';
import type { ImportSessionRow } from './types';

interface HistoryTableProps {
  rows: ReadonlyArray<ImportSessionRow>;
  total: number;
}

export function HistoryTable({ rows, total }: HistoryTableProps) {
  const { t } = useTranslation();
  return (
    <div className="overflow-hidden rounded-2xl border border-zinc-100 bg-white soft-shadow">
      <div className="grid grid-cols-[28px_minmax(0,1.6fr)_minmax(0,1.2fr)_110px_90px_minmax(0,1fr)_130px_120px_36px] gap-3 text-[10.5px] uppercase tracking-wider text-zinc-400 font-medium px-5 py-2.5 border-b border-zinc-100 bg-zinc-50/40">
        <div />
        <div>{t('imports.sessions.history.col_file')}</div>
        <div>{t('imports.sessions.history.col_profile')}</div>
        <div>{t('imports.sessions.history.col_mode')}</div>
        <div className="text-right">{t('imports.sessions.history.col_rows')}</div>
        <div>{t('imports.sessions.history.col_breakdown')}</div>
        <div>{t('imports.sessions.history.col_started')}</div>
        <div>{t('imports.sessions.history.col_status')}</div>
        <div />
      </div>
      <div className="divide-y divide-zinc-50">
        {rows.length > 0 ? (
          rows.map((row) => <HistoryRow key={row.id} row={row} />)
        ) : (
          <div className="px-5 py-8 text-center text-[13px] text-zinc-400">
            {t('imports.sessions.history.empty_filtered')}
          </div>
        )}
      </div>
      <div className="px-5 py-3 flex items-center justify-between text-[11.5px] text-zinc-500 border-t border-zinc-100">
        <span>{t('imports.sessions.history.shown_count', { count: rows.length, total })}</span>
      </div>
    </div>
  );
}
