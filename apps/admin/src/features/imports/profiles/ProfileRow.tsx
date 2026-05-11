import { MoreHorizontal } from 'lucide-react';
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

interface ProfileRowProps {
  profile: ImportProfileRow;
  onEdit: (profile: ImportProfileRow) => void;
  onDuplicate: (profile: ImportProfileRow) => void;
  onExport: (profile: ImportProfileRow) => void;
  onDelete: (profile: ImportProfileRow) => void;
}

export function ProfileRow({ profile, onEdit, onDuplicate, onExport, onDelete }: ProfileRowProps) {
  const { t } = useTranslation();
  const cols = columnsCount(profile);
  const format = detectFormat(profile);

  return (
    <div className="grid grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_90px_120px_90px_110px_110px_36px] gap-3 items-center px-5 py-3 hover:bg-zinc-50/70 transition">
      <div className="min-w-0">
        <div className="text-[13px] font-medium truncate">{profile.name}</div>
        <div className="font-mono text-[11px] text-zinc-500 truncate">{profile.code}</div>
      </div>
      <div className="text-[12px] text-zinc-700 truncate">{targetCodeOf(profile)}</div>
      <FormatPill format={format} />
      <ModeBadge mode={profile.mode} />
      <div className="text-right num text-[12.5px]">{cols}</div>
      <div className="font-mono text-[10.5px] uppercase tracking-wider text-zinc-600">
        {profile.locale ?? '—'}
      </div>
      <div className="text-[11.5px] text-zinc-500">
        {profile.lastUsedAt
          ? new Intl.DateTimeFormat('pl-PL', { dateStyle: 'short' }).format(
              new Date(profile.lastUsedAt),
            )
          : '—'}
      </div>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="icon" aria-label={t('imports.profiles.card.more_actions')}>
            <MoreHorizontal className="h-4 w-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onClick={() => onEdit(profile)}>
            {t('imports.profiles.card.edit')}
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => onDuplicate(profile)}>
            {t('imports.profiles.card.duplicate')}
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => onExport(profile)}>
            {t('imports.profiles.card.export')}
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => onDelete(profile)} className="text-rose-600">
            {t('imports.profiles.card.delete')}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}
