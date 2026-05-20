import { Copy, KeyRound, Loader2, Shield, ShieldOff } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
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
import { cn } from '@/lib/utils';

interface MfaStatus {
  enabled: boolean;
  enabled_at: string | null;
  backup_codes_remaining: number;
  enrolment_pending: boolean;
}

interface EnrolResponse {
  secret: string;
  provisioning_uri: string;
  backup_codes: string[];
}

interface ApiProblem {
  detail?: string;
  title?: string;
}

/**
 * RBAC-P5-013 (#703) + Phase 4 #689 — Profile → Security → MFA
 * section.
 *
 * Encapsulates the full TOTP lifecycle:
 *   - shows current MFA state + recovery-codes remaining
 *   - "Enable" opens an inline wizard (kind chooser today is app-only
 *     because the email TOTP backend isn't wired yet; chooser is
 *     scaffolded for future split)
 *   - "Disable" prompts for the current TOTP/backup code (existing
 *     `POST /api/auth/2fa/disable` requires possession proof — safer
 *     than password re-auth which leaves stolen sessions in control)
 *   - "Generate new codes" rotates the recovery list (requires code)
 *
 * Secrets (TOTP shared secret + recovery codes) surface ONCE on enrol
 * and ONCE on regenerate — the section forces an acknowledge checkbox
 * before letting the operator close the modal, mirroring the API token
 * wizard pattern from #700.
 */
export function MfaSection() {
  const { t } = useTranslation();
  const [status, setStatus] = useState<MfaStatus | null>(null);
  const [loading, setLoading] = useState(true);

  const [wizardOpen, setWizardOpen] = useState(false);
  const [disableOpen, setDisableOpen] = useState(false);
  const [regenerateOpen, setRegenerateOpen] = useState(false);

  const reload = () => {
    setLoading(true);
    jsonFetch<MfaStatus>('/api/me/mfa/status', { method: 'GET' })
      .then(setStatus)
      .catch(() => toast.error(t('settings.security.mfa.error_status')))
      .finally(() => setLoading(false));
  };

  useEffect(reload, [t]);

  if (loading && !status) {
    return (
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Loader2 className="size-4 animate-spin" aria-hidden="true" />
        {t('settings.security.mfa.loading')}
      </div>
    );
  }

  const enabled = Boolean(status?.enabled);
  const pending = Boolean(status?.enrolment_pending);

  return (
    <div className="space-y-3">
      <div className="flex items-start justify-between gap-3">
        <div className="space-y-1">
          <h3 className="display text-lg font-semibold tracking-tight">
            {t('settings.security.mfa.title')}
          </h3>
          <p className="max-w-2xl text-sm text-muted-foreground">
            {t('settings.security.mfa.intro')}
          </p>
        </div>
        <MfaBadge enabled={enabled} pending={pending} />
      </div>

      {enabled && status ? (
        <div className="grid gap-3 sm:grid-cols-2">
          <Stat
            label={t('settings.security.mfa.stat_enabled_at')}
            value={status.enabled_at ? new Date(status.enabled_at).toLocaleString() : '—'}
          />
          <Stat
            label={t('settings.security.mfa.stat_codes_remaining')}
            value={t('settings.security.mfa.stat_codes_value', {
              count: status.backup_codes_remaining,
            })}
            warn={status.backup_codes_remaining <= 3}
          />
        </div>
      ) : null}

      <div className="flex flex-wrap items-center gap-2">
        {enabled ? (
          <>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => setRegenerateOpen(true)}
              className="gap-1.5"
            >
              <KeyRound className="size-3.5" aria-hidden="true" />
              {t('settings.security.mfa.regenerate_button')}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => setDisableOpen(true)}
              className="gap-1.5 text-rose-700 hover:bg-rose-50"
            >
              <ShieldOff className="size-3.5" aria-hidden="true" />
              {t('settings.security.mfa.disable_button')}
            </Button>
          </>
        ) : (
          <Button type="button" size="sm" onClick={() => setWizardOpen(true)} className="gap-1.5">
            <Shield className="size-3.5" aria-hidden="true" />
            {pending
              ? t('settings.security.mfa.resume_enable_button')
              : t('settings.security.mfa.enable_button')}
          </Button>
        )}
      </div>

      <EnableWizard
        open={wizardOpen}
        onOpenChange={setWizardOpen}
        onSuccess={() => {
          reload();
          setWizardOpen(false);
        }}
      />
      <DisableModal
        open={disableOpen}
        onOpenChange={setDisableOpen}
        onSuccess={() => {
          reload();
          setDisableOpen(false);
        }}
      />
      <RegenerateModal
        open={regenerateOpen}
        onOpenChange={setRegenerateOpen}
        onSuccess={() => {
          reload();
          // Stay open — modal displays the new codes; operator closes
          // after copying.
        }}
      />
    </div>
  );
}

