import { useTranslation } from 'react-i18next';

/**
 * UI-03c — application footer rendered below the main outlet.
 *
 * Surfaces workspace identity + active ADRs on the left, app version +
 * model schema rev on the right. All strings are MOCK until backend
 * endpoints ship for tenant identity, ADR registry and schema_revision.
 */
export function AppFooter() {
  const { t } = useTranslation();

  return (
    <footer className="border-t border-line/60 bg-background px-6 py-3 text-[11px] text-muted-foreground">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span>
          {t('footer.workspace_label', {
            defaultValue: 'Pim · workspace „Klimas Sp. z o.o."',
          })}
          <span aria-hidden> · </span>
          {t('footer.adrs', {
            defaultValue: 'ADR-009 · proponowany ADR-012 (Attribute Group as first-class)',
          })}
        </span>
        <span className="num">
          {t('footer.version', { defaultValue: 'v1.0.0-rc.4' })}
          <span aria-hidden> · </span>
          {t('footer.schema_rev', { defaultValue: 'model schema rev 47' })}
        </span>
      </div>
    </footer>
  );
}
