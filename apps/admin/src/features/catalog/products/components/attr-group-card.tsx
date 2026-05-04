import { ChevronRight, Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface AttrGroupCardProps {
  id: string;
  title: string;
  icon?: ReactNode;
  filledCount: number;
  totalCount: number;
  expanded: boolean;
  onToggle: () => void;
  isSystem?: boolean;
  children: ReactNode;
}

/**
 * VIEW-07 (#420) — collapsible group card mirrored from
 * `detail-view.jsx` lines 63–94. Header shows icon + title + system
 * badge + filled progress + chevron; body holds an arbitrary stack of
 * `<AttrRow>` components (or anything else when reused for variants).
 */
export function AttrGroupCard({
  id,
  title,
  icon,
  filledCount,
  totalCount,
  expanded,
  onToggle,
  isSystem = false,
  children,
}: AttrGroupCardProps) {
  const { t } = useTranslation();
  const safeTotal = Math.max(1, totalCount);
  const pct = Math.round((filledCount / safeTotal) * 100);

  return (
    <Card
      id={`group-${id}`}
      className="overflow-hidden rounded-2xl border-line bg-surface soft-shadow"
    >
      <button
        type="button"
        onClick={onToggle}
        className="flex w-full items-center gap-3 px-5 py-4 text-left hover:bg-zinc-50/60"
        aria-expanded={expanded}
        aria-controls={`group-body-${id}`}
      >
        <span
          className="grid size-9 place-items-center rounded-xl bg-zinc-100 text-[16px]"
          aria-hidden
        >
          {icon ?? defaultIcon(title)}
        </span>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 text-[14px] font-semibold tracking-tight">
            <span className="truncate">{title}</span>
            {isSystem ? (
              <span className="inline-flex items-center gap-1 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium text-zinc-500">
                <Lock className="size-3 text-zinc-400" aria-hidden />
                {t('products.detail.group.system', { defaultValue: 'system' })}
              </span>
            ) : null}
          </div>
          <div className="num mt-0.5 text-[11.5px] text-zinc-500">
            {t('products.detail.group.filled', {
              filled: filledCount,
              total: totalCount,
              pct,
              defaultValue: '{{filled}} / {{total}} wypełnione · {{pct}}%',
            })}
          </div>
        </div>
        <div className="h-1 w-20 rounded-full bg-zinc-100">
          <div
            className="h-full rounded-full bg-zinc-900 transition-all"
            style={{ width: `${pct}%` }}
            aria-hidden
          />
        </div>
        <ChevronRight
          className={cn('size-4 text-zinc-400 transition-transform', expanded && 'rotate-90')}
          aria-hidden
        />
      </button>
      {expanded ? (
        <div id={`group-body-${id}`} className="border-t border-zinc-100 bg-zinc-50/40 p-2">
          {children}
        </div>
      ) : null}
    </Card>
  );
}

function defaultIcon(title: string): ReactNode {
  return <span className="text-zinc-500">{title.charAt(0).toUpperCase()}</span>;
}
