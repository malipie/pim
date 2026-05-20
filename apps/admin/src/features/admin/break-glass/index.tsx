import { AlertTriangle, CheckCircle2, KeyRound, Loader2, ShieldAlert } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface UsageResponse {
  used: number;
  limit: number;
  remaining: number;
  window_hours: number;
  recent_invocations: Array<{
    audit_id: string;
    created_at: string;
    target_user: string | null;
    target_tenant: string | null;
    outcome: string;
  }>;
}

interface InvokeSuccess {
  audit_id: string;
  tenant: { id: string; code: string };
  user: { id: string; email: string };
  role_assigned: string;
}

interface ApiProblem {
  detail?: string;
  code?: string;
  title?: string;
}

/**
 * RBAC-P5-022 (#712) — Super Admin break-glass recovery UI.
 *
 * HTTP twin of `cortex:rescue-admin` CLI (#677). Restricted to
 * `super_admin` role; backend additionally enforces:
 *   - MFA enrolled on the caller's account (428 if not)
 *   - Valid TOTP / backup code (counts against 24h budget either way)
 *   - 5 invocations / 24h / Super Admin (failed attempts count too)
 *   - 10-char minimum reason (audit-grade documentation)
 *
 * Privacy boundary: form submits target tenant code + user email +
 * reason + MFA code. Backend resolves cross-tenant via
 * SuperAdminContext, assigns `tenant_owner` role, stamps the audit
 * row with `cross_tenant_access=true` + `special_flags=['SUPER_ADMIN_RECOVERY']`.
 */
export function AdminBreakGlassPage() {
  const { t } = useTranslation();
  const [usage, setUsage] = useState<UsageResponse | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [loadingUsage, setLoadingUsage] = useState(true);

  const reloadUsage = () => {
    setLoadingUsage(true);
    jsonFetch<UsageResponse>('/api/admin/break-glass/usage', { method: 'GET' })
      .then((data) => setUsage(data))
      .catch((err: unknown) => {
        if ((err as { status?: number })?.status === 403) {
          setForbidden(true);
        } else {
          toast.error(t('admin.break_glass.error_usage'));
        }
      })
      .finally(() => setLoadingUsage(false));
  };

  useEffect(reloadUsage, [t]);

  if (forbidden) {
    return (
      <div className="mx-auto max-w-md rounded-lg border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-800">
        <ShieldAlert className="mx-auto mb-2 size-8" aria-hidden="true" />
        <h2 className="display mb-1 text-lg font-semibold">
          {t('admin.break_glass.forbidden_title')}
        </h2>
        <p>{t('admin.break_glass.forbidden_description')}</p>
      </div>
    );
  }

  const remaining = usage?.remaining ?? 0;
  const exhausted = usage !== null && remaining <= 0;

  return (
    <div className="space-y-4">
      <header className="space-y-1">
        <div className="flex items-baseline gap-2">
          <KeyRound className="size-5 text-rose-700" aria-hidden="true" />
          <h2 className="display text-xl font-semibold tracking-tight">
            {t('admin.break_glass.title')}
          </h2>
        </div>
        <p className="max-w-2xl text-sm text-muted-foreground">{t('admin.break_glass.intro')}</p>
      </header>

      <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
        <div className="flex items-start gap-2">
          <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <span>{t('admin.break_glass.danger_notice')}</span>
        </div>
      </div>

      <div className="grid gap-3 md:grid-cols-3">
        <RateLimitCard
          label={t('admin.break_glass.usage.used')}
          value={usage?.used ?? '—'}
          tone="muted"
        />
        <RateLimitCard
          label={t('admin.break_glass.usage.remaining')}
          value={remaining}
          tone={exhausted ? 'danger' : remaining <= 1 ? 'warn' : 'ok'}
        />
        <RateLimitCard
          label={t('admin.break_glass.usage.window')}
          value={t('admin.break_glass.usage.window_value', {
            hours: usage?.window_hours ?? 24,
          })}
          tone="muted"
        />
      </div>

      <BreakGlassForm exhausted={exhausted || loadingUsage} onSuccess={reloadUsage} />

      <RecentInvocations invocations={usage?.recent_invocations ?? []} loading={loadingUsage} />
    </div>
  );
}

