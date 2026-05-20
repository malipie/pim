import { ChevronDown, ChevronRight, Save, Search, ShieldCheck } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

export type AttributePermissionLevel = 'view' | 'edit' | 'restricted' | null;

interface ApiAttribute {
  id: string;
  code: string;
  label: Record<string, string>;
  type: string;
  is_localizable: boolean;
  is_required: boolean;
  permission_level: AttributePermissionLevel;
}

interface ApiGroup {
  group_id: string | null;
  group_code: string | null;
  group_label: Record<string, string> | null;
  attributes: ApiAttribute[];
}

interface ApiResponse {
  role_id: string;
  groups: ApiGroup[];
}

type FilterMode = 'all' | 'view' | 'edit' | 'restricted' | 'overridden';

interface AttributePermissionsTabProps {
  roleId: string | null;
  /** Disable inputs while the parent form is mid-submit. */
  disabled?: boolean;
}

/**
 * RBAC-P5-007 (#697) — "Uprawnienia per atrybut" tab inside the role
 * editor.
 *
 * The tab is independent of the matrix grid (#696) — the resolver
 * consults it as a per-attribute override that takes precedence over
 * the module-level grant. `permission_level: null` means "no override,
 * fall back to the matrix"; explicit `restricted` denies even when the
 * matrix would grant.
 *
 * Save model: own button (separate from the role-editor save) because
 * loading + writing the overrides goes through a dedicated endpoint
 * (`PUT /api/roles/{id}/attribute-permissions`) and a bulk replace
 * inside the role-editor submit would silently widen the audit trail.
 *
 * Scope intentionally trimmed for this MVP:
 *   - Per-attribute 3-state segmented control (the spec's hero UI).
 *   - Per-group bulk-apply button (sets every visible attribute in the
 *     group to the chosen level).
 *   - Search box (case-insensitive substring on code + every locale
 *     label).
 *   - Filter chips for current state.
 *
 * Deferred (flagged inline):
 *   - Cross-tab badges back into the matrix tab (#696) — needs Mercure
 *     SSE or a shared store; tracked as follow-up.
 *   - "Preview changes" modal for bulk apply > 5 attributes.
 *   - Virtualized list for 200+ attributes — current implementation
 *     paints all rows; perf measured fine at 32 attrs in the demo
 *     tenant, revisit when a real fixture pushes past ~150.
 */
