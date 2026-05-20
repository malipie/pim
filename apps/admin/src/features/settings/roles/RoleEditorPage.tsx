import { ArrowLeft, ShieldCheck, ShieldPlus, Trash2 } from 'lucide-react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

import {
  type AttributePermissionLevel,
  AttributePermissionsSection,
  type AttributePermsApiGroup,
} from './AttributePermissionsSection';
import { type PermissionGroup, PermissionMatrix } from './PermissionMatrix';
import type { RoleDetail } from './types';

interface PermissionsResponse {
  member: PermissionGroup[];
  totalItems: number;
}

interface AttrPermsResponse {
  role_id: string;
  groups: AttributePermsApiGroup[];
}

interface ApiProblem {
  detail?: string;
  code?: string;
  user_count?: number;
}

/**
 * Role editor polish (marathon-3 / #847) — pixel-perfect to PRD-PIM-rbac §5.3.
 *
 * Layout is one form with four card sections stacked vertically, plus
 * a sticky bottom action bar (Cancel + primary). NOT tabs — the PRD
 * mockup shows sections grouped by visual cards on one scrollable page.
 *
 * Sections:
 *   1. Identity (name + code + description + system notice)
 *   2. Advanced (auto-grant + deferred scope notice)
 *   3. Permissions matrix (module × action grid from #696)
 *   4. Field-level restrictions (per-attribute overrides from #697)
 *   5. Locale & Channel Scope (deferred placeholder pointing at #693
 *      per-assignment scope follow-up)
 *
 * Single submit pipeline:
 *   1. PATCH /api/roles/{id} — name + description + permission_codes
 *      + auto_grant_new_object_types
 *   2. PUT /api/roles/{id}/attribute-permissions — replacement set
 *   Both fire in sequence; failure on either rolls the toast back to
 *   error and leaves draft state intact so the operator can retry
 *   without re-filling the form.
 *
 * Dirty tracking: matrix toggle, attribute level, description edit,
 * auto-grant toggle, name rename, code edit all feed `isDirty` which
 * gates the Save button. Reset reverts every section to the loaded
 * server snapshot.
 */