function RateLimitCard({
  label,
  value,
  tone,
}: {
  label: string;
  value: number | string;
  tone: 'ok' | 'warn' | 'danger' | 'muted';
}) {
  const toneClasses: Record<typeof tone, string> = {
    ok: 'bg-emerald-50 ring-emerald-200 text-emerald-700',
    warn: 'bg-amber-50 ring-amber-200 text-amber-800',
    danger: 'bg-rose-50 ring-rose-200 text-rose-800',
    muted: 'bg-muted/30 ring-input text-foreground',
  };
  return (
    <div className={cn('rounded-lg border px-3 py-2 ring-1', toneClasses[tone])}>
      <div className="text-[11px] uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="text-2xl font-semibold tabular-nums">{value}</div>
    </div>
  );
}

function BreakGlassForm({ exhausted, onSuccess }: { exhausted: boolean; onSuccess: () => void }) {
  const { t } = useTranslation();
  const [tenantCode, setTenantCode] = useState('');
  const [userEmail, setUserEmail] = useState('');
  const [reason, setReason] = useState('');
  const [mfaCode, setMfaCode] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [lastSuccess, setLastSuccess] = useState<InvokeSuccess | null>(null);

  const reset = () => {
    setTenantCode('');
    setUserEmail('');
    setReason('');
    setMfaCode('');
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (submitting) return;
    if (reason.trim().length < 10) {
      toast.error(t('admin.break_glass.error_reason_short'));
      return;
    }
    if (
      !window.confirm(
        t('admin.break_glass.confirm', {
          email: userEmail.trim(),
          tenant: tenantCode.trim(),
        }),
      )
    ) {
      return;
    }
    setSubmitting(true);
    try {
      const result = await jsonFetch<InvokeSuccess>('/api/admin/break-glass', {
        method: 'POST',
        body: {
          tenant_code: tenantCode.trim(),
          user_email: userEmail.trim(),
          reason: reason.trim(),
          mfa_totp: mfaCode.trim(),
        },
        accept: 'application/json',
        contentType: 'application/json',
      });
      setLastSuccess(result);
      toast.success(
        t('admin.break_glass.toast_success', {
          email: result.user.email,
          tenant: result.tenant.code,
        }),
      );
      reset();
      onSuccess();
    } catch (error: unknown) {
      const status = (error as { status?: number })?.status;
      const body = (error as { body?: ApiProblem })?.body;
      if (status === 428 && body?.code === 'mfa_required') {
        toast.error(t('admin.break_glass.error_mfa_required'));
      } else if (status === 422 && body?.code === 'mfa_invalid') {
        toast.error(t('admin.break_glass.error_mfa_invalid'));
        onSuccess();
      } else if (status === 429) {
        toast.error(t('admin.break_glass.error_rate_limit'));
        onSuccess();
      } else if (status === 404 && body?.code === 'tenant_not_found') {
        toast.error(t('admin.break_glass.error_tenant_not_found'));
        onSuccess();
      } else if (status === 404 && body?.code === 'user_not_found') {
        toast.error(t('admin.break_glass.error_user_not_found'));
        onSuccess();
      } else if (status === 409 && body?.code === 'user_tenant_mismatch') {
        toast.error(t('admin.break_glass.error_user_tenant_mismatch'));
        onSuccess();
      } else if (status === 403) {
        toast.error(t('admin.break_glass.forbidden_description'));
      } else {
        toast.error(body?.detail ?? t('admin.break_glass.error_generic'));
        onSuccess();
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-3 rounded-lg border bg-background p-4 shadow-sm"
    >
      {lastSuccess ? (
        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
          <div className="flex items-start gap-2">
            <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
              {t('admin.break_glass.last_success', {
                email: lastSuccess.user.email,
                tenant: lastSuccess.tenant.code,
                audit_id: lastSuccess.audit_id.slice(0, 8),
              })}
            </span>
          </div>
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="bg-tenant">{t('admin.break_glass.field_tenant')}</Label>
          <Input
            id="bg-tenant"
            value={tenantCode}
            onChange={(e) => setTenantCode(e.target.value)}
            required
            placeholder="demo"
            autoComplete="off"
            className="font-mono"
            disabled={exhausted || submitting}
          />
          <p className="text-[11px] text-muted-foreground">
            {t('admin.break_glass.field_tenant_hint')}
          </p>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="bg-email">{t('admin.break_glass.field_email')}</Label>
          <Input
            id="bg-email"
            type="email"
            value={userEmail}
            onChange={(e) => setUserEmail(e.target.value)}
            required
            placeholder="user@example.com"
            autoComplete="off"
            disabled={exhausted || submitting}
          />
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="bg-reason">{t('admin.break_glass.field_reason')}</Label>
        <textarea
          id="bg-reason"
          required
          minLength={10}
          maxLength={500}
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={3}
          className="w-full rounded-md border border-input bg-background p-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          disabled={exhausted || submitting}
          placeholder={t('admin.break_glass.field_reason_placeholder')}
        />
        <p className="text-[11px] text-muted-foreground">
          {t('admin.break_glass.field_reason_hint', { min: 10, max: 500 })}
        </p>
      </div>

      <div className="space-y-1.5 max-w-xs">
        <Label htmlFor="bg-mfa">{t('admin.break_glass.field_mfa')}</Label>
        <Input
          id="bg-mfa"
          value={mfaCode}
          onChange={(e) => setMfaCode(e.target.value)}
          required
          maxLength={10}
          autoComplete="off"
          inputMode="numeric"
          className="font-mono"
          disabled={exhausted || submitting}
          placeholder="123456"
        />
        <p className="text-[11px] text-muted-foreground">{t('admin.break_glass.field_mfa_hint')}</p>
      </div>

      <div className="flex justify-end">
        <Button
          type="submit"
          disabled={
            exhausted ||
            submitting ||
            tenantCode.trim().length === 0 ||
            userEmail.trim().length === 0 ||
            reason.trim().length < 10 ||
            mfaCode.trim().length === 0
          }
          className="gap-1.5 bg-rose-600 hover:bg-rose-700"
        >
          {submitting ? (
            <Loader2 className="size-4 animate-spin" aria-hidden="true" />
          ) : (
            <KeyRound className="size-4" aria-hidden="true" />
          )}
          {submitting
            ? t('admin.break_glass.submitting')
            : exhausted
              ? t('admin.break_glass.exhausted_button')
              : t('admin.break_glass.submit')}
        </Button>
      </div>
    </form>
  );
}

function RecentInvocations({
  invocations,
  loading,
}: {
  invocations: UsageResponse['recent_invocations'];
  loading: boolean;
}) {
  const { t, i18n } = useTranslation();
  if (loading) {
    return (
      <div className="flex items-center gap-2 text-xs text-muted-foreground">
        <Loader2 className="size-3 animate-spin" aria-hidden="true" />
        {t('admin.break_glass.recent_loading')}
      </div>
    );
  }
  if (invocations.length === 0) {
    return (
      <div className="rounded-md border bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
        {t('admin.break_glass.recent_empty')}
      </div>
    );
  }
  return (
    <div className="space-y-2">
      <h3 className="text-sm font-semibold">{t('admin.break_glass.recent_title')}</h3>
      <div className="overflow-hidden rounded-lg border bg-background shadow-sm">
        <table className="w-full text-xs">
          <thead className="bg-muted/40 text-[11px] uppercase tracking-wide text-muted-foreground">
            <tr>
              <th className="px-3 py-2 text-left">{t('admin.break_glass.recent_col_when')}</th>
              <th className="px-3 py-2 text-left">{t('admin.break_glass.recent_col_target')}</th>
              <th className="px-3 py-2 text-left">{t('admin.break_glass.recent_col_outcome')}</th>
              <th className="px-3 py-2 text-left">{t('admin.break_glass.recent_col_audit')}</th>
            </tr>
          </thead>
          <tbody>
            {invocations.map((row) => (
              <tr key={row.audit_id} className="border-t">
                <td className="px-3 py-2 text-muted-foreground">
                  {new Date(`${row.created_at.replace(' ', 'T')}Z`).toLocaleString(i18n.language)}
                </td>
                <td className="px-3 py-2">
                  <div className="space-y-0.5">
                    <div className="font-medium">{row.target_user ?? '—'}</div>
                    <div className="font-mono text-[10px] text-muted-foreground">
                      {row.target_tenant ?? '—'}
                    </div>
                  </div>
                </td>
                <td className="px-3 py-2">
                  <OutcomeBadge outcome={row.outcome} />
                </td>
                <td className="px-3 py-2 font-mono text-[10px] text-muted-foreground">
                  {row.audit_id.slice(0, 8)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function OutcomeBadge({ outcome }: { outcome: string }) {
  const { t } = useTranslation();
  const success = outcome === 'super_admin_bypass';
  return (
    <span
      className={cn(
        'inline-flex rounded px-1.5 py-0.5 text-[10px] font-medium ring-1',
        success
          ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
          : 'bg-rose-50 text-rose-700 ring-rose-200',
      )}
    >
      {success ? t('admin.break_glass.outcome_success') : t('admin.break_glass.outcome_denied')}
    </span>
  );
}
