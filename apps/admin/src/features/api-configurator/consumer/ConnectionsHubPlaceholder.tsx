import { useTranslation } from 'react-i18next';

/**
 * APIC-P0-04 — empty placeholder for the consumer connections hub. Replaced by
 * the live ConnectionCard grid in APIC-P1-07 (consumes `/api/connections`).
 */
export function ConnectionsHubPlaceholder() {
  const { t } = useTranslation();
  return (
    <div className="rounded-2xl border border-dashed border-zinc-200 bg-white p-10 text-center">
      <h2 className="text-[15px] font-semibold tracking-tight">
        {t('api_configurator.shell.connections_soon_title')}
      </h2>
      <p className="mx-auto mt-1 max-w-md text-[13px] text-zinc-500">
        {t('api_configurator.shell.connections_soon_desc')}
      </p>
    </div>
  );
}
