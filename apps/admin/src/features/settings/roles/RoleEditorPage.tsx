import { ArrowLeft, ShieldCheck, ShieldPlus, Trash2 } from 'lucide-react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';

import { type PermissionGroup, PermissionMatrix } from './PermissionMatrix';
import type { RoleDetail } from './types';

interface PermissionsResponse {
  member: PermissionGroup[];
  totalItems: number;
}

interface ApiProblem {
  detail?: string;
  code?: string;
  user_count?: number;
}

/**
 * RBAC-P5-006 (#696) — create/edit page for custom roles.
 *
 * One component handles both `/settings/roles/new` and
 * `/settings/roles/{id}/edit` because the form surface is identical;
 * the route just decides whether we POST or PATCH on submit. System
 * roles open in edit mode with the name / code fields locked (per
 * PRD §3.2 — built-in code is the wire contract for assignment
 * lookups, can't drift).
 *
 * Scope intentionally trimmed to what the backend supports today:
 *   - name + permission_codes (custom + system)
 *   - code editable on create (auto-slugified from name)
 *
 * Deferred to follow-up tickets — flagged inline in the UI:
 *   - default_attribute_permission radio (AC-9, depends on #697 schema)
 *   - cross-tab exception badges (AC-10/11/12, depends on #697)
 *   - auto-grant new ObjectTypes toggle (AC-5, depends on #698 backend)
 *   - "Start from template" dropdown (AC-6) — operator can copy a
 *     system role by saving as custom in two steps for now
 */
export function RoleEditorPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const params = useParams<{ id?: string }>();
  const isEdit = Boolean(params.id);

  const [role, setRole] = useState<RoleDetail | null>(null);
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [codeTouched, setCodeTouched] = useState(false);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [groups, setGroups] = useState<PermissionGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [deleting, setDeleting] = useState(false);

  // Load the permission catalogue + role detail in parallel. Both feed
  // separate pieces of state — the catalogue drives the matrix grid,
  // the role drives the form initial values + the "system role" gate.
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    const tasks: Promise<void>[] = [
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
            setSelected(new Set(data.permission_codes));
          })
          .catch(() => {
            if (!cancelled) toast.error(t('settings.roles.editor.error_load_role'));
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

  // Auto-derive `code` from `name` until the operator manually edits
  // the code input. Same convention as the backend slugify() — keep
  // them in sync so the preview matches what the API would generate.
  useEffect(() => {
    if (codeTouched || isEdit) return;
    setCode(slugify(name));
  }, [name, codeTouched, isEdit]);

  const isSystem = role?.type === 'system';
  const isCustom = role?.type === 'custom';

  const toggle = (permissionCode: string) => {
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

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (submitting || name.trim().length === 0) return;
    setSubmitting(true);
    try {
      const permissionCodes = Array.from(selected);
      if (isEdit && role) {
        const body: Record<string, unknown> = { permission_codes: permissionCodes };
        if (isCustom) {
          body.name = name.trim();
        }
        await jsonFetch(`/api/roles/${role.id}`, {
          method: 'PATCH',
          body,
          accept: 'application/json',
          contentType: 'application/json',
        });
        toast.success(t('settings.roles.editor.toast_updated', { name: name.trim() }));
      } else {
        const created = await jsonFetch<RoleDetail>('/api/roles', {
          method: 'POST',
          body: {
            name: name.trim(),
            code: code.trim() || undefined,
            permission_codes: permissionCodes,
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
    <form onSubmit={handleSubmit} className="space-y-4">
      <header className="flex items-start justify-between gap-4">
        <div className="space-y-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => navigate('/settings/roles')}
            className="-ml-2 gap-1.5 text-muted-foreground"
          >
            <ArrowLeft className="size-4" aria-hidden="true" />
            {t('settings.roles.editor.back')}
          </Button>
          <h2 className="display flex items-center gap-2 text-xl font-semibold tracking-tight">
            {isEdit ? (
              <ShieldCheck className="size-5 text-accent-violet" aria-hidden="true" />
            ) : (
              <ShieldPlus className="size-5 text-accent-violet" aria-hidden="true" />
            )}
            {isEdit
              ? t('settings.roles.editor.title_edit', { name: role?.name ?? '...' })
              : t('settings.roles.editor.title_create')}
          </h2>
          <p className="max-w-2xl text-sm text-muted-foreground">
            {t('settings.roles.editor.intro')}
          </p>
        </div>
        <div className="flex items-center gap-2">
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
          <Button type="submit" size="sm" disabled={submitting || name.trim().length === 0}>
            {submitting
              ? t('settings.roles.editor.saving')
              : isEdit
                ? t('settings.roles.editor.save_changes')
                : t('settings.roles.editor.save_create')}
          </Button>
        </div>
      </header>

      {isSystem ? (
        <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
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

      <div className="rounded-md border border-dashed bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
        {t('settings.roles.editor.deferred_notice')}
      </div>

      <div className="space-y-2">
        <div className="flex items-baseline justify-between">
          <Label>{t('settings.roles.editor.matrix_title')}</Label>
          <span className="text-xs text-muted-foreground">
            {t('settings.roles.editor.matrix_count', {
              selected: counts.selected,
              total: counts.total,
            })}
          </span>
        </div>
        {loading ? (
          <div className="h-64 animate-pulse rounded-lg border bg-muted/30" />
        ) : (
          <PermissionMatrix
            groups={groups}
            selectedCodes={selected}
            onToggle={toggle}
            disabled={submitting || deleting}
          />
        )}
      </div>
    </form>
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
