import type { ReactNode } from 'react';
import { Link } from 'react-router';

import { cn } from '@/lib/utils';

export interface BreadcrumbItem {
  /** Already-translated segment label. */
  label: string;
  /** Route target; the last segment usually has none (current page). */
  href?: string;
}

interface PageHeaderProps {
  /** Breadcrumb segments, e.g. Workspace / Integracje / Eksporty. */
  items: BreadcrumbItem[];
  /** Right-hand slot: page CTA + fixed icon actions (lang, history, bell). */
  actions?: ReactNode;
  className?: string;
}

/**
 * Topbar page header (design Topbar): breadcrumb where intermediate
 * segments are links (zinc-400) and the last one is bold ink, plus an
 * actions slot on the right. Mounted globally by the shell (EXR-03).
 */
export function PageHeader({ items, actions, className }: PageHeaderProps) {
  return (
    <div className={cn('flex h-[68px] items-center gap-4 px-10', className)}>
      <nav aria-label="breadcrumb" className="min-w-0 flex-1">
        <ol className="font-display flex items-center truncate text-[22px] font-semibold tracking-tight">
          {items.map((item, index) => {
            const last = index === items.length - 1;
            return (
              <li key={`${item.href ?? ''}|${item.label}`} className="flex min-w-0 items-center">
                {index > 0 && (
                  <span aria-hidden="true" className="mx-2 text-zinc-300">
                    /
                  </span>
                )}
                {item.href && !last ? (
                  <Link
                    to={item.href}
                    className="focus-ring truncate rounded-md text-zinc-400 hover:text-ink"
                  >
                    {item.label}
                  </Link>
                ) : (
                  <span
                    aria-current={last ? 'page' : undefined}
                    className={cn('truncate', last ? 'text-ink' : 'text-zinc-400')}
                  >
                    {item.label}
                  </span>
                )}
              </li>
            );
          })}
        </ol>
      </nav>
      {actions && <div className="flex shrink-0 items-center gap-1.5">{actions}</div>}
    </div>
  );
}
