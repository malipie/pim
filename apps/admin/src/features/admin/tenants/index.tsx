import { Building2, Loader2, ShieldAlert, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Input } from '@/components/ui/input';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

import { AdminTenantShowPage } from './AdminTenantShowPage';
import type { AdminTenantSummary } from './types';

export { AdminTenantShowPage };

interface ListResponse {
  member: AdminTenantSummary[];
  totalItems: number;
}

type PlanFilter = 'all' | string;

/**
 * RBAC-P5-019 (#709) — Super Admin operator panel: tenant list.
 *
 * Lives at `/admin/tenants` inside the existing admin app. The
 * long-term home per the ticket spec is the dedicated
 * `admin.cortex.pl` subdomain; that's an operator infra task
 * (Caddyfile + cookie domain config) and not a blocker for the
 * functional substrate — the page works today on the main domain
 * gated by the `super_admin` role check on the backend.
 *
 * **Privacy boundary:** the wire shape carries metadata only (tenant
 * identity, plan, locale config, active-user counter). Tenant domain
 * data (products, attributes, values) is NEVER exposed here — the
 * audit row stamps `cross_tenant_access=true` on every read so the
 * forensic trail is mechanical.
 */
export function AdminTenantsListPage() {
  const { t } = useTranslation();
  const [tenants, setTenants] = useState<AdminTenantSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState(false);

  const [search, setSearch] = useState('');
  const [planFilter, setPlanFilter] = useState<PlanFilter>('all');

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    jsonFetch<ListResponse>('/api/admin/tenants', { method: 'GET' })
      .then((data) => {
        if (!cancelled) setTenants(data.member);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        if ((err as { status?: number })?.status === 403) {
          setForbidden(true);
        } else {
          setError(true);
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const plans = useMemo(() => {
    const seen = new Set<string>();
    for (const tenant of tenants) seen.add(tenant.plan);
    return Array.from(seen).sort();
  }, [tenants]);

  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return tenants.filter((tenant) => {
      if (planFilter !== 'all' && tenant.plan !== planFilter) return false;
      if (needle.length > 0) {
        const haystack = `${tenant.code} ${tenant.name} ${tenant.domain ?? ''}`.toLowerCase();
        if (!haystack.includes(needle)) return false;
      }
      return true;
    });
  }, [tenants, search, planFilter]);

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
      <header>
        <div className="flex items-baseline gap-2">
          <Building2 className="size-5 text-accent-violet" aria-hidden="true" />
          <h2 className="display text-xl font-semibold tracking-tight">
            {t('admin.tenants.title')}
          </h2>
        </div>
        <p className="max-w-2xl text-sm text-muted-foreground">{t('admin.tenants.intro')}</p>
      </header>

      <div className="rounded-md border border-dashed bg-amber-50 px-3 py-2 text-xs text-amber-900">
        {t('admin.tenants.privacy_boundary_notice')}
      </div>

      <div className="rounded-lg border bg-background p-3 shadow-sm">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <Input
            type="search"
            placeholder={t('admin.tenants.search_placeholder')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="flex-1"
          />
          <label className="inline-flex items-center gap-2 text-xs text-muted-foreground">
            <span>{t('admin.tenants.filter_plan')}:</span>
            <select
              value={planFilter}
              onChange={(e) => setPlanFilter(e.target.value)}
              className="h-9 rounded-md border border-input bg-background px-2 text-xs"
            >
              <option value="all">{t('admin.tenants.filter_plan_all')}</option>
              {plans.map((plan) => (
                <option key={plan} value={plan}>
                  {plan}
                </option>
              ))}
            </select>
          </label>
          <div className="text-xs text-muted-foreground sm:ml-auto">
            {t('admin.tenants.count', { count: filtered.length })}
          </div>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-8 text-muted-foreground">
          <Loader2 className="size-5 animate-spin" aria-hidden="true" />
        </div>
      ) : error ? (
        <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
          {t('admin.tenants.error_loading')}
        </div>
      ) : filtered.length === 0 ? (
        <div className="rounded-md border bg-background px-3 py-8 text-center text-sm text-muted-foreground">
          {t('admin.tenants.empty')}
        </div>
      ) : (
        <div className="overflow-hidden rounded-lg border bg-background shadow-sm">
          <table className="w-full text-sm">
            <thead className="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th className="px-4 py-2 text-left">{t('admin.tenants.col_tenant')}</th>
                <th className="px-4 py-2 text-left">{t('admin.tenants.col_plan')}</th>
                <th className="px-4 py-2 text-left">{t('admin.tenants.col_users')}</th>
                <th className="px-4 py-2 text-left">{t('admin.tenants.col_locales')}</th>
                <th className="px-4 py-2 text-left">{t('admin.tenants.col_created')}</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((tenant) => (
                <TenantRow key={tenant.id} tenant={tenant} />
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function TenantRow({ tenant }: { tenant: AdminTenantSummary }) {
  const { i18n } = useTranslation();
  const createdAt = new Date(tenant.created_at).toLocaleDateString(i18n.language);

  return (
    <tr className="border-t hover:bg-muted/20">
      <td className="px-4 py-2.5">
        <Link to={`/admin/tenants/${tenant.id}`} className="block">
          <div className="text-sm font-medium hover:underline">{tenant.name}</div>
          <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
            <span className="font-mono">{tenant.code}</span>
            {tenant.domain ? (
              <>
                <span aria-hidden="true">·</span>
                <span>{tenant.domain}</span>
              </>
            ) : null}
          </div>
        </Link>
      </td>
      <td className="px-4 py-2.5">
        <PlanBadge plan={tenant.plan} />
      </td>
      <td className="px-4 py-2.5 text-xs">
        <span className="inline-flex items-center gap-1 text-muted-foreground">
          <Users className="size-3" aria-hidden="true" />
          {tenant.active_users}
        </span>
      </td>
      <td className="px-4 py-2.5">
        <div className="flex flex-wrap gap-1">
          {tenant.enabled_locales.map((locale) => (
            <span
              key={locale}
              className={cn(
                'rounded px-1.5 py-0.5 text-[10px] font-mono ring-1',
                locale === tenant.primary_locale
                  ? 'bg-accent-violet/10 text-accent-violet ring-accent-violet/30'
                  : 'bg-muted text-muted-foreground ring-input',
              )}
            >
              {locale}
            </span>
          ))}
        </div>
      </td>
      <td className="px-4 py-2.5 text-xs text-muted-foreground">{createdAt}</td>
    </tr>
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
