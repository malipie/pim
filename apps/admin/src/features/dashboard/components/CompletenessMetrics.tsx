import { useTranslation } from 'react-i18next';

import { COMPLETENESS } from '../mock-data';

/**
 * MOCK component — completeness rings (overall + 4 channels).
 * Backend: GET /api/dashboard/completeness (do dorobienia).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
function Ring({ percent }: { percent: number }) {
  const r = 28;
  const c = 2 * Math.PI * r;
  const dash = (percent / 100) * c;
  return (
    <svg viewBox="0 0 64 64" className="size-16" aria-hidden="true">
      <circle cx="32" cy="32" r={r} fill="none" stroke="#ececea" strokeWidth="6" />
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
      <text
        x="32"
        y="36"
        textAnchor="middle"
        fontSize="13"
        fontWeight="600"
        fill="#18181b"
        style={{ fontFamily: 'Inter', fontFeatureSettings: '"tnum"' }}
      >
        {percent}%
      </text>
    </svg>
  );
}

export function CompletenessMetrics() {
  const { t } = useTranslation();
  return (
    <div className="rounded-2xl border border-line bg-surface p-5 soft-shadow">
      <h3 className="text-[15px] font-semibold text-ink">{t('dashboard.completeness.title')}</h3>
      <p className="mt-1 text-[12px] text-muted-foreground">
        {t('dashboard.completeness.subtitle')}
      </p>
      <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        {COMPLETENESS.map((c) => (
          <div key={c.key} className="flex flex-col items-center gap-2 text-center">
            <Ring percent={c.percent} />
            <span className="text-[12px] font-medium text-ink-2">{c.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