export function RoleEditorPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const params = useParams<{ id?: string }>();
  const isEdit = Boolean(params.id);

  const [role, setRole] = useState<RoleDetail | null>(null);

  // Identity section state
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [codeTouched, setCodeTouched] = useState(false);
  const [description, setDescription] = useState('');

  // Advanced section state
  const [autoGrant, setAutoGrant] = useState(false);

  // Matrix state
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [groups, setGroups] = useState<PermissionGroup[]>([]);

  // Attribute permissions state
  const [attrGroups, setAttrGroups] = useState<AttributePermsApiGroup[]>([]);
  const [attrDraft, setAttrDraft] = useState<Record<string, AttributePermissionLevel>>({});

  // Original snapshots for dirty tracking
  const [originalIdentity, setOriginalIdentity] = useState({ name: '', description: '' });
  const [originalAutoGrant, setOriginalAutoGrant] = useState(false);
  const [originalPermissions, setOriginalPermissions] = useState<Set<string>>(new Set());
  const [originalAttrDraft, setOriginalAttrDraft] = useState<
    Record<string, AttributePermissionLevel>
  >({});

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [deleting, setDeleting] = useState(false);

  // Load catalogue + role detail + attribute permissions in parallel.
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    const tasks: Array<Promise<unknown>> = [
      jsonFetch<PermissionsResponse>('/api/permissions', { method: 'GET' })
        .then((data) => {
          if (!cancelled) setGroups(data.member);
        })
        .catch(() => {
          if (!cancelled) toast.error(t('settings.roles.editor.error_load_permissions'));
        }),
    ];

    if (isEdit && params.id) {
      tasks.push(
        jsonFetch<RoleDetail>(`/api/roles/${params.id}`, { method: 'GET' })
          .then((data) => {
            if (cancelled) return;
            setRole(data);
            setName(data.name);
            setCode(data.code);
            setCodeTouched(true);
            setDescription(data.description ?? '');
            setSelected(new Set(data.permission_codes));
            setAutoGrant(data.auto_grant_new_object_types);
            setOriginalIdentity({ name: data.name, description: data.description ?? '' });
            setOriginalAutoGrant(data.auto_grant_new_object_types);
            setOriginalPermissions(new Set(data.permission_codes));
          })
          .catch(() => {
            if (!cancelled) toast.error(t('settings.roles.editor.error_load_role'));
          }),
        jsonFetch<AttrPermsResponse>(`/api/roles/${params.id}/attribute-permissions`, {
          method: 'GET',
        })
          .then((data) => {
            if (cancelled) return;
            setAttrGroups(data.groups);
            const seed: Record<string, AttributePermissionLevel> = {};
            for (const group of data.groups) {
              for (const attr of group.attributes) {
                seed[attr.id] = attr.permission_level;
              }
            }
            setAttrDraft(seed);
            setOriginalAttrDraft(seed);
          })
          .catch(() => {
            if (!cancelled) toast.error(t('settings.roles.attr_perms.error_load'));
          }),
      );
    }

    Promise.all(tasks).finally(() => {
      if (!cancelled) setLoading(false);
    });
    return () => {
      cancelled = true;
    };
  }, [isEdit, params.id, t]);

  // Auto-derive code from name on create only.
  useEffect(() => {
    if (codeTouched || isEdit) return;
    setCode(slugify(name));
  }, [name, codeTouched, isEdit]);

  const isSystem = role?.type === 'system';
  const isCustom = role?.type === 'custom';

  const togglePermission = (permissionCode: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(permissionCode)) {
        next.delete(permissionCode);
      } else {
        next.add(permissionCode);
      }
      return next;
    });
  };

  const counts = useMemo(() => {
    let totalCells = 0;
    for (const group of groups) totalCells += group.permissions.length;
    return { selected: selected.size, total: totalCells };
  }, [groups, selected]);

  const attrOverrideCount = useMemo(
    () => Object.values(attrDraft).filter((l) => l !== null).length,
    [attrDraft],
  );

  // Dirty tracking — Save disabled unless something actually changed.
  const isDirty = useMemo(() => {
    if (!isEdit) return name.trim().length > 0;
    if (name !== originalIdentity.name) return true;
    if ((description ?? '') !== originalIdentity.description) return true;
    if (autoGrant !== originalAutoGrant) return true;
    if (selected.size !== originalPermissions.size) return true;
    for (const code of selected) {
      if (!originalPermissions.has(code)) return true;
    }
    const draftKeys = Object.keys(attrDraft);
    const origKeys = Object.keys(originalAttrDraft);
    if (draftKeys.length !== origKeys.length) return true;
    for (const id of draftKeys) {
      if (attrDraft[id] !== (originalAttrDraft[id] ?? null)) return true;
    }
    return false;
  }, [
    isEdit,
    name,
    description,
    autoGrant,
    selected,
    attrDraft,
    originalIdentity,
    originalAutoGrant,
    originalPermissions,
    originalAttrDraft,
  ]);

  const handleReset = () => {
    if (!role) return;
    setName(role.name);
    setDescription(role.description ?? '');
    setSelected(new Set(role.permission_codes));
    setAutoGrant(role.auto_grant_new_object_types);
    setAttrDraft(originalAttrDraft);
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (submitting || name.trim().length === 0) return;
    setSubmitting(true);
    try {
      const permissionCodes = Array.from(selected);

      if (isEdit && role) {
        // 1. PATCH role identity + permissions
        const body: Record<string, unknown> = {
          permission_codes: permissionCodes,
          auto_grant_new_object_types: autoGrant,
          description: description.trim() || null,
        };
        if (isCustom) {
          body.name = name.trim();
        }
        await jsonFetch(`/api/roles/${role.id}`, {
          method: 'PATCH',
          body,
          accept: 'application/json',
          contentType: 'application/json',
        });

        // 2. PUT attribute permissions
        const attrPayload = Object.entries(attrDraft)
          .filter(([, level]) => level !== null)
          .map(([attribute_id, permission_level]) => ({ attribute_id, permission_level }));
        await jsonFetch(`/api/roles/${role.id}/attribute-permissions`, {
          method: 'PUT',
          body: { attribute_permissions: attrPayload },
          accept: 'application/json',
          contentType: 'application/json',
        });

        // Refresh originals
        setOriginalIdentity({ name: name.trim(), description: description.trim() });
        setOriginalAutoGrant(autoGrant);
        setOriginalPermissions(new Set(permissionCodes));
        setOriginalAttrDraft({ ...attrDraft });
        toast.success(t('settings.roles.editor.toast_updated', { name: name.trim() }));
      } else {
        const created = await jsonFetch<RoleDetail>('/api/roles', {
          method: 'POST',
          body: {
            name: name.trim(),
            code: code.trim() || undefined,
            description: description.trim() || null,
            permission_codes: permissionCodes,
            auto_grant_new_object_types: autoGrant,
          },
          accept: 'application/json',
          contentType: 'application/json',
        });
        toast.success(t('settings.roles.editor.toast_created', { name: created.name }));
        navigate(`/settings/roles/${created.id}/edit`, { replace: true });
        return;
      }
    } catch (error: unknown) {
      const status = (error as { status?: number; body?: ApiProblem })?.status;
      const body = (error as { body?: ApiProblem })?.body;
      if (status === 409 && body?.code === 'duplicate_code') {
        toast.error(body?.detail ?? t('settings.roles.editor.error_duplicate'));
      } else if (status === 400) {
        toast.error(body?.detail ?? t('settings.roles.editor.error_validation'));
      } else if (status === 403) {
        toast.error(t('settings.roles.editor.error_forbidden'));
      } else {
        toast.error(t('settings.roles.editor.error_generic'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async () => {
    if (!role || !isCustom || deleting) return;
    if (!window.confirm(t('settings.roles.editor.confirm_delete', { name: role.name }))) return;
    setDeleting(true);
    try {
      await jsonFetch(`/api/roles/${role.id}`, { method: 'DELETE', accept: 'application/json' });
      toast.success(t('settings.roles.editor.toast_deleted', { name: role.name }));
      navigate('/settings/roles', { replace: true });
    } catch (error: unknown) {
      const status = (error as { status?: number; body?: ApiProblem })?.status;
      const body = (error as { body?: ApiProblem })?.body;
      if (status === 409 && body?.code === 'role_in_use') {
        toast.error(t('settings.roles.editor.error_in_use', { count: body?.user_count ?? 0 }));
      } else if (status === 403) {
        toast.error(t('settings.roles.editor.error_delete_forbidden'));
      } else {
        toast.error(t('settings.roles.editor.error_generic'));
      }
    } finally {
      setDeleting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-5 pb-24">
      <header>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => navigate('/settings/roles')}
          className="-ml-2 mb-1 gap-1.5 text-muted-foreground"
        >
          <ArrowLeft className="size-4" aria-hidden="true" />
          {t('settings.roles.editor.back')}
        </Button>
        <h2 className="display flex items-center gap-2 text-2xl font-semibold tracking-tight">
          {isEdit ? (
            <ShieldCheck className="size-6 text-accent-violet" aria-hidden="true" />
          ) : (
            <ShieldPlus className="size-6 text-accent-violet" aria-hidden="true" />
          )}
          {isEdit
            ? t('settings.roles.editor.title_edit', { name: role?.name ?? '...' })
            : t('settings.roles.editor.title_create')}
        </h2>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          {t('settings.roles.editor.intro')}
        </p>
      </header>

      <SectionCard
        title={t('settings.roles.editor.section_identity')}
        description={t('settings.roles.editor.section_identity_intro')}
      >
        {isSystem ? (
          <div className="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            {t('settings.roles.editor.system_notice')}
          </div>
        ) : null}
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="role-name">{t('settings.roles.editor.field_name')}</Label>
            <Input
              id="role-name"
              required
              value={name}
              disabled={isSystem || loading}
              onChange={(e) => setName(e.target.value)}
              placeholder={t('settings.roles.editor.field_name_placeholder')}
              maxLength={80}
              autoComplete="off"
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="role-code">{t('settings.roles.editor.field_code')}</Label>
            <Input
              id="role-code"
              value={code}
              disabled={isSystem || isEdit || loading}
              onChange={(e) => {
                setCodeTouched(true);
                setCode(e.target.value);
              }}
              placeholder={t('settings.roles.editor.field_code_placeholder')}
              maxLength={64}
              autoComplete="off"
              className="font-mono"
            />
            <p className="text-[11px] text-muted-foreground">
              {t('settings.roles.editor.field_code_hint')}
            </p>
          </div>
        </div>
        <div className="mt-3 space-y-1.5">
          <Label htmlFor="role-description">{t('settings.roles.editor.field_description')}</Label>
          <textarea
            id="role-description"
            value={description}
            disabled={isSystem || loading}
            onChange={(e) => setDescription(e.target.value)}
            rows={3}
            maxLength={500}
            placeholder={t('settings.roles.editor.field_description_placeholder')}
            className="w-full rounded-md border border-input bg-background p-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          />
          <p className="text-[11px] text-muted-foreground">
            {t('settings.roles.editor.field_description_hint')}
          </p>
        </div>
      </SectionCard>

      <SectionCard
        title={t('settings.roles.editor.section_advanced')}
        description={t('settings.roles.editor.section_advanced_intro')}
      >
        <label className="flex items-start gap-3 rounded-md border bg-background px-3 py-2 text-sm">
          <input
            type="checkbox"
            className="mt-0.5 size-4"
            checked={autoGrant}
            disabled={loading || submitting}
            onChange={(e) => setAutoGrant(e.target.checked)}
          />
          <div className="flex-1 space-y-0.5">
            <div className="font-medium">{t('settings.roles.editor.auto_grant_label')}</div>
            <p className="text-[11px] text-muted-foreground">
              {t('settings.roles.editor.auto_grant_hint')}
            </p>
          </div>
        </label>
      </SectionCard>

      <SectionCard
        title={t('settings.roles.editor.matrix_title')}
        description={t('settings.roles.editor.matrix_intro')}
        meta={t('settings.roles.editor.matrix_count', {
          selected: counts.selected,
          total: counts.total,
        })}
      >
        {loading ? (
          <div className="h-64 animate-pulse rounded-md border bg-muted/30" />
        ) : (
          <PermissionMatrix
            groups={groups}
            selectedCodes={selected}
            onToggle={togglePermission}
            disabled={submitting || deleting}
          />
        )}
      </SectionCard>

      <SectionCard
        title={t('settings.roles.editor.section_field_restrictions')}
        description={t('settings.roles.editor.section_field_restrictions_intro')}
        meta={t('settings.roles.attr_perms.override_count', { count: attrOverrideCount })}
      >
        {isEdit ? (
          <AttributePermissionsSection
            groups={attrGroups}
            draft={attrDraft}
            onChange={setAttrDraft}
            loading={loading}
            disabled={submitting || deleting}
          />
        ) : (
          <div className="rounded-md border border-dashed bg-muted/30 px-3 py-4 text-center text-xs text-muted-foreground">
            {t('settings.roles.attr_perms.create_first')}
          </div>
        )}
      </SectionCard>

      <SectionCard
        title={t('settings.roles.editor.section_locale_channel_scope')}
        description={t('settings.roles.editor.section_locale_channel_scope_intro')}
      >
        <div className="rounded-md border border-dashed bg-muted/30 px-3 py-3 text-xs text-muted-foreground">
          {t('settings.roles.editor.scope_deferred_notice')}
        </div>
      </SectionCard>

      {/* Sticky bottom action bar — single save + cancel/reset per
          PRD §5.3 mockup (no top-header save button). */}
      <div className="sticky bottom-0 -mx-4 mt-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:-mx-6 sm:px-6">
        <div className="flex flex-wrap items-center gap-2">
          <span
            className={cn('text-xs', isDirty ? 'text-amber-700' : 'text-muted-foreground')}
            aria-live="polite"
          >
            {isDirty
              ? t('settings.roles.editor.dirty_indicator')
              : t('settings.roles.editor.clean_indicator')}
          </span>
          <div className="ml-auto flex flex-wrap items-center gap-2">
            {isCustom ? (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleDelete}
                disabled={submitting || deleting}
                className="gap-1.5 text-rose-700 hover:bg-rose-50"
              >
                <Trash2 className="size-4" aria-hidden="true" />
                {t('settings.roles.editor.delete')}
              </Button>
            ) : null}
            {isEdit ? (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleReset}
                disabled={!isDirty || submitting || deleting}
              >
                {t('settings.roles.editor.reset')}
              </Button>
            ) : null}
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => navigate('/settings/roles')}
              disabled={submitting}
            >
              {t('settings.roles.editor.cancel')}
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={submitting || deleting || name.trim().length === 0 || (isEdit && !isDirty)}
            >
              {submitting
                ? t('settings.roles.editor.saving')
                : isEdit
                  ? t('settings.roles.editor.save_changes')
                  : t('settings.roles.editor.save_create')}
            </Button>
          </div>
        </div>
      </div>
    </form>
  );
}

function SectionCard({
  title,
  description,
  meta,
  children,
}: {
  title: string;
  description?: string;
  meta?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="rounded-lg border bg-background shadow-sm">
      <header className="flex flex-wrap items-baseline justify-between gap-2 border-b bg-muted/30 px-4 py-3">
        <div className="space-y-0.5">
          <h3 className="text-sm font-semibold">{title}</h3>
          {description ? (
            <p className="max-w-2xl text-[11px] text-muted-foreground">{description}</p>
          ) : null}
        </div>
        {meta ? <span className="text-xs text-muted-foreground">{meta}</span> : null}
      </header>
      <div className="p-4">{children}</div>
    </section>
  );
}

function slugify(input: string): string {
  return input
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}
