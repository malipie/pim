import { ChevronDown, ChevronRight, Search, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type AttributePermissionLevel = 'view' | 'edit' | 'restricted' | null;

export interface AttributePermsApiAttribute {
  id: string;
  code: string;
  label: Record<string, string>;
  type: string;
  is_localizable: boolean;
  is_required: boolean;
  permission_level: AttributePermissionLevel;
}

export interface AttributePermsApiGroup {
  group_id: string | null;
  group_code: string | null;
  group_label: Record<string, string> | null;
  attributes: AttributePermsApiAttribute[];
}

type FilterMode = 'all' | 'view' | 'edit' | 'restricted' | 'overridden';

interface AttributePermissionsSectionProps {
  /** Catalogue loaded from backend (one render = one snapshot). */
  groups: AttributePermsApiGroup[];
  /** Current draft state keyed by attribute id. */
  draft: Record<string, AttributePermissionLevel>;
  onChange: (next: Record<string, AttributePermissionLevel>) => void;
  loading?: boolean;
  disabled?: boolean;
}

/**
 * Role editor polish (marathon-3 / #847) — presentational variant of
 * the per-attribute permission override grid. State lives in the
 * parent `RoleEditorPage`, which calls both `PATCH /api/roles/{id}`
 * and `PUT /api/roles/{id}/attribute-permissions` from a single
 * submit handler. The standalone "Tab" with its own save button is
 * gone — the polish ticket asked for unified save.
 *
 * Search + filter chips + bulk-apply per-group + 3-state segmented
 * control per row stay identical to the previous tab implementation;
 * the only structural change is who owns the draft (now parent).
 */
export function AttributePermissionsSection({
  groups,
  draft,
  onChange,
  loading = false,
  disabled = false,
}: AttributePermissionsSectionProps) {
  const { t, i18n } = useTranslation();
  const locale = i18n.language;
  const [filter, setFilter] = useState<FilterMode>('all');
  const [search, setSearch] = useState('');
  const [expanded, setExpanded] = useState<Set<string>>(() => {
    const keys = new Set<string>();
    for (const group of groups) {
      if (group.attributes.some((a) => a.permission_level !== null)) {
        keys.add(group.group_id ?? '__ungrouped__');
      }
    }
    return keys;
  });

  const setLevel = (attrId: string, level: AttributePermissionLevel) => {
    onChange({ ...draft, [attrId]: level });
  };

  const applyToGroup = (group: AttributePermsApiGroup, level: AttributePermissionLevel) => {
    const next = { ...draft };
    for (const attr of visibleAttributes(group, search, filter, draft)) {
      next[attr.id] = level;
    }
    onChange(next);
  };

  const overrideCount = useMemo(
    () => Object.values(draft).filter((l) => l !== null).length,
    [draft],
  );

  if (loading) {
    return <div className="h-48 animate-pulse rounded-md border bg-muted/30" />;
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative min-w-[240px] flex-1">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <Input
            type="search"
            placeholder={t('settings.roles.attr_perms.search_placeholder')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-9"
          />
        </div>
        <FilterChips value={filter} onChange={setFilter} />
        <span className="ml-auto text-xs text-muted-foreground">
          {t('settings.roles.attr_perms.override_count', { count: overrideCount })}
        </span>
      </div>

      <div className="space-y-2">
        {groups.map((group) => {
          const visible = visibleAttributes(group, search, filter, draft);
          if (visible.length === 0) return null;
          const groupKey = group.group_id ?? '__ungrouped__';
          const isExpanded = expanded.has(groupKey);
          return (
            <GroupPanel
              key={groupKey}
              group={group}
              visible={visible}
              draft={draft}
              onChangeLevel={setLevel}
              onBulkApply={(level) => applyToGroup(group, level)}
              isExpanded={isExpanded}
              onToggle={() => {
                setExpanded((prev) => {
                  const next = new Set(prev);
                  if (next.has(groupKey)) next.delete(groupKey);
                  else next.add(groupKey);
                  return next;
                });
              }}
              locale={locale}
              disabled={disabled}
            />
          );
        })}
        {groups.length === 0 ? (
          <div className="rounded-md border border-dashed bg-muted/20 px-3 py-6 text-center text-xs text-muted-foreground">
            {t('settings.roles.attr_perms.empty_catalogue')}
          </div>
        ) : null}
      </div>
    </div>
  );
}

interface GroupPanelProps {
  group: AttributePermsApiGroup;
  visible: AttributePermsApiAttribute[];
  draft: Record<string, AttributePermissionLevel>;
  onChangeLevel: (attrId: string, level: AttributePermissionLevel) => void;
  onBulkApply: (level: AttributePermissionLevel) => void;
  isExpanded: boolean;
  onToggle: () => void;
  locale: string;
  disabled: boolean;
}

function GroupPanel({
  group,
  visible,
  draft,
  onChangeLevel,
  onBulkApply,
  isExpanded,
  onToggle,
  locale,
  disabled,
}: GroupPanelProps) {
  const { t } = useTranslation();
  const groupLabel =
    group.group_label?.[locale] ??
    group.group_label?.en ??
    group.group_code ??
    t('settings.roles.attr_perms.group_ungrouped');

  const visibleLevels = new Set(visible.map((a) => draft[a.id] ?? '__none__'));
  const isMixed = visibleLevels.size > 1;
  const allLevel = visibleLevels.size === 1 ? Array.from(visibleLevels)[0] : null;

  return (
    <div className="overflow-hidden rounded-md border bg-background shadow-sm">
      <div className="flex items-center justify-between gap-3 border-b bg-muted/40 px-3 py-2">
        <button
          type="button"
          onClick={onToggle}
          className="flex items-center gap-2 text-sm font-medium hover:underline"
          aria-expanded={isExpanded}
        >
          {isExpanded ? (
            <ChevronDown className="size-4" aria-hidden="true" />
          ) : (
            <ChevronRight className="size-4" aria-hidden="true" />
          )}
          <ShieldCheck className="size-3.5 text-accent-violet" aria-hidden="true" />
          <span>{groupLabel}</span>
          <span className="text-xs font-normal text-muted-foreground">
            ({t('settings.roles.attr_perms.attribute_count', { count: visible.length })})
          </span>
          {isMixed ? (
            <span className="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-700 ring-1 ring-amber-200">
              {t('settings.roles.attr_perms.mixed')}
            </span>
          ) : null}
        </button>
        <div className="flex items-center gap-1">
          {(['restricted', 'view', 'edit'] as const).map((level) => (
            <button
              key={level}
              type="button"
              onClick={() => onBulkApply(level)}
              disabled={disabled}
              className={cn(
                'rounded-md border px-2 py-1 text-[11px] font-medium transition-colors',
                allLevel === level
                  ? levelStyle(level, true)
                  : 'border-transparent text-muted-foreground hover:bg-background',
              )}
              title={t('settings.roles.attr_perms.bulk_hint', {
                level: t(`settings.roles.attr_perms.level.${level}`),
              })}
            >
              {t(`settings.roles.attr_perms.bulk_${level}`)}
            </button>
          ))}
          <button
            type="button"
            onClick={() => onBulkApply(null)}
            disabled={disabled}
            className={cn(
              'rounded-md border px-2 py-1 text-[11px] font-medium transition-colors',
              allLevel === '__none__'
                ? 'border-input bg-muted text-foreground'
                : 'border-transparent text-muted-foreground hover:bg-background',
            )}
            title={t('settings.roles.attr_perms.bulk_clear_hint')}
          >
            {t('settings.roles.attr_perms.bulk_clear')}
          </button>
        </div>
      </div>
      {isExpanded ? (
        <ul className="divide-y">
          {visible.map((attr) => (
            <AttributeRow
              key={attr.id}
              attr={attr}
              level={draft[attr.id] ?? null}
              onChange={(level) => onChangeLevel(attr.id, level)}
              locale={locale}
              disabled={disabled}
            />
          ))}
        </ul>
      ) : null}
    </div>
  );
}

function AttributeRow({
  attr,
  level,
  onChange,
  locale,
  disabled,
}: {
  attr: AttributePermsApiAttribute;
  level: AttributePermissionLevel;
  onChange: (level: AttributePermissionLevel) => void;
  locale: string;
  disabled: boolean;
}) {
  const { t } = useTranslation();
  const label = attr.label[locale] ?? attr.label.en ?? attr.code;
  return (
    <li className="flex items-center justify-between gap-3 px-3 py-2 hover:bg-muted/20">
      <div className="min-w-0 flex-1 space-y-0.5">
        <div className="flex items-center gap-2 text-sm">
          <span className="font-medium">{label}</span>
          <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
            {attr.code}
          </span>
          <span className="rounded bg-violet-50 px-1.5 py-0.5 text-[10px] font-medium text-violet-700 ring-1 ring-violet-200">
            {attr.type}
          </span>
          {attr.is_localizable ? (
            <span className="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 ring-1 ring-blue-200">
              {t('settings.roles.attr_perms.localizable')}
            </span>
          ) : null}
          {attr.is_required ? (
            <span className="rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-medium text-rose-700 ring-1 ring-rose-200">
              {t('settings.roles.attr_perms.required')}
            </span>
          ) : null}
        </div>
      </div>
      <div className="flex items-center gap-1 rounded-md border bg-muted/40 p-0.5">
        {(['restricted', 'view', 'edit'] as const).map((option) => (
          <button
            key={option}
            type="button"
            onClick={() => onChange(option)}
            disabled={disabled}
            className={cn(
              'rounded px-2 py-1 text-[11px] font-medium transition-colors',
              level === option
                ? levelStyle(option, true)
                : 'text-muted-foreground hover:bg-background',
            )}
            aria-pressed={level === option}
          >
            {t(`settings.roles.attr_perms.level.${option}`)}
          </button>
        ))}
        <button
          type="button"
          onClick={() => onChange(null)}
          disabled={disabled}
          className={cn(
            'rounded px-2 py-1 text-[11px] font-medium transition-colors',
            level === null
              ? 'bg-background text-foreground'
              : 'text-muted-foreground hover:bg-background',
          )}
          aria-pressed={level === null}
          title={t('settings.roles.attr_perms.level.inherit_hint')}
        >
          {t('settings.roles.attr_perms.level.inherit')}
        </button>
      </div>
    </li>
  );
}

function FilterChips({
  value,
  onChange,
}: {
  value: FilterMode;
  onChange: (next: FilterMode) => void;
}) {
  const { t } = useTranslation();
  const options: FilterMode[] = ['all', 'overridden', 'view', 'edit', 'restricted'];
  return (
    <div className="inline-flex rounded-md border bg-background p-0.5">
      {options.map((option) => (
        <button
          key={option}
          type="button"
          onClick={() => onChange(option)}
          className={cn(
            'rounded px-2 py-1 text-[11px] font-medium transition-colors',
            value === option
              ? 'bg-foreground text-background'
              : 'text-muted-foreground hover:bg-muted',
          )}
        >
          {t(`settings.roles.attr_perms.filter.${option}`)}
        </button>
      ))}
    </div>
  );
}

function visibleAttributes(
  group: AttributePermsApiGroup,
  search: string,
  filter: FilterMode,
  draft: Record<string, AttributePermissionLevel>,
): AttributePermsApiAttribute[] {
  const needle = search.trim().toLowerCase();
  return group.attributes.filter((attr) => {
    const level = draft[attr.id] ?? null;
    if (filter === 'overridden' && level === null) return false;
    if (filter !== 'all' && filter !== 'overridden' && level !== filter) return false;
    if (needle.length > 0) {
      const haystack = `${attr.code} ${Object.values(attr.label).join(' ')}`.toLowerCase();
      if (!haystack.includes(needle)) return false;
    }
    return true;
  });
}

function levelStyle(level: 'view' | 'edit' | 'restricted', active: boolean): string {
  if (!active) return '';
  switch (level) {
    case 'view':
      return 'border-blue-300 bg-blue-50 text-blue-800';
    case 'edit':
      return 'border-emerald-300 bg-emerald-50 text-emerald-800';
    case 'restricted':
      return 'border-rose-300 bg-rose-50 text-rose-800';
  }
}
