import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import type { SchedulePriority, UpcomingScheduleEntry } from './types';

interface NextRunsTimelineProps {
  entries: ReadonlyArray<UpcomingScheduleEntry>;
  horizonHours: number;
}

const PRIORITY_RING: Record<SchedulePriority, string> = {
  high: 'bg-rose-500 ring-rose-200',
  normal: 'bg-zinc-900 ring-zinc-200',
  low: 'bg-zinc-400 ring-zinc-200',
};

export function NextRunsTimeline({ entries, horizonHours }: NextRunsTimelineProps) {
  const { t } = useTranslation();
  const now = Date.now();
  const horizonMs = horizonHours * 60 * 60 * 1000;
  const ticks = Array.from({ length: 12 }, (_, i) => i);

  return (
    <section className="rounded-2xl border border-zinc-100 bg-white p-4 soft-shadow">
      <div className="flex items-center gap-2 mb-3">
        <div className="text-[11px] uppercase tracking-wider text-zinc-500 font-medium">
          {t('imports.schedule.timeline.eyebrow', { hours: horizonHours })}
        </div>
        <div className="h-px bg-zinc-100 flex-1" />
        <div className="text-[11px] font-mono text-zinc-500">
          {t('imports.schedule.timeline.now')}{' '}
          {new Intl.DateTimeFormat('pl-PL', { hour: '2-digit', minute: '2-digit' }).format(
            new Date(),
          )}
        </div>
      </div>

      <div className="relative h-20">
        <div className="absolute top-1/2 left-0 right-0 h-px bg-zinc-200" />
        {ticks.map((i) => (
          <div
            key={i}
            className="absolute top-1/2 -translate-y-1/2 flex flex-col items-center"
            style={{ left: `${(i / 11) * 100}%` }}
          >
            <div className="h-2 w-px bg-zinc-200" />
            <div className="text-[9.5px] font-mono text-zinc-500 mt-1">
              {`+${Math.round(((i / 11) * horizonHours) / 1) | 0}h`}
            </div>
          </div>
        ))}
        <div className="absolute top-0 bottom-0 left-0 w-px bg-zinc-900" />

        {entries.map((entry) => {
          const time = new Date(entry.next_run).getTime();
          const pct = Math.min(100, Math.max(0, ((time - now) / horizonMs) * 100));
          return (
            <div
              key={entry.id}
              className="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 flex flex-col items-center"
              style={{ left: `${pct}%` }}
            >
              <div
                role="img"
                className={cn('h-3 w-3 rounded-full ring-4', PRIORITY_RING[entry.priority])}
                aria-label={t('imports.schedule.timeline.dot_aria', {
                  name: entry.name,
                  time: new Intl.DateTimeFormat('pl-PL', {
                    hour: '2-digit',
                    minute: '2-digit',
                    day: '2-digit',
                    month: '2-digit',
                  }).format(new Date(entry.next_run)),
                })}
              />
              <div className="absolute top-full mt-2 text-[10px] font-mono text-zinc-600 whitespace-nowrap">
                {new Intl.DateTimeFormat('pl-PL', { hour: '2-digit', minute: '2-digit' }).format(
                  new Date(entry.next_run),
                )}
              </div>
            </div>
          );
        })}
      </div>

      {entries.length === 0 ? (
        <div className="mt-2 text-center text-[12px] text-zinc-500">
          {t('imports.schedule.timeline.empty')}
        </div>
      ) : null}
    </section>
  );
}
