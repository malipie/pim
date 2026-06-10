import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface EmptyStateProps {
  /** Icon rendered inside a muted rounded square. */
  icon?: ReactNode;
  /** Already-translated title. */
  title: string;
  /** Already-translated supporting copy. */
  description?: string;
  /** Call-to-action slot (button / link). */
  action?: ReactNode;
  className?: string;
}

/** Centered empty state ("W toku" with no running exports, empty tables). */
export function EmptyState({ icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div
      className={cn('flex flex-col items-center justify-center px-6 py-12 text-center', className)}
    >
      {icon && (
        <span
          aria-hidden="true"
          className="mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-zinc-100 text-zinc-500"
        >
          {icon}
        </span>
      )}
      <div className="text-[14px] font-semibold tracking-tight text-ink">{title}</div>
      {description && (
        <div className="mt-1 max-w-sm text-[12.5px] leading-relaxed text-zinc-500">
          {description}
        </div>
      )}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
