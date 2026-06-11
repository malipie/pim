import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

interface CompletenessRingProps {
  /** 0..100 */
  pct: number;
  /** Pixel size for width / height. Default 56. */
  size?: number;
  /** Stroke width. Default 5. */
  stroke?: number;
  className?: string;
}

/**
 * UI-03b detail-page completeness indicator (#366).
 *
 * Replaces the linear `<CompletenessBadge>` in the product show header
 * with an animated SVG ring. Color tracks the percent: rose 0–50,
 * amber 50–80, emerald 80–100. The stroke animates from 0 → target on
 * mount so the visual transition reads as "computing completeness".
 */
export function CompletenessRing({ pct, size = 56, stroke = 5, className }: CompletenessRingProps) {
  const clamped = Math.max(0, Math.min(100, Math.round(pct)));
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;
  const [animatedPct, setAnimatedPct] = useState(0);

  useEffect(() => {
    // Stroke animates from 0 → target via stroke-dashoffset transition.
    const timer = window.requestAnimationFrame(() => setAnimatedPct(clamped));
    return () => window.cancelAnimationFrame(timer);
  }, [clamped]);

  const dashOffset = circumference * (1 - animatedPct / 100);
  const color =
    clamped >= 80
      ? 'var(--color-accent-emerald)'
      : clamped >= 50
        ? 'var(--color-accent-amber)'
        : 'var(--color-accent-rose)';

  return (
    <div
      className={cn('relative inline-flex items-center justify-center', className)}
      style={{ width: size, height: size }}
      role="img"
      aria-label={`${clamped}%`}
    >
      <svg
        width={size}
        height={size}
        viewBox={`0 0 ${size} ${size}`}
        className="-rotate-90"
        aria-hidden
      >
        <title>Completeness {clamped}%</title>
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          stroke="var(--color-line, #ececea)"
          strokeWidth={stroke}
        />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          stroke={color}
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={circumference}
          strokeDashoffset={dashOffset}
          style={{ transition: 'stroke-dashoffset 600ms ease-out' }}
        />
      </svg>
      <span className="num absolute text-[12px] font-semibold text-ink">{clamped}%</span>
    </div>
  );
}
