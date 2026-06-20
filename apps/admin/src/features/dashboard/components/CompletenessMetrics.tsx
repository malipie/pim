import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';

import { COMPLETENESS } from '../mock-data';
import { PUBLISH_READY_THRESHOLD, useDashboardCompleteness } from '../use-dashboard-completeness';

/**
 * Completeness rings. The OVERALL ring is LIVE (AUD-058 #1610): a real
 * publish-readiness share derived from the indexed `completeness[gte]=N`
 * product filter. The per-channel rings stay mock — channel-aware
 * completeness is parked until ChannelObjectTypeMapping reads land (epic 0.6)
 * — and are flagged with their own MockBadge under the dashboard banner.
 */
function Ring({ percent, pending = false }: { percent: number; pending?: boolean }) {
  const r = 28;
  const c = 2 * Math.PI * r;
  const dash = (percent / 100) * c;
  return (
    <svg viewBox="0 0 64 64" className="size-16" aria-hidden="true">
      <circle cx="32" cy="32" r={r} fill="none" stroke="#ececea" strokeWidth="6" />
      {!pending && (
        <circle
          cx="32"
          cy="32"
          r={r}
          fill="none"
          stroke="#10b981"
          strokeWidth="6"
          strokeLinecap="round"
          strokeDasharray={`${dash} ${c}`}
          transform="rotate(-90 32 32)"
        />
      )}
      <text
        x="32"
        y="36"
        textAnchor="middle"
        fontSize="13"
        fontWeight="600"
        fill="#18181b"
        style={{ fontFamily: 'Inter', fontFeatureSettings: '"tnum"' }}
      >
        {pending ? '…' : `${percent}%`}
      </text>
    </svg>
  );
}

const numberFormatter = new Intl.NumberFormat('pl-PL');

export function CompletenessMetrics() {
  const { t } = useTranslation();
  const { data, isPending } = useDashboardCompleteness();

  // Live overall ring when the count query succeeds; otherwise fall back to the
  // mock overall slice so the widget never renders blank.
  const overallMock = COMPLETENESS.find((c) => c.key === 'overall');
  const overallPercent = data ? data.publishReadyPct : (overallMock?.percent ?? 0);
  const overallIsLive = Boolean(data);
  const channelSlices = COMPLETENESS.filter((c) => c.key !== 'overall');

  return (
    <div className="relative rounded-2xl border border-line bg-surface p-5 soft-shadow">
      <h3 className="text-[15px] font-semibold text-ink">{t('dashboard.completeness.title')}</h3>
      <p className="mt-1 text-[12px] text-muted-foreground">
        {t('dashboard.completeness.subtitle')}
      </p>
      <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        {/* Overall — LIVE */}
        <div className="flex flex-col items-center gap-2 text-center">
          <Ring percent={overallPercent} pending={isPending && !overallIsLive} />
          <span className="text-[12px] font-medium text-ink-2">
            {t('dashboard.completeness.overall', { defaultValue: 'Ogólna' })}
          </span>
          {overallIsLive && data ? (
            <span className="num text-[11px] text-muted-foreground">
              {t('dashboard.completeness.publish_ready', {
                defaultValue: '{{count}} z {{total}} ≥ {{threshold}}%',
                count: numberFormatter.format(data.publishReady),
                total: numberFormatter.format(data.total),
                threshold: PUBLISH_READY_THRESHOLD,
              })}
            </span>
          ) : null}
        </div>

        {/* Per-channel — MOCK (channel-aware completeness lands in epic 0.6) */}
        {channelSlices.map((c) => (
          <div key={c.key} className="relative flex flex-col items-center gap-2 text-center">
            <span className="absolute -right-1 -top-1 z-10">
              <MockBadge
                tooltip={t('dashboard.completeness.channel_mock_tooltip', {
                  defaultValue:
                    'MOCK — kompletność per kanał wymaga mapowania kanał↔typ (epik 0.6)',
                })}
              />
            </span>
            <Ring percent={c.percent} />
            <span className="text-[12px] font-medium text-ink-2">{c.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