function MfaBadge({ enabled, pending }: { enabled: boolean; pending: boolean }) {
  const { t } = useTranslation();
  if (enabled) {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-200">
        <span className="size-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
        {t('settings.security.mfa.badge_enabled')}
      </span>
    );
  }
  if (pending) {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-700 ring-1 ring-amber-200">
        <span className="size-1.5 rounded-full bg-amber-500" aria-hidden="true" />
        {t('settings.security.mfa.badge_pending')}
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 rounded-md bg-muted px-2 py-1 text-[11px] font-medium text-muted-foreground ring-1 ring-input">
      <span className="size-1.5 rounded-full bg-muted-foreground/40" aria-hidden="true" />
      {t('settings.security.mfa.badge_disabled')}
    </span>
  );
}

function Stat({ label, value, warn = false }: { label: string; value: string; warn?: boolean }) {
  return (
    <div className="rounded-md border bg-muted/30 px-3 py-2 text-sm">
      <div className="text-[11px] uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className={cn('font-medium', warn && 'text-amber-700')}>{value}</div>
    </div>
  );
}

interface ModalCommon {
  open: boolean;
  onOpenChange: (next: boolean) => void;
  onSuccess: () => void;
}

function EnableWizard({ open, onOpenChange, onSuccess }: ModalCommon) {
  const { t } = useTranslation();
  const [enrolment, setEnrolment] = useState<EnrolResponse | null>(null);
  const [code, setCode] = useState('');
  const [acknowledged, setAcknowledged] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [stage, setStage] = useState<'idle' | 'show-secret' | 'verified'>('idle');

  useEffect(() => {
    if (!open) {
      setEnrolment(null);
      setCode('');
      setAcknowledged(false);
      setSubmitting(false);
      setStage('idle');
    }
  }, [open]);

  const startEnrol = async () => {
    setSubmitting(true);
    try {
      const result = await jsonFetch<EnrolResponse>('/api/auth/2fa/enrol', {
        method: 'POST',
        accept: 'application/json',
        contentType: 'application/json',
      });
      setEnrolment(result);
      setStage('show-secret');
    } catch (error: unknown) {
      const body = (error as { body?: ApiProblem })?.body;
      toast.error(body?.detail ?? t('settings.security.mfa.error_enrol'));
    } finally {
      setSubmitting(false);
    }
  };

  const verifyCode = async (event: FormEvent) => {
    event.preventDefault();
    if (submitting || code.trim().length === 0) return;
    setSubmitting(true);
    try {
      await jsonFetch('/api/auth/2fa/verify', {
        method: 'POST',
        body: { code: code.trim() },
        accept: 'application/json',
        contentType: 'application/json',
      });
      toast.success(t('settings.security.mfa.toast_enabled'));
      setStage('verified');
    } catch (error: unknown) {
      const body = (error as { body?: ApiProblem })?.body;
      toast.error(body?.detail ?? t('settings.security.mfa.error_verify'));
    } finally {
      setSubmitting(false);
    }
  };

  const copyCodes = async () => {
    if (!enrolment) return;
    await navigator.clipboard.writeText(enrolment.backup_codes.join('\n'));
    toast.success(t('settings.security.mfa.toast_codes_copied'));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('settings.security.mfa.wizard.title')}</DialogTitle>
        </DialogHeader>

        {stage === 'idle' ? (
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">
              {t('settings.security.mfa.wizard.intro')}
            </p>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                {t('settings.security.mfa.wizard.cancel')}
              </Button>
              <Button type="button" onClick={startEnrol} disabled={submitting}>
                {submitting
                  ? t('settings.security.mfa.wizard.starting')
                  : t('settings.security.mfa.wizard.start')}
              </Button>
            </DialogFooter>
          </div>
        ) : null}

        {stage === 'show-secret' && enrolment ? (
          <form onSubmit={verifyCode} className="space-y-3">
            <p className="text-sm text-muted-foreground">
              {t('settings.security.mfa.wizard.scan_intro')}
            </p>
            <div className="space-y-1.5 rounded-md border bg-muted/30 p-3 text-xs">
              <Label className="text-[11px] uppercase tracking-wide text-muted-foreground">
                {t('settings.security.mfa.wizard.secret_label')}
              </Label>
              <code className="block break-all font-mono text-[11px]">{enrolment.secret}</code>
              <p className="text-[11px] text-muted-foreground">
                {t('settings.security.mfa.wizard.secret_hint')}
              </p>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="mfa-verify-code">
                {t('settings.security.mfa.wizard.code_label')}
              </Label>
              <Input
                id="mfa-verify-code"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                inputMode="numeric"
                pattern="\d{6}"
                maxLength={6}
                placeholder="123456"
                autoFocus
                className="font-mono"
              />
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => onOpenChange(false)}
                disabled={submitting}
              >
                {t('settings.security.mfa.wizard.cancel')}
              </Button>
              <Button type="submit" disabled={submitting || code.trim().length === 0}>
                {submitting
                  ? t('settings.security.mfa.wizard.verifying')
                  : t('settings.security.mfa.wizard.verify')}
              </Button>
            </DialogFooter>
          </form>
        ) : null}

        {stage === 'verified' && enrolment ? (
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">
              {t('settings.security.mfa.wizard.success_intro')}
            </p>
            <div className="space-y-2 rounded-md border border-dashed border-rose-200 bg-rose-50 p-3">
              <div className="flex items-center justify-between gap-2">
                <Label className="text-xs font-medium uppercase tracking-wide text-rose-700">
                  {t('settings.security.mfa.wizard.codes_label')}
                </Label>
                <Button
                  type="button"
                  size="icon"
                  variant="outline"
                  onClick={copyCodes}
                  aria-label={t('settings.security.mfa.wizard.copy_codes')}
                >
                  <Copy className="size-3.5" aria-hidden="true" />
                </Button>
              </div>
              <ul className="grid grid-cols-2 gap-1 font-mono text-[12px]">
                {enrolment.backup_codes.map((c) => (
                  <li key={c} className="rounded bg-background px-2 py-1">
                    {c}
                  </li>
                ))}
              </ul>
              <p className="text-[11px] text-rose-700">
                {t('settings.security.mfa.wizard.codes_warning')}
              </p>
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={acknowledged}
                onChange={(e) => setAcknowledged(e.target.checked)}
              />
              {t('settings.security.mfa.wizard.acknowledge')}
            </label>
            <DialogFooter>
              <Button type="button" onClick={onSuccess} disabled={!acknowledged}>
                {t('settings.security.mfa.wizard.done')}
              </Button>
            </DialogFooter>
          </div>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

function DisableModal({ open, onOpenChange, onSuccess }: ModalCommon) {
  const { t } = useTranslation();
  const [code, setCode] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!open) {
      setCode('');
      setSubmitting(false);
    }
  }, [open]);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (submitting || code.trim().length === 0) return;
    setSubmitting(true);
    try {
      await jsonFetch('/api/auth/2fa/disable', {
        method: 'POST',
        body: { code: code.trim() },
        accept: 'application/json',
        contentType: 'application/json',
      });
      toast.success(t('settings.security.mfa.toast_disabled'));
      onSuccess();
    } catch (error: unknown) {
      const body = (error as { body?: ApiProblem })?.body;
      toast.error(body?.detail ?? t('settings.security.mfa.error_disable'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t('settings.security.mfa.disable_modal.title')}</DialogTitle>
        </DialogHeader>
        <form onSubmit={submit} className="space-y-3">
          <p className="text-sm text-muted-foreground">
            {t('settings.security.mfa.disable_modal.body')}
          </p>
          <div className="space-y-1.5">
            <Label htmlFor="mfa-disable-code">
              {t('settings.security.mfa.disable_modal.code_label')}
            </Label>
            <Input
              id="mfa-disable-code"
              value={code}
              onChange={(e) => setCode(e.target.value)}
              maxLength={10}
              autoFocus
              className="font-mono"
            />
            <p className="text-[11px] text-muted-foreground">
              {t('settings.security.mfa.disable_modal.code_hint')}
            </p>
          </div>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={submitting}
            >
              {t('settings.security.mfa.disable_modal.cancel')}
            </Button>
            <Button
              type="submit"
              disabled={submitting || code.trim().length === 0}
              className="text-rose-700 hover:bg-rose-50"
            >
              {submitting
                ? t('settings.security.mfa.disable_modal.submitting')
                : t('settings.security.mfa.disable_modal.confirm')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function RegenerateModal({ open, onOpenChange, onSuccess }: ModalCommon) {
  const { t } = useTranslation();
  const [code, setCode] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [newCodes, setNewCodes] = useState<string[] | null>(null);
  const [acknowledged, setAcknowledged] = useState(false);

  useEffect(() => {
    if (!open) {
      setCode('');
      setSubmitting(false);
      setNewCodes(null);
      setAcknowledged(false);
    }
  }, [open]);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (submitting || code.trim().length === 0) return;
    setSubmitting(true);
    try {
      const result = await jsonFetch<{ backup_codes: string[] }>(
        '/api/me/mfa/recovery-codes/regenerate',
        {
          method: 'POST',
          body: { code: code.trim() },
          accept: 'application/json',
          contentType: 'application/json',
        },
      );
      setNewCodes(result.backup_codes);
      onSuccess();
    } catch (error: unknown) {
      const body = (error as { body?: ApiProblem })?.body;
      toast.error(body?.detail ?? t('settings.security.mfa.error_regenerate'));
    } finally {
      setSubmitting(false);
    }
  };

  const copyCodes = async () => {
    if (!newCodes) return;
    await navigator.clipboard.writeText(newCodes.join('\n'));
    toast.success(t('settings.security.mfa.toast_codes_copied'));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t('settings.security.mfa.regenerate_modal.title')}</DialogTitle>
        </DialogHeader>
        {newCodes === null ? (
          <form onSubmit={submit} className="space-y-3">
            <p className="text-sm text-muted-foreground">
              {t('settings.security.mfa.regenerate_modal.body')}
            </p>
            <div className="space-y-1.5">
              <Label htmlFor="mfa-rotate-code">
                {t('settings.security.mfa.regenerate_modal.code_label')}
              </Label>
              <Input
                id="mfa-rotate-code"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                maxLength={10}
                autoFocus
                className="font-mono"
              />
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => onOpenChange(false)}
                disabled={submitting}
              >
                {t('settings.security.mfa.regenerate_modal.cancel')}
              </Button>
              <Button type="submit" disabled={submitting || code.trim().length === 0}>
                {submitting
                  ? t('settings.security.mfa.regenerate_modal.submitting')
                  : t('settings.security.mfa.regenerate_modal.confirm')}
              </Button>
            </DialogFooter>
          </form>
        ) : (
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">
              {t('settings.security.mfa.regenerate_modal.success_intro')}
            </p>
            <div className="space-y-2 rounded-md border border-dashed border-rose-200 bg-rose-50 p-3">
              <div className="flex items-center justify-between gap-2">
                <Label className="text-xs font-medium uppercase tracking-wide text-rose-700">
                  {t('settings.security.mfa.regenerate_modal.codes_label')}
                </Label>
                <Button
                  type="button"
                  size="icon"
                  variant="outline"
                  onClick={copyCodes}
                  aria-label={t('settings.security.mfa.wizard.copy_codes')}
                >
                  <Copy className="size-3.5" aria-hidden="true" />
                </Button>
              </div>
              <ul className="grid grid-cols-2 gap-1 font-mono text-[12px]">
                {newCodes.map((c) => (
                  <li key={c} className="rounded bg-background px-2 py-1">
                    {c}
                  </li>
                ))}
              </ul>
              <p className="text-[11px] text-rose-700">
                {t('settings.security.mfa.regenerate_modal.codes_warning')}
              </p>
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={acknowledged}
                onChange={(e) => setAcknowledged(e.target.checked)}
              />
              {t('settings.security.mfa.regenerate_modal.acknowledge')}
            </label>
            <DialogFooter>
              <Button type="button" onClick={() => onOpenChange(false)} disabled={!acknowledged}>
                {t('settings.security.mfa.regenerate_modal.done')}
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
