import { ExternalLink, KeyRound, Loader2, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

import { SSO_KINDS, type SsoKind, type SsoProvider } from './types';

interface ListResponse {
  member: SsoProvider[];
  totalItems: number;
}

interface ApiProblem {
  detail?: string;
  code?: string;
}

/**
 * RBAC-P5-014 (#704) — `/settings/sso` Settings tab.
 *
 * Manages tenant-level SSO providers (Google Workspace / Microsoft 365 /
 * SAML 2.0). One provider per kind per tenant — POST 409s on duplicate.
 *
 * Each provider gets its own collapsed card by kind; selecting a card
 * either shows the existing config (PATCH) or opens the "add provider"
 * form (POST). Secrets land masked from the backend (`'****'`); the
 * form round-trips that mask on save so editing a non-secret field
 * doesn't accidentally clobber the real value.
 *
 * Test-connection deferred — links to the existing
 * `/api/auth/sso/{tenant}/{kind}/login` flow which already exercises
 * the OAuth round-trip end-to-end (used by the login screen). Per the
 * ticket, the dedicated "Test connection" button is a Phase 6 nicety
 * once we have IdP credentials in CI; the existing login link covers
 * the live-stack smoke flow today.
 */
export function SsoSettingsPage() {
  const { t } = useTranslation();
  const [providers, setProviders] = useState<SsoProvider[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  const reload = useCallback(() => {
    setLoading(true);
    setError(false);
    jsonFetch<ListResponse>('/api/sso/providers', { method: 'GET' })
      .then((data) => setProviders(data.member))
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    reload();
  }, [reload]);

  const byKind = useMemo(() => {
    const map: Partial<Record<SsoKind, SsoProvider>> = {};
    for (const provider of providers) {
      map[provider.kind] = provider;
    }
    return map;
  }, [providers]);

  return (
    <div className="space-y-4">
      <header>
        <h2 className="display text-xl font-semibold tracking-tight">{t('settings.sso.title')}</h2>
        <p className="max-w-2xl text-sm text-muted-foreground">{t('settings.sso.intro')}</p>
      </header>

      {error ? (
        <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
          {t('settings.sso.error_loading')}
        </div>
      ) : null}

      {loading ? (
        <div className="flex items-center justify-center py-8 text-muted-foreground">
          <Loader2 className="size-5 animate-spin" aria-hidden="true" />
        </div>
      ) : (
        <div className="grid gap-3 md:grid-cols-3">
          {SSO_KINDS.map((kind) => (
            <ProviderCard
              key={kind}
              kind={kind}
              provider={byKind[kind] ?? null}
              onChanged={reload}
            />
          ))}
        </div>
      )}

      <div className="rounded-md border border-dashed bg-muted/30 px-3 py-2 text-[11px] text-muted-foreground">
        {t('settings.sso.deferred_notice')}
      </div>
    </div>
  );
}

interface ProviderCardProps {
  kind: SsoKind;
  provider: SsoProvider | null;
  onChanged: () => void;
}

function ProviderCard({ kind, provider, onChanged }: ProviderCardProps) {
  const { t } = useTranslation();
  const [editing, setEditing] = useState(false);

  return (
    <div
      className={cn(
        'overflow-hidden rounded-lg border bg-background shadow-sm transition-colors',
        provider?.enabled && 'border-emerald-200',
      )}
    >
      <div className="border-b bg-muted/40 px-4 py-3">
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <ShieldCheck className="size-4 text-accent-violet" aria-hidden="true" />
            <h3 className="text-sm font-semibold">{t(`settings.sso.kind.${kind}`)}</h3>
          </div>
          {provider ? (
            <span
              className={cn(
                'rounded px-2 py-0.5 text-[10px] font-medium ring-1',
                provider.enabled
                  ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                  : 'bg-muted text-muted-foreground ring-input',
              )}
            >
              {provider.enabled
                ? t('settings.sso.status_enabled')
                : t('settings.sso.status_disabled')}
            </span>
          ) : (
            <span className="rounded bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
              {t('settings.sso.status_not_configured')}
            </span>
          )}
        </div>
      </div>
      <div className="p-3">
        {provider && !editing ? (
          <ProviderSummary
            provider={provider}
            onEdit={() => setEditing(true)}
            onChanged={onChanged}
          />
        ) : (
          <ProviderForm
            kind={kind}
            existing={provider}
            onDone={(reload) => {
              setEditing(false);
              if (reload) onChanged();
            }}
          />
        )}
      </div>
    </div>
  );
}

function ProviderSummary({
  provider,
  onEdit,
  onChanged,
}: {
  provider: SsoProvider;
  onEdit: () => void;
  onChanged: () => void;
}) {
  const { t } = useTranslation();
  const [toggling, setToggling] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const toggle = async () => {
    setToggling(true);
    try {
      await jsonFetch(`/api/sso/providers/${provider.id}`, {
        method: 'PATCH',
        body: { enabled: !provider.enabled },
        accept: 'application/json',
        contentType: 'application/json',
      });
      toast.success(
        provider.enabled
          ? t('settings.sso.toast_disabled', { name: provider.name })
          : t('settings.sso.toast_enabled', { name: provider.name }),
      );
      onChanged();
    } catch {
      toast.error(t('settings.sso.error_generic'));
    } finally {
      setToggling(false);
    }
  };

  const handleDelete = async () => {
    if (!window.confirm(t('settings.sso.confirm_delete', { name: provider.name }))) return;
    setDeleting(true);
    try {
      await jsonFetch(`/api/sso/providers/${provider.id}`, {
        method: 'DELETE',
        accept: 'application/json',
      });
      toast.success(t('settings.sso.toast_deleted', { name: provider.name }));
      onChanged();
    } catch {
      toast.error(t('settings.sso.error_generic'));
    } finally {
      setDeleting(false);
    }
  };

  const entries = Object.entries(provider.config);
  return (
    <div className="space-y-2">
      <div className="text-sm font-medium">{provider.name}</div>
      {entries.length > 0 ? (
        <dl className="space-y-1 text-xs">
          {entries.map(([key, value]) => (
            <div key={key} className="flex gap-2">
              <dt className="min-w-[7rem] font-mono text-muted-foreground">{key}</dt>
              <dd className="break-all font-mono">{renderConfigValue(value)}</dd>
            </div>
          ))}
        </dl>
      ) : (
        <p className="text-xs text-muted-foreground">{t('settings.sso.config_empty')}</p>
      )}
      <div className="flex flex-wrap items-center gap-2 pt-2">
        <Button type="button" size="sm" variant="outline" onClick={onEdit}>
          {t('settings.sso.edit')}
        </Button>
        <Button type="button" size="sm" variant="outline" onClick={toggle} disabled={toggling}>
          {provider.enabled ? t('settings.sso.disable') : t('settings.sso.enable')}
        </Button>
        <a
          href={`/api/auth/sso/demo/${kindToPathSegment(provider.kind)}/login`}
          target="_blank"
          rel="noreferrer noopener"
          className="inline-flex items-center gap-1 text-xs text-accent-violet hover:underline"
        >
          <ExternalLink className="size-3" aria-hidden="true" />
          {t('settings.sso.test_link')}
        </a>
        <Button
          type="button"
          size="sm"
          variant="outline"
          onClick={handleDelete}
          disabled={deleting}
          className="ml-auto text-rose-700 hover:bg-rose-50"
        >
          <Trash2 className="size-3" aria-hidden="true" />
        </Button>
      </div>
    </div>
  );
}

interface ProviderFormProps {
  kind: SsoKind;
  existing: SsoProvider | null;
  onDone: (reload: boolean) => void;
}

function ProviderForm({ kind, existing, onDone }: ProviderFormProps) {
  const { t } = useTranslation();
  const [name, setName] = useState(existing?.name ?? defaultName(kind));
  const [enabled, setEnabled] = useState(existing?.enabled ?? false);
  const [configText, setConfigText] = useState(
    existing ? JSON.stringify(existing.config, null, 2) : defaultConfigSkeleton(kind),
  );
  const [configError, setConfigError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    let config: Record<string, unknown>;
    try {
      config = JSON.parse(configText) as Record<string, unknown>;
      setConfigError(null);
    } catch {
      setConfigError(t('settings.sso.error_invalid_json'));
      return;
    }
    setSubmitting(true);
    try {
      if (existing) {
        await jsonFetch(`/api/sso/providers/${existing.id}`, {
          method: 'PATCH',
          body: { name: name.trim(), enabled, config },
          accept: 'application/json',
          contentType: 'application/json',
        });
        toast.success(t('settings.sso.toast_updated', { name: name.trim() }));
      } else {
        await jsonFetch('/api/sso/providers', {
          method: 'POST',
          body: { kind, name: name.trim(), enabled, config },
          accept: 'application/json',
          contentType: 'application/json',
        });
        toast.success(t('settings.sso.toast_created', { name: name.trim() }));
      }
      onDone(true);
    } catch (error: unknown) {
      const status = (error as { status?: number; body?: ApiProblem })?.status;
      const body = (error as { body?: ApiProblem })?.body;
      if (status === 409 && body?.code === 'duplicate_kind') {
        toast.error(body?.detail ?? t('settings.sso.error_duplicate'));
      } else if (status === 400) {
        toast.error(body?.detail ?? t('settings.sso.error_validation'));
      } else if (status === 403) {
        toast.error(t('settings.sso.error_forbidden'));
      } else {
        toast.error(t('settings.sso.error_generic'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-2">
      <div className="space-y-1">
        <Label htmlFor={`sso-${kind}-name`} className="text-xs">
          {t('settings.sso.field_name')}
        </Label>
        <Input
          id={`sso-${kind}-name`}
          value={name}
          onChange={(e) => setName(e.target.value)}
          required
          maxLength={80}
          className="text-sm"
        />
      </div>
      <label className="flex items-center gap-2 text-xs">
        <input
          type="checkbox"
          checked={enabled}
          onChange={(e) => setEnabled(e.target.checked)}
          className="size-3.5"
        />
        {t('settings.sso.field_enabled')}
      </label>
      <div className="space-y-1">
        <Label htmlFor={`sso-${kind}-config`} className="text-xs">
          {t('settings.sso.field_config')}
        </Label>
        <textarea
          id={`sso-${kind}-config`}
          value={configText}
          onChange={(e) => {
            setConfigText(e.target.value);
            setConfigError(null);
          }}
          rows={8}
          className="w-full rounded-md border border-input bg-background p-2 font-mono text-[11px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
        {configError ? <p className="text-[11px] text-rose-700">{configError}</p> : null}
        <p className="text-[11px] text-muted-foreground">{t('settings.sso.config_hint')}</p>
      </div>
      <div className="flex items-center justify-end gap-2 pt-1">
        {existing ? (
          <Button
            type="button"
            size="sm"
            variant="ghost"
            onClick={() => onDone(false)}
            disabled={submitting}
          >
            {t('settings.sso.cancel')}
          </Button>
        ) : null}
        <Button type="submit" size="sm" disabled={submitting} className="gap-1.5">
          {existing ? (
            <KeyRound className="size-3.5" aria-hidden="true" />
          ) : (
            <Plus className="size-3.5" aria-hidden="true" />
          )}
          {submitting
            ? t('settings.sso.saving')
            : existing
              ? t('settings.sso.save_changes')
              : t('settings.sso.save_create')}
        </Button>
      </div>
    </form>
  );
}

function renderConfigValue(value: unknown): string {
  if (typeof value === 'string') return value;
  if (typeof value === 'boolean' || typeof value === 'number') return String(value);
  if (value === null || value === undefined) return '—';
  return JSON.stringify(value);
}

function defaultName(kind: SsoKind): string {
  switch (kind) {
    case 'google_workspace':
      return 'Google Workspace';
    case 'microsoft_365':
      return 'Microsoft 365';
    case 'saml':
      return 'SAML 2.0';
  }
}

function defaultConfigSkeleton(kind: SsoKind): string {
  switch (kind) {
    case 'google_workspace':
      return JSON.stringify(
        {
          client_id: '',
          client_secret: '',
          hosted_domain: '',
          allowed_domains: [],
          auto_create_users: true,
        },
        null,
        2,
      );
    case 'microsoft_365':
      return JSON.stringify(
        {
          client_id: '',
          client_secret: '',
          azure_tenant_id: '',
          allowed_domains: [],
          auto_create_users: true,
        },
        null,
        2,
      );
    case 'saml':
      return JSON.stringify(
        {
          idp_entity_id: '',
          idp_sso_url: '',
          idp_certificate: '',
          attribute_email: 'email',
          attribute_name: 'name',
          enforce_sso: false,
        },
        null,
        2,
      );
  }
}

function kindToPathSegment(kind: SsoKind): string {
  switch (kind) {
    case 'google_workspace':
      return 'google';
    case 'microsoft_365':
      return 'microsoft';
    case 'saml':
      return 'saml';
  }
}
