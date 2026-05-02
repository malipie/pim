import { Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import { BuiltInLockBadge } from './built-in-lock-badge';

export interface AttachedGroup {
  id: string;
  code: string;
  label: Record<string, string> | null;
  icon?: string | null;
  color?: string | null;
  system: boolean;
  attrsCount: number;
  attrsPreview: string[];
}

interface GroupCardProps {
  group: AttachedGroup;
  language: string;
  /** Force locked styling regardless of `group.system` (e.g. built-in section). */
  locked?: boolean;
  onEdit?: (group: AttachedGroup) => void;
  onRemove?: (group: AttachedGroup) => void;
}

/**
 * VIEW-01 (#372) — attribute group row in the modeling Detail
 * (object-types.jsx lines 260–280). Chip strip of the first 8 attributes
 * + "+N więcej" badge on overflow. Pencil + trash on the right when
 * editable.
 */
export function GroupCard({ group, language, locked, onEdit, onRemove }: GroupCardProps) {
  const { t } = useTranslation();
  const isLocked = Boolean(locked || group.system);

  const labelText = resolveGroupLabel(group, language);
  const previewVisible = group.attrsPreview.slice(0, 8);
  const remaining = Math.max(0, group.attrsCount - previewVisible.length);

  return (
    <div
      className={cn(
        'rounded-2xl border p-4',
        isLocked ? 'border-zinc-100 bg-zinc-50/50' : 'border-zinc-200 bg-white',
      )}
    >
      <div className="flex items-center justify-between">
        <div className="flex min-w-0 items-center gap-2">
          <div className="truncate text-[14px] font-semibold tracking-tight">{labelText}</div>
          {isLocked ? <BuiltInLockBadge /> : null}
          <span className="num shrink-0 text-[11px] text-zinc-400">
            {t('object_types.attrs_count', {
              defaultValue: '{{count}} atrybutów',
              count: group.attrsCount,
            })}
          </span>
        </div>
        <div className="flex shrink-0 items-center gap-1">
          {!isLocked && onEdit ? (
            <button
              type="button"
              aria-label={t('object_types.group_card_edit', { defaultValue: 'Edytuj grupę' })}
              onClick={() => onEdit(group)}
              className="rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-900"
            >
              <Pencil className="size-3.5" />
            </button>
          ) : null}
          {!isLocked && onRemove ? (
            <button
              type="button"
              aria-label={t('object_types.group_card_remove', { defaultValue: 'Usuń grupę' })}
              onClick={() => onRemove(group)}
              className="rounded-lg p-1.5 text-zinc-400 transition hover:bg-rose-50 hover:text-rose-600"
            >
              <Trash2 className="size-3.5" />
            </button>
          ) : null}
        </div>
      </div>
      <div className="mt-2.5 flex flex-wrap items-center gap-1">
        {previewVisible.map((code) => (
          <span
            key={code}
            className="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-600"
          >
            {code}
          </span>
        ))}
        {remaining > 0 ? (
          <span className="text-[11px] text-zinc-400">
            {t('object_types.more_attrs', { defaultValue: '+{{count}} więcej', count: remaining })}
          </span>
        ) : null}
      </div>
    </div>
  );
}

function resolveGroupLabel(group: AttachedGroup, language: string): string {
  if (group.label && typeof group.label === 'object') {
    if (group.label[language]) return group.label[language];
    const first = Object.values(group.label).find((v) => typeof v === 'string' && v.length > 0);
    if (first) return first;
  }
  return group.code;
}
