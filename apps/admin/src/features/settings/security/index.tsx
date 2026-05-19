import { useTranslation } from 'react-i18next';

import { ChangePasswordForm } from './ChangePasswordForm';

/**
 * RBAC-P5-012 (#702) — Settings → Security entry point.
 *
 * Wave 1 ships password change; MFA enable/disable (#703) docks here
 * once the MFA wizard (#689) is in place.
 */
export function SecuritySettingsPage() {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h2 className="display text-xl font-semibold tracking-tight">
          {t('settings.security.title')}
        </h2>
        <p className="max-w-2xl text-sm text-muted-foreground">{t('settings.security.intro')}</p>
      </header>
      <section className="rounded-lg border bg-background p-6 shadow-sm">
        <ChangePasswordForm />
      </section>
    </div>
  );
}
