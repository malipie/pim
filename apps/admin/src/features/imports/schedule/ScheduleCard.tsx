import { Clock, MoreHorizontal, Play } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

import type { ImportScheduleRow, NotifyChannel, SchedulePriority } from './types';

interface ScheduleCardProps {
  schedule: ImportScheduleRow;
  toggling: boolean;
  running: boolean;
  onToggle: (schedule: ImportScheduleRow) => void;
  onRunNow: (schedule: ImportScheduleRow) => void;
  onEdit: (schedule: ImportScheduleRow) => void;
  onDelete: (schedule: ImportScheduleRow) => void;
}

const PRIORITY_BADGE: Record<SchedulePriority, string> = {
  high: 'bg-rose-50 text-rose-700',
  normal: 'bg-zinc-100 text-zinc-700',
  low: 'bg-zinc-50 text-zinc-500',
};

const NOTIFY_BADGE: Record<NotifyChannel, string> = {
  slack: 'bg-violet-50 text-violet-700',
  email: 'bg-sky-50 text-sky-700',
  webhook: 'bg-amber-50 text-amber-700',
};

function formatDateTime(value: string | null): string {
  if (value === null) {
    return '—';
  }
  return new Intl.DateTimeFormat('pl-PL', { dateStyle: 'short', timeStyle: 'short' }).format(
    new Date(value),
  );
}

export function ScheduleCard({
  schedule,
  toggling,
  running,
  onToggle,
  onRunNow,
  onEdit,
  onDelete,
}: ScheduleCardProps) {
  const { t } = useTranslation();
  return (
    <article className="rounded-2xl border border-zinc-100 bg-white p-4 soft-shadow flex flex-col gap-3">
      <div className="flex items-start gap-3">
        <div className="h-9 w-9 rounded-xl bg-zinc-100 text-zinc-700 grid place-items-center shrink-0">
          <Clock className="h-4 w-4" aria-hidden="true" />
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <div className="text-[14px] font-semibold tracking-tight truncate">{schedule.name}</div>
            <span
              className={cn(
                'text-[10.5px] font-medium px-1.5 py-0.5 rounded uppercase tracking-wider',
                PRIORITY_BADGE[schedule.priority],
              )}
            >
              {t(`imports.schedule.priority.${schedule.priority}`)}
            </span>
          </div>
          <div className="font-mono text-[11.5px] text-zinc-500 mt-0.5 truncate">
            {schedule.cronHuman ?? schedule.cron}
          </div>
        </div>
        <label className="flex items-center gap-2 shrink-0 text-[11.5px] cursor-pointer">
          <input
            type="checkbox"
            checked={schedule.enabled}
            disabled={toggling}
            onChange={() => onToggle(schedule)}
            aria-label={t('imports.schedule.card.toggle_aria', { name: schedule.name })}
          />
          {schedule.enabled
            ? t('imports.schedule.card.enabled')
            : t('imports.schedule.card.disabled')}
        </label>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('imports.schedule.card.more_actions')}
            >
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => onRunNow(schedule)} disabled={running}>
              <Play className="h-4 w-4" />
              {t('imports.schedule.card.run_now')}
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => onEdit(schedule)}>
              {t('imports.schedule.card.edit')}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={() => onDelete(schedule)} className="text-rose-600">
              {t('imports.schedule.card.delete')}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <div className="grid grid-cols-2 gap-x-3 gap-y-1.5 text-[11.5px]">
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-400">
            {t('imports.schedule.card.source')}
          </div>
          <div className="text-zinc-700 truncate">
            {schedule.source?.name ?? t('imports.schedule.card.no_source')}
          </div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-400">
            {t('imports.schedule.card.profile')}
          </div>
          <div className="text-zinc-700 truncate">
            {schedule.profile?.name ?? t('imports.schedule.card.no_profile')}
          </div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-400">
            {t('imports.schedule.card.next_run')}
          </div>
          <div className="font-mono text-zinc-700">{formatDateTime(schedule.nextRun)}</div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-400">
            {t('imports.schedule.card.last_run')}
          </div>
          <div className="font-mono text-zinc-700">{formatDateTime(schedule.lastRunAt)}</div>
        </div>
      </div>

      {schedule.notifyChannels.length > 0 ? (
        <div className="flex items-center gap-1.5 flex-wrap">
          <span className="text-[10px] uppercase tracking-wider text-zinc-400">
            {t('imports.schedule.card.notify')}
          </span>
          {schedule.notifyChannels.map((ch) => (
            <span
              key={ch}
              className={cn(
                'text-[10.5px] font-medium px-1.5 py-0.5 rounded uppercase tracking-wider',
                NOTIFY_BADGE[ch],
              )}
            >
              {ch}
            </span>
          ))}
        </div>
      ) : null}
    </article>
  );
}
