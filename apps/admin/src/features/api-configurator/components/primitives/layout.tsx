import { Shield } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * APIC-P0-05 — small layout primitives (field wrapper, security callout,
 * section label) for the Konfigurator API screens
 * (`integracje/api-primitives.jsx`).
 */
export function Field({
  label,
  hint,
  required = false,
  children,
}: {
  label: string;
  hint?: string;
  required?: boolean;
  children: ReactNode;
}) {
  return (
    <div>
      <div className="mb-1.5 flex items-center gap-1.5">
        <span className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
          {label}
        </span>
        {required ? <span className="text-[11px] text-rose-500">*</span> : null}
        {hint !== undefined && hint !== '' ? (
          <span className="text-[11px] normal-case tracking-normal text-zinc-500">· {hint}</span>
        ) : null}
      </div>
      {children}
    </div>
  );
}

type SecurityTone = 'emerald' | 'amber' | 'zinc';

const SECURITY_STYLES: Record<SecurityTone, { box: string; icon: string }> = {
  emerald: {
    box: 'bg-emerald-50/70 border-emerald-200 text-emerald-900',
    icon: 'text-emerald-600',
  },
  amber: { box: 'bg-amber-50/70 border-amber-200 text-amber-900', icon: 'text-amber-600' },
  zinc: { box: 'bg-zinc-50 border-zinc-200 text-zinc-700', icon: 'text-zinc-500' },
};

export function SecurityNote({
  children,
  tone = 'emerald',
  icon,
}: {
  children: ReactNode;
  tone?: SecurityTone;
  icon?: ReactNode;
}) {
  const meta = SECURITY_STYLES[tone];
  return (
    <div
      className={cn('flex items-start gap-2.5 rounded-xl border px-3 py-2.5 text-[12px]', meta.box)}
    >
      <span className={cn('mt-px shrink-0', meta.icon)} aria-hidden>
        {icon ?? <Shield className="size-4" />}
      </span>
      <div className="flex-1 leading-relaxed">{children}</div>
    </div>
  );
}

export function SectionLabel({ children, right }: { children: ReactNode; right?: ReactNode }) {
  return (
    <div className="mb-3 flex items-center gap-3">
      <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
        {children}
      </div>
      <div className="h-px flex-1 bg-zinc-100" />
      {right}
    </div>
  );
}
