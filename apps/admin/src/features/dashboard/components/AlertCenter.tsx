import { AlertTriangle, BellRing, Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import { ALERTS, type AlertSeverity } from '../mock-data';

/**
 * MOCK component — alert center.
 * Backend: GET /api/alerts?limit=5 (entity Alert nie istnieje, do projektowania).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
const SEVERITY_STYLE: Record<
  AlertSeverity,
  { icon: typeof Info; className: string; pill: string }
> = {
  err: {
    icon: AlertTriangle,
    className: 'text-accent-rose',
    pill: 'bg-accent-rose/10 text-accent-rose',
  },
  warn: {
    icon: BellRing,
    className: 'text-accent-amber',
    pill: 'bg-accent-amber/10 text-accent-amber',
  },
  info: { icon: Info, className: 'text-accent-blue', pill: 'bg-accent-blue/10 text-accent-blue' },
};

export function AlertCenter() {
  const { t } = useTranslation();
  return (
    <div className="rounded-2xl border border-line bg-surface soft-shadow">
      <div className="flex items-center justify-between border-b border-line px-5 py-4">
        <h3 className="text-[15px] font-semibold text-ink">{t('dashboard.alerts.title')}</h3>
        <span className="text-[12px] text-muted-foreground">{t('dashboard.alerts.subtitle')}</span>
      </div>
      <ul className="divide-y divide-line">
        {ALERTS.map((alert) => {
          const sev = SEVERITY_STYLE[alert.severity];
          const Icon = sev.icon;
          return (
            <li key={alert.id} className="flex items-start gap-3 px-5 py-3">
              <Icon className={cn('mt-0.5 size-4 shrink-0', sev.className)} />
              <div className="min-w-0 flex-1">
                <p className="text-[13.5px] font-medium text-ink">{alert.title}</p>
                <p className="mt-0.5 text-[11px] text-muted-foreground">
                  {alert.source} · {alert.when}
                </p>
              </div>
              {/* MOCK: alert CTA — wymaga GET /api/alerts/{id} + drill-down handlera (#TBD) */}
              <button
                type="button"
                disabled
                aria-disabled="true"
                className={cn(
                  'cursor-not-allowed shrink-0 rounded-md px-2 py-0.5 text-[11px] font-medium',
                  sev.pill,
                )}
              >
                {alert.cta}
              </button>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
