import { useQueryClient } from '@tanstack/react-query';
import { Eye, EyeOff, KeyRound } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { IDENTITY_QUERY_KEY } from '@/lib/identity';
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
 * Manual user creation (#867) — `/first-login-password` page that catches
 * users created via `POST /api/users` with `force_password_change: true`.
 *
 * Differs from {@link import('@/features/settings/security/ChangePasswordForm').ChangePasswordForm}
 * in two ways:
 *   - sits outside `<AuthedRoute>` shell (centred card layout, no
 *     sidebar / topbar) so the flow feels like the login page,
 *   - on success it invalidates the identity query and navigates to
 *     `/dashboard` instead of forcing logout — the JWT is still valid,
 *     backend cleared the flag, refetched /api/auth/me drops the
 *     redirect guard and the user lands on the workspace.
 *
 * The MIN_LENGTH constant + strength meter mirror ChangePasswordForm so
 * password policy stays in one place visually; the underlying endpoint
 * (`POST /api/me/change-password`) enforces the 12-character minimum on
 * the server.
 */
export function FirstLoginChangePasswordPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
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
      toast.success(
        t('auth.first_login_password.success_toast', { defaultValue: 'Hasło zmienione' }),
      );
      // Backend cleared `password_change_required` server-side; force the
      // identity query to re-fetch so AuthedRoute on /dashboard sees the
      // updated flag (the staleTime=5min cache would otherwise hold the
      // stale snapshot for the rest of the session).
      await queryClient.invalidateQueries({ queryKey: IDENTITY_QUERY_KEY });
      navigate('/dashboard', { replace: true });
    } catch (error: unknown) {
      const status = (error as { status?: number })?.status;
      if (status === 401) {
        toast.error(
          t('auth.first_login_password.error_wrong_password', {
            defaultValue: 'Nieprawidłowe obecne hasło',
          }),
        );
      } else if (status === 400) {
        toast.error(t('settings.security.error_too_short', { min: MIN_LENGTH }));
      } else {
        toast.error(
          t('auth.first_login_password.error_generic', {
            defaultValue: 'Nie udało się zmienić hasła',
          }),
        );
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-[#fafaf9] px-4 py-8">
      <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)]">
        <div className="mb-6 flex items-start gap-3">
          <span
            className="inline-grid size-10 shrink-0 place-items-center rounded-2xl bg-zinc-900 text-white"
            aria-hidden
          >
            <KeyRound className="size-5" />
          </span>
          <div className="space-y-1">
            <h1 className="text-[18px] font-semibold tracking-tight text-zinc-900">
              {t('auth.first_login_password.title', {
                defaultValue: 'Pierwsze logowanie — zmień hasło',
              })}
            </h1>
            <p className="text-[12.5px] text-zinc-500">
              {t('auth.first_login_password.intro', {
                defaultValue:
                  'Administrator ustawił dla Ciebie tymczasowe hasło. Wybierz nowe, zanim przejdziesz dalej.',
              })}
            </p>
          </div>
        </div>

        <form className="space-y-5" onSubmit={onSubmit}>
          <PasswordField
            id="first-current-password"
            label={t('settings.security.field_current')}
            value={currentPassword}
            onChange={setCurrentPassword}
            show={showCurrent}
            onToggle={() => setShowCurrent((v) => !v)}
            autoComplete="current-password"
          />

          <div className="space-y-2">
            <PasswordField
              id="first-new-password"
              label={t('settings.security.field_new')}
              value={newPassword}
              onChange={setNewPassword}
              show={showNew}
              onToggle={() => setShowNew((v) => !v)}
              autoComplete="new-password"
              describedBy="first-new-password-strength"
            />
            <StrengthMeter strength={strength} id="first-new-password-strength" />
            {tooShort ? (
              <p className="text-xs text-rose-600">
                {t('settings.security.error_too_short', { min: MIN_LENGTH })}
              </p>
            ) : null}
          </div>

          <PasswordField
            id="first-confirm-password"
            label={t('settings.security.field_confirm')}
            value={confirmPassword}
            onChange={setConfirmPassword}
            show={showNew}
            onToggle={() => setShowNew((v) => !v)}
            autoComplete="new-password"
            invalid={confirmMismatch}
            describedBy={confirmMismatch ? 'first-confirm-mismatch' : undefined}
          />
          {confirmMismatch ? (
            <p id="first-confirm-mismatch" className="text-xs text-rose-600">
              {t('settings.security.error_mismatch')}
            </p>
          ) : null}

          <Button
            type="submit"
            disabled={!canSubmit}
            className="w-full rounded-xl bg-zinc-900 text-white hover:bg-zinc-800"
          >
            {submitting
              ? t('auth.first_login_password.submitting', { defaultValue: 'Zmieniam...' })
              : t('auth.first_login_password.submit', {
                  defaultValue: 'Zmień hasło i wejdź',
                })}
          </Button>
        </form>
      </div>
    </div>
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
        {(['s0', 's1', 's2', 's3'] as const).map((slot, idx) => (
          <span
            key={slot}
            className={cn(
              'flex-1 rounded-full',
              idx < strength.score ? strength.colour : 'bg-zinc-200',
            )}
            aria-hidden
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
