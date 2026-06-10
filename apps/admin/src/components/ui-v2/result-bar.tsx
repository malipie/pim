import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

interface ResultBarProps {
  ok: number;
  warn: number;
  err: number;
  /** Defaults to ok + warn + err when omitted. */
  total?: number;
  width?: number;
  height?: number;
  /** Render the `✓ok ⚠warn ✗err` counter next to the bar. */
  showCounts?: boolean;
  className?: string;
}

/**
 * Horizontal OK/WARN/ERR distribution bar (emerald / orange / brick)
 * with an optional mono counter (design: ResultBar + session table cells).
 */
export function ResultBar({
  ok,
  warn,
  err,
  total,
  width = 140,
  height = 8,
  showCounts = false,
  className,
}: ResultBarProps) {
  const { t } = useTranslation();
  const sum = total ?? ok + warn + err;
  const safeSum = sum > 0 ? sum : 1;
  return (
    <div className={cn('flex items-center gap-2', className)}>
      <div
        role="img"
        aria-label={t('ui_v2.result_bar.aria', { ok, warn, err })}
        className="flex overflow-hidden rounded-full bg-zinc-100"
        style={{ width, height }}
      >
        <div className="bg-emerald-500" style={{ width: `${(ok / safeSum) * 100}%` }} />
        <div className="bg-orange-400" style={{ width: `${(warn / safeSum) * 100}%` }} />
        <div className="bg-brick-500" style={{ width: `${(err / safeSum) * 100}%` }} />
      </div>
      {showCounts && (
        <span aria-hidden="true" className="font-mono text-[11px] text-zinc-500">
          <span className="text-emerald-600">✓{ok}</span>{' '}
          <span className="text-orange-600">⚠{warn}</span>{' '}
          <span className="text-brick-600">✗{err}</span>
        </span>
      )}
    </div>
  );
}
