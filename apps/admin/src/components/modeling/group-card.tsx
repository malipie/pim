import { Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { isLegacyOptionalSystemGroupCode } from '@/lib/legacy-attribute-groups';
import { cn } from '@/lib/utils';

import { BuiltInLockBadge } from './built-in-lock-badge';

export type GroupDisplayMode = 'tab' | 'stacked';

export interface AttachedGroup {
  id: string;
  code: string;
  label: Record<string, string> | null;
  icon?: string | null;
  color?: string | null;
  system: boolean;
  attrsCount: number;
  attrsPreview: string[];
  /**
   * MODR-01 (#923) — placement of this group on the object detail page.
   * `tab` renders the group as its own tab; `stacked` renders it as an
   * inline section under the default "Attributes" tab. Optional for
   * backwards compatibility with older payloads (defaults to `tab`).
   */
  displayMode?: GroupDisplayMode;
  /**
   * #1349 — persisted order of this group within the ObjectType. Drives
   * the left-to-right tab order on the object detail page. Optional for
   * backwards compatibility with older payloads (defaults to 0).
   */
  position?: number;
}

interface GroupCardProps {
  group: AttachedGroup;
  language: string;
  /** Force locked styling regardless of `group.system` (e.g. built-in section). */
  locked?: boolean;
  onEdit?: (group: AttachedGroup) => void;
  onRemove?: (group: AttachedGroup) => void;
  /**
   * MODR-04 (#926) — when supplied, the card shows a `tab|stacked`
   * segmented control next to the title. The callback is invoked with
   * the next mode; the consumer is responsible for persisting via
   * `PATCH /api/object_types/{id}/groups/{groupId}` (MODR-01).
   */
  onDisplayModeChange?: (group: AttachedGroup, next: GroupDisplayMode) => void;
}

/**
 * VIEW-01 (#372) — attribute group row in the modeling Detail
 * (object-types.jsx lines 260–280). Chip strip of the first 8 attributes
 * + "+N więcej" badge on overflow. Pencil + trash on the right when
 * editable.
 */
export function GroupCard({
  group,
  language,
  locked,
  onEdit,
  onRemove,
  onDisplayModeChange,
}: GroupCardProps) {
  const { t } = useTranslation();
  const isLegacyOptionalGroup = isLegacyOptionalSystemGroupCode(group.code);
  const isLocked = Boolean(locked || (group.system && !isLegacyOptionalGroup));

  const labelText = resolveGroupLabel(group, language);
  const previewVisible = group.attrsPreview.slice(0, 8);
  const remaining = Math.max(0, group.attrsCount - previewVisible.length);
  const displayMode: GroupDisplayMode = group.displayMode ?? 'tab';

  return (
    <div
      className={cn(
        'rounded-2xl border p-4',
        isLocked ? 'border-zinc-100 bg-zinc-50/50' : 'border-zinc-200 bg-white',
      )}
    >
      <div className="flex items-center justify-between gap-2">
        <div className="flex min-w-0 items-center gap-2">
          <div className="truncate text-[14px] font-semibold tracking-tight">{labelText}</div>
          {isLocked ? <BuiltInLockBadge /> : null}
          <span className="num shrink-0 text-[11px] text-zinc-500">
            {t('object_types.attrs_count', {
              defaultValue: '{{count}} atrybutów',
              count: group.attrsCount,
            })}
          </span>
        </div>
        <div className="flex shrink-0 items-center gap-1">
          {onDisplayModeChange ? (
            <DisplayModeSegmented
              value={displayMode}
              onChange={(next) => onDisplayModeChange(group, next)}
              labelTab={t('object_types.group_card_display_mode_tab', {
                defaultValue: 'Zakładka',
              })}
              labelStacked={t('object_types.group_card_display_mode_stacked', {
                defaultValue: 'Inline',
              })}
              tooltip={t('object_types.group_card_display_mode_hint', {
                defaultValue: 'Zakładka — własny tab. Inline — sekcja w karcie atrybutów.',
              })}
            />
          ) : null}
          {!isLocked && onEdit ? (
            <button
              type="button"
              aria-label={t('object_types.group_card_edit', { defaultValue: 'Edytuj grupę' })}
              onClick={() => onEdit(group)}
              className="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900"
            >
              <Pencil className="size-3.5" />
            </button>
          ) : null}
          {!isLocked && onRemove ? (
            <button
              type="button"
              aria-label={t('object_types.group_card_remove', { defaultValue: 'Usuń grupę' })}
              onClick={() => onRemove(group)}
              className="rounded-lg p-1.5 text-zinc-500 transition hover:bg-rose-50 hover:text-rose-600"
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
          <span className="text-[11px] text-zinc-500">
            {t('object_types.more_attrs', { defaultValue: '+{{count}} więcej', count: remaining })}
          </span>
        ) : null}
      </div>
    </div>
  );
}

/**
 * MODR-04 (#926) — segmented `tab|stacked` selector used inline in the
 * group card and in the ObjectType wizard row. Kept as a local helper —
 * the design system doesn't have a generic segmented control yet and
 * the use-case is narrow (two options, short labels).
 */
export function DisplayModeSegmented({
  value,
  onChange,
  labelTab,
  labelStacked,
  tooltip,
  disabled,
}: {
  value: GroupDisplayMode;
  onChange: (next: GroupDisplayMode) => void;
  labelTab: string;
  labelStacked: string;
  tooltip?: string;
  disabled?: boolean;
}) {
  const options: { mode: GroupDisplayMode; label: string }[] = [
    { mode: 'tab', label: labelTab },
    { mode: 'stacked', label: labelStacked },
  ];
  return (
    <div
      role="radiogroup"
      aria-label={tooltip}
      title={tooltip}
      className={cn(
        'inline-flex h-7 items-center rounded-lg border border-zinc-200 bg-zinc-50 p-0.5 text-[11px] font-medium',
        disabled ? 'opacity-60' : '',
      )}
    >
      {options.map((opt) => {
        const isActive = value === opt.mode;
        return (
          // biome-ignore lint/a11y/useSemanticElements: segmented control via buttons is a deliberate styling choice; semantics still convey via role=radio + aria-checked
          <button
            key={opt.mode}
            type="button"
            role="radio"
            aria-checked={isActive}
            disabled={disabled}
            onClick={() => {
              if (!disabled && opt.mode !== value) onChange(opt.mode);
            }}
            className={cn(
              'rounded-md px-2 py-0.5 transition',
              isActive ? 'bg-white text-zinc-900 soft-shadow' : 'text-zinc-500 hover:text-zinc-800',
            )}
          >
            {opt.label}
          </button>
        );
      })}
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
