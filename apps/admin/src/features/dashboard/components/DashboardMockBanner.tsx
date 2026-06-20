import { AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * AUD-058 (#1610) — page-level honesty banner.
 *
 * The dashboard mixes live data (KPI entity totals, overall completeness) with
 * widgets that still render demo data because their backend does not exist yet
 * (agent activity — Faza 2; integration syncs — Faza 1; alerts; pgBackRest
 * status; activity history; channel distribution). The per-widget MockBadge is
 * easy to miss, so this explicit banner names exactly which blocks are
 * demonstrative. Remove it once every widget is wired to a real endpoint.
 */
export function DashboardMockBanner() {
  const { t } = useTranslation();

  return (
    <div
      role="status"
      className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 soft-shadow"
    >
      <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-600" aria-hidden />
      <div className="min-w-0 text-[13px] leading-relaxed">
        <p className="font-semibold">
          {t('dashboard.mock_banner.title', {
            defaultValue: 'Część widżetów pokazuje dane demonstracyjne',
          })}
        </p>
        <p className="mt-0.5 text-amber-800">
          {t('dashboard.mock_banner.body', {
            defaultValue:
              'Liczby KPI oraz kafelek „Kompletność” (ogólna) są na żywo z API. Pozostałe bloki — aktywność, status synchronizacji, alerty, aktywność agenta, backup, dystrybucja kanałów i ranking edytowanych — to makieta (funkcje w przygotowaniu).',
          })}
        </p>
      </div>
    </div>
  );
}