export function AttributePermissionsTab({
  roleId,
  disabled = false,
}: AttributePermissionsTabProps) {
  const { t, i18n } = useTranslation();
  const locale = i18n.language;

  const [groups, setGroups] = useState<ApiGroup[]>([]);
  const [original, setOriginal] = useState<Record<string, AttributePermissionLevel>>({});
  const [draft, setDraft] = useState<Record<string, AttributePermissionLevel>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [filter, setFilter] = useState<FilterMode>('all');
  const [search, setSearch] = useState('');
  const [expanded, setExpanded] = useState<Set<string>>(new Set());

  useEffect(() => {
    if (!roleId) return;
    let cancelled = false;
    setLoading(true);
    jsonFetch<ApiResponse>(`/api/roles/${roleId}/attribute-permissions`, { method: 'GET' })
      .then((data) => {
        if (cancelled) return;
        setGroups(data.groups);
        const seeded: Record<string, AttributePermissionLevel> = {};
        for (const group of data.groups) {
          for (const attr of group.attributes) {
            seeded[attr.id] = attr.permission_level;
          }
        }
        setOriginal(seeded);
        setDraft(seeded);
        // Auto-expand groups that have ANY override so the operator
        // sees what's already configured without clicking through.
        const expandKeys = new Set<string>();
        for (const group of data.groups) {
          if (group.attributes.some((a) => a.permission_level !== null)) {
            expandKeys.add(group.group_id ?? '__ungrouped__');
          }
        }
        setExpanded(expandKeys);
      })
      .catch(() => {
        if (!cancelled) toast.error(t('settings.roles.attr_perms.error_load'));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [roleId, t]);

  const dirty = useMemo(() => {
    for (const id in draft) {
      if (draft[id] !== (original[id] ?? null)) return true;
    }
    for (const id in original) {
      if (!(id in draft)) return true;
    }
    return false;
  }, [draft, original]);

  const setLevel = (attrId: string, level: AttributePermissionLevel) => {
    setDraft((prev) => ({ ...prev, [attrId]: level }));
  };

  const applyToGroup = (group: ApiGroup, level: AttributePermissionLevel) => {
    setDraft((prev) => {
      const next = { ...prev };
      for (const attr of visibleAttributes(group, search, filter, draft)) {
        next[attr.id] = level;
      }
      return next;
    });
  };

  const handleReset = () => {
    setDraft(original);
  };

  const handleSave = async () => {
    if (!roleId || saving) return;
    setSaving(true);
    try {
      const payload = {
        attribute_permissions: Object.entries(draft)
          .filter(([, level]) => level !== null)
          .map(([attribute_id, permission_level]) => ({ attribute_id, permission_level })),
      };
      const result = await jsonFetch<ApiResponse>(`/api/roles/${roleId}/attribute-permissions`, {
        method: 'PUT',
        body: payload,
        accept: 'application/json',
        contentType: 'application/json',
      });
      setGroups(result.groups);
      const fresh: Record<string, AttributePermissionLevel> = {};
      for (const group of result.groups) {
        for (const attr of group.attributes) {
          fresh[attr.id] = attr.permission_level;
        }
      }
      setOriginal(fresh);
      setDraft(fresh);
      toast.success(t('settings.roles.attr_perms.toast_saved'));
    } catch (error: unknown) {
      const status = (error as { status?: number; body?: { detail?: string } })?.status;
      const body = (error as { body?: { detail?: string } })?.body;
      if (status === 400) {
        toast.error(body?.detail ?? t('settings.roles.attr_perms.error_validation'));
      } else if (status === 403) {
        toast.error(t('settings.roles.attr_perms.error_forbidden'));
      } else {
        toast.error(t('settings.roles.attr_perms.error_generic'));
      }
    } finally {
      setSaving(false);
    }
  };

  const overrideCount = useMemo(
    () => Object.values(draft).filter((l) => l !== null).length,
    [draft],
  );

  if (!roleId) {
    return (
      <div className="rounded-md border border-dashed bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
        {t('settings.roles.attr_perms.create_first')}
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[240px]">
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

      <div className="rounded-md border border-dashed bg-muted/30 px-3 py-2 text-[11px] text-muted-foreground">
        {t('settings.roles.attr_perms.cross_tab_deferred')}
      </div>

      {loading ? (
        <div className="h-64 animate-pulse rounded-lg border bg-muted/30" />
      ) : (
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
                disabled={disabled || saving}
              />
            );
          })}
        </div>
      )}

      <div className="flex items-center justify-end gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleReset}
          disabled={!dirty || disabled || saving}
        >
          {t('settings.roles.attr_perms.reset')}
        </Button>
        <Button
          type="button"
          size="sm"
          onClick={handleSave}
          disabled={!dirty || disabled || saving}
          className="gap-1.5"
        >
          <Save className="size-4" aria-hidden="true" />
          {saving ? t('settings.roles.attr_perms.saving') : t('settings.roles.attr_perms.save')}
        </Button>
      </div>
    </div>
  );
}

interface GroupPanelProps {
  group: ApiGroup;
  visible: ApiAttribute[];
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

interface AttributeRowProps {
  attr: ApiAttribute;
  level: AttributePermissionLevel;
  onChange: (level: AttributePermissionLevel) => void;
  locale: string;
  disabled: boolean;
}

function AttributeRow({ attr, level, onChange, locale, disabled }: AttributeRowProps) {
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
  group: ApiGroup,
  search: string,
  filter: FilterMode,
  draft: Record<string, AttributePermissionLevel>,
): ApiAttribute[] {
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
