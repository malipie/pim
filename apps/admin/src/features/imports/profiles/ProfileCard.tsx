import { Copy, Download, Layers, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import { FormatPill, ModeBadge } from '../primitives';
import { columnsCount, detectFormat, type ImportProfileRow, targetCodeOf } from './types';

interface ProfileCardProps {
  profile: ImportProfileRow;
  onEdit: (profile: ImportProfileRow) => void;
  onDuplicate: (profile: ImportProfileRow) => void;
  onExport: (profile: ImportProfileRow) => void;
  onDelete: (profile: ImportProfileRow) => void;
}

export function ProfileCard({
  profile,
  onEdit,
  onDuplicate,
  onExport,
  onDelete,
}: ProfileCardProps) {
  const { t } = useTranslation();
  const cols = columnsCount(profile);
  const format = detectFormat(profile);

  return (
    <article className="rounded-2xl border border-zinc-100 bg-white p-4 soft-shadow flex flex-col gap-3">
      <div className="flex items-start gap-3">
        <div className="h-10 w-10 rounded-xl grid place-items-center shrink-0 bg-orange-50 text-orange-700">
          <Layers className="h-5 w-5" aria-hidden="true" />
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1.5 flex-wrap">
            <FormatPill format={format} />
            <ModeBadge mode={profile.mode} />
          </div>
          <div className="text-[14px] font-semibold tracking-tight mt-1.5 leading-snug line-clamp-2">
            {profile.name}
          </div>
          <div className="font-mono text-[11.5px] text-zinc-500 mt-0.5 truncate">
            {profile.code}
          </div>
        </div>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('imports.profiles.card.more_actions')}
            >
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => onEdit(profile)}>
              <Pencil className="h-4 w-4" />
              {t('imports.profiles.card.edit')}
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => onDuplicate(profile)}>
              <Copy className="h-4 w-4" />
              {t('imports.profiles.card.duplicate')}
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => onExport(profile)}>
              <Download className="h-4 w-4" />
              {t('imports.profiles.card.export')}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={() => onDelete(profile)} className="text-rose-600">
              <Trash2 className="h-4 w-4" />
              {t('imports.profiles.card.delete')}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <div className="grid grid-cols-2 gap-x-3 gap-y-2 text-[11.5px]">
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-500">
            {t('imports.profiles.card.target')}
          </div>
          <div className="text-zinc-700 truncate">{targetCodeOf(profile)}</div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-500">
            {t('imports.profiles.card.encoding')}
          </div>
          <div className="font-mono text-zinc-700">
            {profile.encoding ?? '—'}
            {profile.delimiter ? ` · "${profile.delimiter}"` : ''}
          </div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-500">
            {t('imports.profiles.card.columns')}
          </div>
          <div className="text-zinc-700 num">
            {t('imports.profiles.card.columns_count', { count: cols })}
          </div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-500">
            {t('imports.profiles.card.locale')}
          </div>
          <div className="text-zinc-700 font-mono uppercase tracking-wider">
            {profile.locale ?? '—'}
          </div>
        </div>
      </div>

      <div className="pt-3 border-t border-zinc-100 text-[10.5px] text-zinc-500 font-mono">
        {profile.lastUsedAt
          ? t('imports.profiles.card.last_used', {
              date: new Intl.DateTimeFormat('pl-PL', {
                dateStyle: 'short',
                timeStyle: 'short',
              }).format(new Date(profile.lastUsedAt)),
            })
          : t('imports.profiles.card.never_used')}
      </div>
    </article>
  );
}
