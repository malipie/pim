import { useTranslation } from 'react-i18next';

import { ChangePasswordForm } from './ChangePasswordForm';
import { MfaSection } from './MfaSection';

/**
 * RBAC-P5-012 (#702) + RBAC-P5-013 (#703) — Settings → Security entry
 * point. Two sections: password change + MFA lifecycle (enrol /
 * disable / rotate recovery codes).
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
      <section className="rounded-lg border bg-background p-6 shadow-sm">
        <MfaSection />
      </section>
    </div>
  );
}
