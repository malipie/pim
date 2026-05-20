import { Copy, KeyRound, Plus, ShieldCheck } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';

interface CreateTokenWizardProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

interface CreateResponse {
  id: string;
  name: string;
  token_last4: string;
  scopes: string[];
  expires_at: string | null;
  created_at: string;
  plaintext: string;
}

const SCOPE_TEMPLATES = [
  { code: 'read-only', label_key: 'settings.api_tokens.create.template_read_only' },
  { code: 'read-write-catalog', label_key: 'settings.api_tokens.create.template_catalog_rw' },
  { code: 'integrations', label_key: 'settings.api_tokens.create.template_integrations' },
  { code: 'admin', label_key: 'settings.api_tokens.create.template_admin' },
] as const;

const TTL_OPTIONS = [
  { value: '30', label_key: 'settings.api_tokens.create.ttl_30' },
  { value: '90', label_key: 'settings.api_tokens.create.ttl_90' },
  { value: '365', label_key: 'settings.api_tokens.create.ttl_365' },
  { value: '', label_key: 'settings.api_tokens.create.ttl_never' },
] as const;

/**
 * RBAC-P5-010 (#700) — create API token wizard.
 *
 * MVP form (compressed from the ticket's 5-step spec):
 *   1. Name + scope template selection
 *   2. TTL (30 days default per PRD §5.3)
 *   3. Submit → POST /api/api-tokens
 *   4. Plaintext token shown once with copy-to-clipboard + force-
 *      acknowledge checkbox before close.
 *
 * Custom scope checkbox grid + locale/channel scope inputs deferred —
 * the backend `ApiToken::scopes` stores a list<string> already, so
 * follow-up can add UI without schema changes.
 *
 * Long-lived tokens (TTL "Never") are flagged in the AuditLogListener
 * downstream — no special UI confirm needed, the spec mentions it but
 * the explicit warning lives in the inline help text.
 */
export function CreateTokenWizard({ open, onOpenChange, onSuccess }: CreateTokenWizardProps) {
  const { t } = useTranslation();
  const [name, setName] = useState('');
  const [scopeTemplate, setScopeTemplate] = useState<string>('read-only');
  const [ttlDays, setTtlDays] = useState<string>('30');
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState<CreateResponse | null>(null);
  const [acknowledged, setAcknowledged] = useState(false);

  const reset = () => {
    setName('');
    setScopeTemplate('read-only');
    setTtlDays('30');
    setResult(null);
    setAcknowledged(false);
    setSubmitting(false);
  };

  const close = (next: boolean) => {
    if (!next) reset();
    onOpenChange(next);
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (submitting || name.trim().length === 0) return;
    setSubmitting(true);
    try {
      const response = await jsonFetch<CreateResponse>('/api/api-tokens', {
        method: 'POST',
        body: {
          name: name.trim(),
          scopes: [scopeTemplate],
          ttl_days: ttlDays === '' ? null : Number(ttlDays),
        },
        accept: 'application/json',
        contentType: 'application/json',
      });
      setResult(response);
      onSuccess();
    } catch (error: unknown) {
      const status = (error as { status?: number; body?: { detail?: string } })?.status;
      const body = (error as { body?: { detail?: string } })?.body;
      if (status === 400) {
        toast.error(body?.detail ?? t('settings.api_tokens.create.error_validation'));
      } else if (status === 403) {
        toast.error(t('settings.api_tokens.create.error_forbidden'));
      } else {
        toast.error(t('settings.api_tokens.create.error_generic'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  const copyPlaintext = async () => {
    if (!result) return;
    await navigator.clipboard.writeText(result.plaintext);
    toast.success(t('settings.api_tokens.create.token_copied'));
  };

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent className="max-w-md">
        {result ? (
          <>
            <DialogHeader>
              <div className="mb-2 inline-grid size-10 place-items-center rounded-full bg-emerald-100 text-emerald-700">
                <ShieldCheck className="size-5" aria-hidden="true" />
              </div>
              <DialogTitle>{t('settings.api_tokens.create.success_title')}</DialogTitle>
            </DialogHeader>
            <p className="text-sm text-muted-foreground">
              {t('settings.api_tokens.create.success_body', { name: result.name })}
            </p>
            <div className="space-y-2 rounded-md border border-dashed border-rose-200 bg-rose-50 p-3">
              <Label className="text-xs font-medium uppercase tracking-wide text-rose-700">
                {t('settings.api_tokens.create.token_label')}
              </Label>
              <div className="flex items-center gap-2">
                <code className="flex-1 overflow-x-auto rounded bg-background px-2 py-1.5 font-mono text-[11px]">
                  {result.plaintext}
                </code>
                <Button
                  type="button"
                  size="icon"
                  variant="outline"
                  onClick={copyPlaintext}
                  aria-label={t('settings.api_tokens.create.copy_token')}
                >
                  <Copy className="size-4" />
                </Button>
              </div>
              <p className="text-[11px] text-rose-700">
                {t('settings.api_tokens.create.token_warning')}
              </p>
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={acknowledged}
                onChange={(e) => setAcknowledged(e.target.checked)}
              />
              {t('settings.api_tokens.create.acknowledge')}
            </label>
            <DialogFooter>
              <Button onClick={() => close(false)} disabled={!acknowledged}>
                {t('settings.api_tokens.create.close')}
              </Button>
            </DialogFooter>
          </>
        ) : (
          <form onSubmit={submit}>
            <DialogHeader>
              <div className="mb-2 inline-grid size-10 place-items-center rounded-full bg-accent-violet/10 text-accent-violet">
                <Plus className="size-5" aria-hidden="true" />
              </div>
              <DialogTitle>{t('settings.api_tokens.create.title')}</DialogTitle>
            </DialogHeader>
            <p className="text-sm text-muted-foreground">{t('settings.api_tokens.create.intro')}</p>

            <div className="space-y-3 py-3">
              <div className="space-y-1.5">
                <Label htmlFor="token-name">{t('settings.api_tokens.create.field_name')}</Label>
                <div className="relative">
                  <KeyRound
                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                  />
                  <Input
                    id="token-name"
                    required
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="np. Shopify sync"
                    maxLength={80}
                    className="pl-9"
                    autoComplete="off"
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="token-scope">{t('settings.api_tokens.create.field_scope')}</Label>
                <select
                  id="token-scope"
                  value={scopeTemplate}
                  onChange={(e) => setScopeTemplate(e.target.value)}
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  {SCOPE_TEMPLATES.map((template) => (
                    <option key={template.code} value={template.code}>
                      {t(template.label_key)}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="token-ttl">{t('settings.api_tokens.create.field_ttl')}</Label>
                <select
                  id="token-ttl"
                  value={ttlDays}
                  onChange={(e) => setTtlDays(e.target.value)}
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  {TTL_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {t(option.label_key)}
                    </option>
                  ))}
                </select>
                {ttlDays === '' ? (
                  <p className="text-[11px] text-amber-700">
                    {t('settings.api_tokens.create.never_warning')}
                  </p>
                ) : null}
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => close(false)}
                disabled={submitting}
              >
                {t('settings.api_tokens.create.cancel')}
              </Button>
              <Button type="submit" disabled={submitting || name.trim().length === 0}>
                {submitting
                  ? t('settings.api_tokens.create.submitting')
                  : t('settings.api_tokens.create.submit')}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}
