import { MoreHorizontal, PlugZap } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import { HealthDot, SourceIcon } from '../primitives';
import type { ImportSourceRow } from './types';

interface SourceCardProps {
  source: ImportSourceRow;
  testing: boolean;
  onTest: (source: ImportSourceRow) => void;
  onEdit: (source: ImportSourceRow) => void;
  onDelete: (source: ImportSourceRow) => void;
}

export function SourceCard({ source, testing, onTest, onEdit, onDelete }: SourceCardProps) {
  const { t } = useTranslation();
  const typeLabel = t(`imports.sources.types.${source.type}`);
  const healthLabel = t(`imports.sources.health.${source.health}`);

  return (
    <article className="rounded-2xl border border-zinc-100 bg-white p-5 soft-shadow">
      <div className="flex items-start gap-3">
        <SourceIcon type={source.type} size={18} />
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <div className="text-[15px] font-semibold tracking-tight">{source.name}</div>
            <span className="text-[10.5px] font-mono px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600 uppercase tracking-wider">
              {typeLabel}
            </span>
            {source.autotrigger ? (
              <span className="text-[10.5px] font-medium px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 flex items-center gap-1">
                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                {t('imports.sources.card.autotrigger')}
              </span>
            ) : null}
          </div>
          <div className="font-mono text-[11.5px] text-zinc-500 mt-1 truncate">
            {source.host ?? source.path ?? '—'}
          </div>
        </div>
        <div className="flex items-center gap-1.5 shrink-0">
          <span className="text-[11px] font-medium px-2 py-0.5 rounded-md flex items-center gap-1.5 bg-zinc-50 text-zinc-700">
            <HealthDot health={source.health} />
            {healthLabel}
          </span>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                aria-label={t('imports.sources.card.more_actions')}
              >
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={() => onTest(source)} disabled={testing}>
                <PlugZap className="h-4 w-4" />
                {t('imports.sources.card.test_connection')}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => onEdit(source)}>
                {t('imports.sources.card.edit')}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => onDelete(source)} className="text-rose-600">
                {t('imports.sources.card.delete')}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2.5 text-[12px]">
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-500">
            {t('imports.sources.card.path')}
          </div>
          <div className="font-mono text-zinc-700 truncate">{source.path ?? '—'}</div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-500">
            {t('imports.sources.card.pattern')}
          </div>
          <div className="font-mono text-zinc-700 truncate">{source.filePattern ?? '—'}</div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-500">
            {t('imports.sources.card.polling')}
          </div>
          <div className="font-mono text-zinc-700">
            {source.pollIntervalSec
              ? t('imports.sources.card.poll_every', { sec: source.pollIntervalSec })
              : '—'}
          </div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-500">
            {t('imports.sources.card.profile')}
          </div>
          <div className="text-zinc-700 truncate">
            {source.profile?.name ?? t('imports.sources.card.no_profile')}
          </div>
        </div>
      </div>

      {source.healthNote ? (
        <div className="mt-3 rounded-md bg-zinc-50 px-3 py-2 text-[11.5px] text-zinc-600 leading-relaxed">
          {source.healthNote}
        </div>
      ) : null}

      <div className="mt-3 pt-3 border-t border-zinc-100 flex items-center justify-between text-[11px] text-zinc-500">
        <span>
          {t('imports.sources.card.files24h')}{' '}
          <span className="font-mono text-zinc-700 num">{source.files24h}</span>
        </span>
        <span>
          {source.lastPickupAt
            ? t('imports.sources.card.last_pickup', {
                date: new Intl.DateTimeFormat('pl-PL', {
                  dateStyle: 'short',
                  timeStyle: 'short',
                }).format(new Date(source.lastPickupAt)),
              })
            : t('imports.sources.card.never_pickup')}
        </span>
      </div>
    </article>
  );
}
