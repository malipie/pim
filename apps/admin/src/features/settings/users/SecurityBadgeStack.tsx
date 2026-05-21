import { AlertTriangle, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

export type MfaMethod = 'app_totp' | 'email_totp' | null;
export type SsoProvider = 'google' | 'microsoft' | 'saml' | null;

export interface SecurityBadgeStackProps {
  mfaEnabled: boolean;
  mfaMethod?: MfaMethod;
  mfaRequiredByRole?: boolean;
  sso?: SsoProvider;
}

const SSO_LABEL: Record<NonNullable<SsoProvider>, string> = {
  google: 'Google',
  microsoft: 'Microsoft',
  saml: 'SAML',
};

const SSO_STYLES: Record<NonNullable<SsoProvider>, string> = {
  google: 'bg-zinc-50 text-zinc-700 ring-zinc-200',
  microsoft: 'bg-blue-50 text-blue-700 ring-blue-200',
  saml: 'bg-violet-50 text-violet-700 ring-violet-200',
};

/**
 * Stacked MFA + SSO badges per `Zrodla/.../settings/users.jsx` §MFABadge +
 * §SSOBadge. Renders graceful fallbacks when backend doesn't yet expose
 * `mfa_method` / `sso` (boolean `mfa_enabled` from current
 * UserListResponseBuilder is enough for the green badge, just without the
 * `· App TOTP` / `· Email TOTP` suffix).
 */
export function SecurityBadgeStack({
  mfaEnabled,
  mfaMethod,
  mfaRequiredByRole,
  sso,
}: SecurityBadgeStackProps) {
  const { t } = useTranslation();

  const methodLabel =
    mfaMethod === 'app_totp' ? 'App TOTP' : mfaMethod === 'email_totp' ? 'Email TOTP' : null;

  return (
    <div className="flex flex-col gap-1">
      {mfaEnabled ? (
        <span className="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10.5px] font-medium text-emerald-700 ring-1 ring-emerald-200">
          <Lock className="size-2.5" aria-hidden />
          {methodLabel
            ? t('settings.users.mfa_with_method', {
                method: methodLabel,
                defaultValue: 'MFA · {{method}}',
              })
            : t('settings.users.mfa_enabled', { defaultValue: 'MFA' })}
        </span>
      ) : mfaRequiredByRole ? (
        <span
          className="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10.5px] font-medium text-rose-700 ring-1 ring-rose-200"
          title={t('settings.users.mfa_required_tooltip', {
            defaultValue:
              'MFA wymagane przez rolę — użytkownik musi włączyć przy następnym logowaniu',
          })}
        >
          <AlertTriangle className="size-2.5" aria-hidden />
          {t('settings.users.mfa_required', { defaultValue: 'MFA wymagane' })}
        </span>
      ) : (
        <span className="text-[10.5px] text-zinc-400">
          {t('settings.users.mfa_none', { defaultValue: 'brak MFA' })}
        </span>
      )}
      {sso ? (
        <span
          className={cn(
            'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10.5px] font-medium ring-1',
            SSO_STYLES[sso],
          )}
        >
          SSO · {SSO_LABEL[sso]}
        </span>
      ) : null}
    </div>
  );
}
