import { Fragment, type ReactNode } from 'react';

import { cn } from '@/lib/utils';

const TOKEN =
  /("(?:\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(?:\s*:)?|\b(?:true|false)\b|\bnull\b|-?\d+(?:\.\d*)?(?:[eE][+-]?\d+)?)/g;

function classFor(token: string): string {
  if (token.startsWith('"')) {
    return /:\s*$/.test(token) ? 'text-zinc-500' : 'text-emerald-700';
  }
  if (token === 'true' || token === 'false') {
    return 'text-violet-700';
  }
  if (token === 'null') {
    return 'text-zinc-400';
  }
  return 'text-sky-700';
}

/**
 * APIC-P0-05 — lightweight JSON viewer with syntax tinting
 * (`integracje/api-primitives.jsx`). Tokenises into React spans rather than
 * injecting HTML, so it stays XSS-safe (no `dangerouslySetInnerHTML`).
 */
export function JsonView({
  value,
  className,
  maxHeight,
}: {
  value: unknown;
  className?: string;
  maxHeight?: number;
}) {
  const json = typeof value === 'string' ? value : JSON.stringify(value, null, 2);

  const parts: ReactNode[] = [];
  let lastIndex = 0;
  let key = 0;
  for (const match of json.matchAll(TOKEN)) {
    const start = match.index;
    if (start > lastIndex) {
      parts.push(<Fragment key={key++}>{json.slice(lastIndex, start)}</Fragment>);
    }
    const token = match[0];
    parts.push(
      <span key={key++} className={classFor(token)}>
        {token}
      </span>,
    );
    lastIndex = start + token.length;
  }
  if (lastIndex < json.length) {
    parts.push(<Fragment key={key++}>{json.slice(lastIndex)}</Fragment>);
  }

  return (
    <pre
      className={cn(
        'scrollbar-thin overflow-auto whitespace-pre font-mono text-[11.5px] leading-relaxed text-zinc-700',
        className,
      )}
      style={maxHeight !== undefined ? { maxHeight } : undefined}
    >
      {parts}
    </pre>
  );
}
