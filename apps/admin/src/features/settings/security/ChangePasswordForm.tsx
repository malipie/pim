import { Eye, EyeOff, KeyRound } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/toast';
import { authProvider } from '@/lib/auth-provider';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

const MIN_LENGTH = 12;

interface Strength {
  score: 0 | 1 | 2 | 3 | 4;
  label: string;
  colour: string;
}

function evaluateStrength(value: string, t: (key: string) => string): Strength {
  if (value.length === 0) {
    return { score: 0, label: t('settings.security.strength.empty'), colour: 'bg-zinc-200' };
  }
  let score = 0;
  if (value.length >= MIN_LENGTH) score += 1;
  if (/[a-z]/.test(value)) score += 1;
  if (/[A-Z]/.test(value)) score += 1;
  if (/\d/.test(value)) score += 1;
  if (/[^A-Za-z0-9]/.test(value)) score += 1;
  // collapse the 0-5 raw scale into the 0-4 visualised steps.
  const clamped = Math.min(4, score) as 0 | 1 | 2 | 3 | 4;
  const labels: Record<typeof clamped, { label: string; colour: string }> = {
    0: { label: t('settings.security.strength.empty'), colour: 'bg-zinc-200' },
    1: { label: t('settings.security.strength.weak'), colour: 'bg-rose-400' },
    2: { label: t('settings.security.strength.fair'), colour: 'bg-amber-400' },
    3: { label: t('settings.security.strength.good'), colour: 'bg-lime-500' },
    4: { label: t('settings.security.strength.strong'), colour: 'bg-emerald-500' },
  };
  return { score: clamped, ...labels[clamped] };
}

/**
 * RBAC-P5-012 (#702) — change-password form.
 *
 * Fields:
 *   - current_password (required, type=password, toggleable visibility),
 *   - new_password (required, min 12 chars hard, strength meter advisory),
 *   - confirm_password (must match new_password).
 *
 * On success:
 *   1. POST `/api/me/change-password` returns 204.
 *   2. Toast "Hasło zmienione" (success).
 *   3. Force re-login — drain the auth provider and navigate to /login.
 *      This matches PRD §3 — the operator's new credential becomes the
 *      only valid one, the JWT in memory becomes stale once the
 *      refresh-token revocation lands in a follow-up.
 */
export function ChangePasswordForm() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const strength = evaluateStrength(newPassword, t);
  const confirmMismatch = confirmPassword.length > 0 && newPassword !== confirmPassword;
  const tooShort = newPassword.length > 0 && newPassword.length < MIN_LENGTH;
  const canSubmit =
    currentPassword.length > 0 &&
    newPassword.length >= MIN_LENGTH &&
    !confirmMismatch &&
    !submitting;

  const onSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!canSubmit) return;
    setSubmitting(true);
    try {
      await jsonFetch('/api/me/change-password', {
        method: 'POST',
        body: { current_password: currentPassword, new_password: newPassword },
        accept: 'application/json',
        contentType: 'application/json',
      });
      toast.success(t('settings.security.toast_success'));
      // Drain the local token + refresh cookie before the next reload.
      await authProvider.logout?.({});
      navigate('/login', { replace: true });
    } catch (error: unknown) {
      const status = (error as { status?: number })?.status;
      if (status === 401) {
        toast.error(t('settings.security.error_wrong_current'));
      } else if (status === 400) {
        toast.error(t('settings.security.error_too_short'));
      } else {
        toast.error(t('settings.security.error_generic'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form className="space-y-5" onSubmit={onSubmit}>
      <div className="flex items-start gap-4">
        <span
          className="inline-grid size-10 place-items-center rounded-md bg-orange-500/10 text-orange-700"
          aria-hidden="true"
        >
          <KeyRound className="size-5" />
        </span>
        <div className="space-y-1">
          <h3 className="display text-base font-semibold tracking-tight">
            {t('settings.security.change_password.title')}
          </h3>
          <p className="max-w-xl text-sm text-muted-foreground">
            {t('settings.security.change_password.intro')}
          </p>
        </div>
      </div>

      <PasswordField
        id="current-password"
        label={t('settings.security.field_current')}
        value={currentPassword}
        onChange={setCurrentPassword}
        show={showCurrent}
        onToggle={() => setShowCurrent((v) => !v)}
        autoComplete="current-password"
      />

      <div className="space-y-2">
        <PasswordField
          id="new-password"
          label={t('settings.security.field_new')}
          value={newPassword}
          onChange={setNewPassword}
          show={showNew}
          onToggle={() => setShowNew((v) => !v)}
          autoComplete="new-password"
          describedBy="new-password-strength"
        />
        <StrengthMeter strength={strength} id="new-password-strength" />
        {tooShort ? (
          <p className="text-xs text-rose-600">
            {t('settings.security.error_too_short', { min: MIN_LENGTH })}
          </p>
        ) : null}
      </div>

      <PasswordField
        id="confirm-password"
        label={t('settings.security.field_confirm')}
        value={confirmPassword}
        onChange={setConfirmPassword}
        show={showNew}
        onToggle={() => setShowNew((v) => !v)}
        autoComplete="new-password"
        invalid={confirmMismatch}
        describedBy={confirmMismatch ? 'confirm-password-mismatch' : undefined}
      />
      {confirmMismatch ? (
        <p id="confirm-password-mismatch" className="text-xs text-rose-600">
          {t('settings.security.error_mismatch')}
        </p>
      ) : null}

      <Button type="submit" disabled={!canSubmit} className="gap-1.5">
        {submitting ? t('settings.security.submitting') : t('settings.security.submit')}
      </Button>
    </form>
  );
}

interface PasswordFieldProps {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  show: boolean;
  onToggle: () => void;
  autoComplete: string;
  invalid?: boolean;
  describedBy?: string;
}

function PasswordField({
  id,
  label,
  value,
  onChange,
  show,
  onToggle,
  autoComplete,
  invalid,
  describedBy,
}: PasswordFieldProps) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <div className="relative">
        <Input
          id={id}
          type={show ? 'text' : 'password'}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          autoComplete={autoComplete}
          aria-invalid={invalid}
          aria-describedby={describedBy}
          className="pr-9"
        />
        <button
          type="button"
          onClick={onToggle}
          aria-label={show ? 'Ukryj' : 'Pokaż'}
          className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
        >
          {show ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
        </button>
      </div>
    </div>
  );
}

function StrengthMeter({ strength, id }: { strength: Strength; id: string }) {
  const { t } = useTranslation();
  return (
    <div id={id} aria-live="polite" className="space-y-1">
      <div className="flex h-1.5 gap-1">
        {[0, 1, 2, 3].map((idx) => (
          <span
            key={idx}
            className={cn(
              'flex-1 rounded-full',
              idx < strength.score ? strength.colour : 'bg-zinc-200',
            )}
            aria-hidden="true"
          />
        ))}
      </div>
      <p className="text-xs text-muted-foreground">
        {t('settings.security.strength.label')}:{' '}
        <span className="font-medium">{strength.label}</span>
      </p>
    </div>
  );
}
