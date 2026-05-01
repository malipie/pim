import { useTranslation } from 'react-i18next';

/**
 * UI-02.10 (#300) — flat completeness percentage rendered as a coloured
 * progress bar plus the numeric label. Colour coding matches the brief
 * in `Project Plan/UI/epik-02-produkty.md` §4.5: red <50, yellow
 * 50-90, green >90.
 */
export function CompletenessBadge({
  pct,
  totalAttributes,
  filledAttributes,
}: {
  pct: number;
  totalAttributes?: number;
  filledAttributes?: number;
}) {
  const { t } = useTranslation();
  const safe = Math.max(0, Math.min(100, Math.round(pct)));
  const tone = safe >= 90 ? 'bg-emerald-500' : safe >= 50 ? 'bg-amber-500' : 'bg-rose-500';

  const tooltip =
    totalAttributes !== undefined && filledAttributes !== undefined
      ? t('products.completeness.tooltip', {
          pct: safe,
          filled: filledAttributes,
          total: totalAttributes,
          defaultValue: '{{pct}}% — wypełnione {{filled}}/{{total}} atrybutów',
        })
      : `${safe}%`;

  return (
    <div className="flex items-center gap-2" title={tooltip}>
      <meter
        min={0}
        max={100}
        low={50}
        high={90}
        optimum={95}
        value={safe}
        aria-label={`${safe}%`}
        className="h-2 w-20 overflow-hidden rounded-full bg-muted"
      >
        <div className={`h-full ${tone}`} style={{ width: `${safe}%` }} />
      </meter>
      <span className="text-xs tabular-nums text-muted-foreground">{safe}%</span>
    </div>
  );
}
