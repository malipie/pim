import { ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';
import { StatusPill } from '@/components/ui-v2/status-pill';
import { cn } from '@/lib/utils';

import { BACKUP_MOCK } from '../mock-data';

/**
 * MOCK widget — database backup health (design `Dashboard.html` BackupWidget):
 * RPO pill, last-backup time + size, 14-day heatmap. pgBackRest runs but has
 * no status API — backend follow-up tracked in
 * Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
export function BackupWidget() {
  const { t } = useTranslation();

  return (
    <div className="relative rounded-2xl border border-line bg-surface p-7 soft-shadow">
      <MockBadge variant="corner" />
      <div className="flex items-start justify-between">
        <div>
          <div className="flex items-center gap-2 text-[13px] font-medium text-muted-foreground">
            <ShieldCheck className="size-4 text-zinc-500" aria-hidden />
            {t('dashboard.backup.title', { defaultValue: 'Backup bazy' })}
          </div>
          <div className="display mt-1 text-[22px] font-semibold tracking-tight">
            {t('dashboard.backup.healthy', { defaultValue: 'Healthy' })}
          </div>
        </div>
        <StatusPill
          variant="success"
          label={t('dashboard.backup.rpo', { defaultValue: 'RPO 15 min' })}
        />
      </div>

      <div className="mt-6 grid grid-cols-2 gap-5">
        <div>
          <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
            {t('dashboard.backup.last_label', { defaultValue: 'Ostatni' })}
          </div>
          <div className="num mt-1.5 text-[15px] font-semibold">{BACKUP_MOCK.lastRelative}</div>
          <div className="num text-[12px] text-zinc-500">{BACKUP_MOCK.lastAt}</div>
        </div>
        <div>
          <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
            {t('dashboard.backup.size_label', { defaultValue: 'Rozmiar' })}
          </div>
          <div className="num mt-1.5 text-[15px] font-semibold">{BACKUP_MOCK.size}</div>
          <div className="text-[12px] text-zinc-500">{BACKUP_MOCK.engine}</div>
        </div>
      </div>

      <div className="mt-6">
        <div className="mb-2 text-[11px] font-medium uppercase tracking-wider text-zinc-500">
          {t('dashboard.backup.heatmap_label', { defaultValue: 'Ostatnie 14 dni' })}
        </div>
        <div className="flex gap-1.5">
          {BACKUP_MOCK.days.map((day) => (
            <div
              key={day.date}
              className={cn(
                'h-7 flex-1 rounded-md',
                day.ok ? 'bg-emerald-400/85' : 'bg-amber-400/85',
              )}
              title={day.ok ? 'OK' : day.note}
            />
          ))}
        </div>
        <div className="num mt-1.5 flex justify-between text-[10.5px] text-zinc-500">
          <span>{BACKUP_MOCK.days[0]?.date}</span>
          <span>{BACKUP_MOCK.days[BACKUP_MOCK.days.length - 1]?.date}</span>
        </div>
      </div>
    </div>
  );
}
