import { RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import { SYNCS, type SyncStatus } from '../mock-data';

/**
 * MOCK component — integration sync status panel.
 * Backend: GET /api/integrations/status + POST /api/integrations/{id}/sync (do dorobienia).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
const STATUS_DOT: Record<SyncStatus, string> = {
  ok: 'bg-accent-emerald',
  warn: 'bg-accent-amber',
  err: 'bg-accent-rose',
};

export function SyncsStatusPanel() {
  const { t } = useTranslation();
  return (
    <div className="rounded-2xl border border-line bg-surface soft-shadow">
      <div className="flex items-center justify-between border-b border-line px-5 py-4">
        <h3 className="text-[15px] font-semibold text-ink">{t('dashboard.syncs.title')}</h3>
        {/* MOCK: button "Wymuś synchronizację" — wymaga POST /api/integrations/{id}/sync (#TBD) */}
        <button
          type="button"
          disabled
          aria-disabled="true"
          className="inline-flex cursor-not-allowed items-center gap-1.5 rounded-xl border border-line px-2.5 py-1 text-[12px] text-muted-foreground"
          title={t('dashboard.syncs.force_disabled') ?? ''}
        >
          <RefreshCw className="size-3" />
          {t('dashboard.syncs.force')}
        </button>
      </div>
      <ul className="divide-y divide-line">
        {SYNCS.map((s) => (
          <li key={s.id} className="flex items-center gap-3 px-5 py-3 text-[13.5px] text-ink-2">
            <span className={cn('size-2 rounded-full', STATUS_DOT[s.status])} />
            <span className="flex-1 font-medium text-ink">{s.label}</span>
            <span className="hidden text-[12px] text-muted-foreground sm:inline">{s.lastSync}</span>
            <span className="num inline-flex items-center gap-1 rounded-md bg-accent-emerald/10 px-1.5 py-0.5 text-[11px] font-medium text-accent-emerald">
              ↑ {s.pushed}
            </span>
            {s.failed > 0 && (
              <span className="num inline-flex items-center gap-1 rounded-md bg-accent-rose/10 px-1.5 py-0.5 text-[11px] font-medium text-accent-rose">
                ✕ {s.failed}
              </span>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
