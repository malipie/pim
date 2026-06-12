import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface ModelingSectionProps {
  /** Eyebrow label (e.g. "BUILT-IN (SYSTEM)"). */
  label: string;
  /** Inline tagline shown next to the eyebrow. */
  tagline?: string;
  /** Right-aligned summary string, e.g. "3 typów · 29 instancji". */
  summary?: ReactNode;
  /** When true, renders the Lock icon next to the label. */
  locked?: boolean;
  /** Section content — typically a stack of `<ModelingRow>` separated by dividers. */
  children: ReactNode;
}

/**
 * UI-03c — wrapper that mirrors the BUILT-IN / CUSTOM section blocks
 * in the handoff. Adds a soft-shadow card around its children with a
 * dedicated header bar.
 */
export function ModelingSection({
  label,
  tagline,
  summary,
  locked,
  children,
}: ModelingSectionProps) {
  return (
    <section className="rounded-2xl border border-line bg-surface soft-shadow">
      <header className="flex flex-wrap items-center gap-2 border-b border-line/60 px-5 py-3 text-[12px] text-muted-foreground">
        <span className="inline-flex items-center gap-1 font-semibold uppercase tracking-wider text-ink-2">
          {locked ? <Lock className="size-3" /> : null}
          {label}
        </span>
        {tagline ? <span className="text-muted-foreground">— {tagline}</span> : null}
        {summary ? <span className="ml-auto text-muted-foreground">{summary}</span> : null}
      </header>
      <ul className="divide-y divide-line/60">{children}</ul>
    </section>
  );
}

interface ModelingRowProps {
  /** Optional `to` — when provided, the row is rendered as a clickable link wrapper. */
  to?: string;
  /** Click handler for non-link rows. */
  onClick?: () => void;
  /** Leading slot — typically a 48×48 coloured icon box. */
  leading: ReactNode;
  /** Primary text (display name). */
  title: ReactNode;
  /** Code label rendered beneath the title, monospace. */
  code?: ReactNode;
  /** Inline badges next to the title (e.g. system, variants, hierarchical). */
  badges?: ReactNode;
  /** Mid-column secondary text (e.g. English label). */
  secondaryLabel?: ReactNode;
  /** Trailing metadata column 1 (e.g. "5 grup atrybutów"). */
  metaPrimary?: ReactNode;
  /** Trailing metadata column 2 (e.g. "12 847 instancji"). */
  metaSecondary?: ReactNode;
  /** Override default chevron with a custom slot (e.g. action menu). */
  trailing?: ReactNode;
}

/**
 * UI-03c — single horizontal row inside a `<ModelingSection>`.
 *
 * Layout: [icon] [title + code + badges] [secondaryLabel] [metaPrimary] [metaSecondary] [chevron]
 *
 * Width hints follow the makieta:
 *  - icon column: 56 px
 *  - title column: flexible
 *  - secondary label: ~140 px
 *  - meta columns: ~140 px each, right-aligned
 *  - chevron: 24 px
 */
import { ChevronRight } from 'lucide-react';
import { Link } from 'react-router';

export function ModelingRow({
  to,
  onClick,
  leading,
  title,
  code,
  badges,
  secondaryLabel,
  metaPrimary,
  metaSecondary,
  trailing,
}: ModelingRowProps) {
  const content = (
    <div className="grid grid-cols-[56px_1fr_auto_auto_auto_24px] items-center gap-x-4 px-5 py-4">
      <div>{leading}</div>
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-[14.5px] font-semibold text-ink">{title}</span>
          {badges}
        </div>
        {code ? (
          <code className="mt-0.5 block truncate font-mono text-[11.5px] text-muted-foreground">
            {code}
          </code>
        ) : null}
      </div>
      <span className="text-[12.5px] text-muted-foreground">{secondaryLabel}</span>
      <span className="num text-right text-[12.5px] text-muted-foreground tabular-nums">
        {metaPrimary}
      </span>
      <span className="num text-right text-[12.5px] font-medium text-ink tabular-nums">
        {metaSecondary}
      </span>
      <span className="flex justify-end text-muted-foreground">
        {trailing ?? <ChevronRight className="size-4" />}
      </span>
    </div>
  );

  const baseClass = 'group block transition-colors hover:bg-surface-2/40';

  if (to) {
    return (
      <li>
        <Link to={to} className={cn(baseClass, 'cursor-pointer')}>
          {content}
        </Link>
      </li>
    );
  }
  if (onClick) {
    return (
      <li>
        <button type="button" onClick={onClick} className={cn(baseClass, 'w-full text-left')}>
          {content}
        </button>
      </li>
    );
  }
  return <li className={baseClass}>{content}</li>;
}
