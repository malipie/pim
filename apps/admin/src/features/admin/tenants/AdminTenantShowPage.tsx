import { ArrowLeft, Building2, Calendar, Globe, Loader2, ShieldAlert, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

import type { AdminTenantSummary } from './types';

/**
 * RBAC-P5-020 (#710) — Super Admin tenant detail.
 *
 * Read-only view of the metadata + counters for a single tenant. Uses
 * the same wire shape as the list endpoint (the backend re-uses
 * {@link SuperAdminTenantResponseBuilder::buildOne()}), so adding a
 * field to the API automatically shows up here.
 *
 * Privacy boundary same as the list — never displays products,
 * attributes, or values. Tenant CRUD writes (suspend / change plan)
 * land in #711 with their own break-glass + audit hooks.
 */
export function AdminTenantShowPage() {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const params = useParams<{ id: string }>();
  const [tenant, setTenant] = useState<AdminTenantSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [forbidden, setForbidden] = useState(false);
  const [notFound, setNotFound] = useState(false);
  const [error, setError] = useState(false);

  useEffect(() => {
    if (!params.id) return;
    let cancelled = false;
    setLoading(true);
    setForbidden(false);
    setNotFound(false);
    setError(false);
    jsonFetch<AdminTenantSummary>(`/api/admin/tenants/${params.id}`, { method: 'GET' })
      .then((data) => {
        if (!cancelled) setTenant(data);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        const status = (err as { status?: number })?.status;
        if (status === 403) setForbidden(true);
        else if (status === 404) setNotFound(true);
        else setError(true);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [params.id]);

  if (forbidden) {
    return (
      <div className="mx-auto max-w-md rounded-lg border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-800">
        <ShieldAlert className="mx-auto mb-2 size-8" aria-hidden="true" />
        <h2 className="display mb-1 text-lg font-semibold">{t('admin.tenants.forbidden_title')}</h2>
        <p>{t('admin.tenants.forbidden_description')}</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <header className="space-y-2">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => navigate('/admin/tenants')}
          className="-ml-2 gap-1.5 text-muted-foreground"
        >
          <ArrowLeft className="size-4" aria-hidden="true" />
          {t('admin.tenants.detail.back')}
        </Button>
        <div className="flex items-baseline gap-2">
          <Building2 className="size-5 text-accent-violet" aria-hidden="true" />
          <h2 className="display text-xl font-semibold tracking-tight">
            {tenant?.name ?? t('admin.tenants.detail.loading')}
          </h2>
        </div>
        {tenant ? (
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <span className="font-mono">{tenant.code}</span>
            {tenant.domain ? (
              <>
                <span aria-hidden="true">·</span>
                <span>{tenant.domain}</span>
              </>
            ) : null}
          </div>
        ) : null}
      </header>

      <div className="rounded-md border border-dashed bg-amber-50 px-3 py-2 text-xs text-amber-900">
        {t('admin.tenants.privacy_boundary_notice')}
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-12 text-muted-foreground">
          <Loader2 className="size-5 animate-spin" aria-hidden="true" />
        </div>
      ) : notFound ? (
        <div className="rounded-md border bg-background px-3 py-8 text-center text-sm text-muted-foreground">
          {t('admin.tenants.detail.not_found')}
        </div>
      ) : error ? (
        <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
          {t('admin.tenants.error_loading')}
        </div>
      ) : tenant ? (
        <div className="grid gap-3 md:grid-cols-3">
          <Card
            icon={Building2}
            label={t('admin.tenants.detail.field_plan')}
            value={<PlanBadge plan={tenant.plan} />}
          />
          <Card
            icon={Users}
            label={t('admin.tenants.detail.field_users')}
            value={
              <span className="text-2xl font-semibold tabular-nums">{tenant.active_users}</span>
            }
            hint={t('admin.tenants.detail.field_users_hint')}
          />
          <Card
            icon={Calendar}
            label={t('admin.tenants.detail.field_created')}
            value={
              <span className="text-sm">
                {new Date(tenant.created_at).toLocaleString(i18n.language)}
              </span>
            }
          />
          <Card
            icon={Globe}
            label={t('admin.tenants.detail.field_locales')}
            value={
              <div className="flex flex-wrap gap-1">
                {tenant.enabled_locales.map((locale) => (
                  <span
                    key={locale}
                    className={cn(
                      'rounded px-1.5 py-0.5 text-[11px] font-mono ring-1',
                      locale === tenant.primary_locale
                        ? 'bg-accent-violet/10 text-accent-violet ring-accent-violet/30'
                        : 'bg-muted text-muted-foreground ring-input',
                    )}
                  >
                    {locale}
                  </span>
                ))}
              </div>
            }
            hint={t('admin.tenants.detail.field_locales_hint', { primary: tenant.primary_locale })}
          />
          <div className="md:col-span-2 rounded-lg border border-dashed bg-muted/30 p-3 text-xs text-muted-foreground">
            {t('admin.tenants.detail.crud_deferred')}
          </div>
        </div>
      ) : null}
    </div>
  );
}

function Card({
  icon: Icon,
  label,
  value,
  hint,
}: {
  icon: React.ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
  label: string;
  value: React.ReactNode;
  hint?: string;
}) {
  return (
    <div className="space-y-2 rounded-lg border bg-background p-3 shadow-sm">
      <div className="flex items-center gap-1.5 text-xs uppercase tracking-wide text-muted-foreground">
        <Icon className="size-3.5" aria-hidden={true} />
        <span>{label}</span>
      </div>
      <div>{value}</div>
      {hint ? <p className="text-[11px] text-muted-foreground">{hint}</p> : null}
    </div>
  );
}

function PlanBadge({ plan }: { plan: string }) {
  const classes: Record<string, string> = {
    starter: 'bg-blue-50 text-blue-700 ring-blue-200',
    growth: 'bg-violet-50 text-violet-700 ring-violet-200',
    scale: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    enterprise: 'bg-amber-50 text-amber-700 ring-amber-200',
  };
  return (
    <span
      className={cn(
        'inline-flex rounded px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide ring-1',
        classes[plan] ?? 'bg-muted text-muted-foreground ring-input',
      )}
    >
      {plan}
    </span>
  );
}
