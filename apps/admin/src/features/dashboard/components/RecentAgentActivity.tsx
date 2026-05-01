import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import { AGENT_ACTIVITY, type AgentStatus } from '../mock-data';

/**
 * MOCK component — recent agent activity (audit-style log).
 * Backend: GET /api/audit-log?actor=agent&limit=6 (do dorobienia).
 * Agent provenance dopiero w Fazie 2 per CLAUDE.md PIM.
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
const STATUS_BADGE: Record<AgentStatus, { label: string; className: string }> = {
  approved: { label: 'OK', className: 'bg-accent-emerald/10 text-accent-emerald' },
  rejected: { label: 'odrzucone', className: 'bg-accent-rose/10 text-accent-rose' },
  pending: { label: 'czeka', className: 'bg-accent-amber/10 text-accent-amber' },
};

export function RecentAgentActivity() {
  const { t } = useTranslation();
  return (
    <div className="rounded-2xl border border-line bg-surface soft-shadow">
      <div className="flex items-center justify-between border-b border-line px-5 py-4">
        <h3 className="text-[15px] font-semibold text-ink">
          {t('dashboard.agent_activity.title')}
        </h3>
        <span className="text-[12px] text-muted-foreground">
          {t('dashboard.agent_activity.subtitle')}
        </span>
      </div>
      <ul className="divide-y divide-line">
        {AGENT_ACTIVITY.map((row) => {
          const badge = STATUS_BADGE[row.status];
          return (
            <li key={row.id} className="px-5 py-3">
              <div className="flex items-start gap-3">
                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[11px] font-semibold text-ink-2">
                  {row.who
                    .split(' ')
                    .map((p) => p[0])
                    .join('')
                    .slice(0, 2)
                    .toUpperCase()}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2">
                    <span className="text-[13.5px] font-medium text-ink">{row.who}</span>
                    <span className="text-[11px] text-muted-foreground">{row.when}</span>
                    <span
                      className={cn(
                        'rounded-md px-1.5 py-0.5 text-[10.5px] font-medium uppercase tracking-wide',
                        badge.className,
                      )}
                    >
                      {badge.label}
                    </span>
                  </div>
                  <p className="mt-0.5 truncate text-[13px] text-ink-2">{row.title}</p>
                  <p className="mt-0.5 text-[11px] text-muted-foreground">
                    {row.hint} · {row.tools.join(', ')}
                  </p>
                </div>
              </div>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
