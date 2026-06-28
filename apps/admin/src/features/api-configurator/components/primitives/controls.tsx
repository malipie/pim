import { cn } from '@/lib/utils';

import type { SyncDirection } from './badges';

/**
 * APIC-P0-05 — interactive controls for the Konfigurator API screens
 * (`integracje/api-primitives.jsx`).
 */

const DIR_TOGGLE_ORDER: SyncDirection[] = ['inbound', 'bidirectional', 'outbound'];
const DIR_TOGGLE_META: Record<SyncDirection, { arrow: string; cls: string }> = {
  inbound: { arrow: '←', cls: 'text-sky-700 bg-sky-50 border-sky-200' },
  bidirectional: { arrow: '↔', cls: 'text-emerald-700 bg-emerald-50 border-emerald-200' },
  outbound: { arrow: '→', cls: 'text-violet-700 bg-violet-50 border-violet-200' },
};

export function DirToggle({
  value,
  onChange,
  title,
}: {
  value: SyncDirection;
  onChange: (next: SyncDirection) => void;
  title: string;
}) {
  const meta = DIR_TOGGLE_META[value];
  const cycle = () => {
    const next =
      DIR_TOGGLE_ORDER[(DIR_TOGGLE_ORDER.indexOf(value) + 1) % DIR_TOGGLE_ORDER.length] ??
      'inbound';
    onChange(next);
  };

  return (
    <button
      type="button"
      onClick={cycle}
      title={title}
      aria-label={title}
      className={cn(
        'focus-ring grid h-6 w-7 place-items-center rounded-md border font-mono text-[13px] leading-none transition hover:brightness-95',
        meta.cls,
      )}
    >
      {meta.arrow}
    </button>
  );
}

export interface SegmentedOption<T extends string> {
  value: T;
  label: string;
}

export function Segmented<T extends string>({
  options,
  value,
  onChange,
  size = 'md',
  full = false,
  ariaLabel,
}: {
  options: ReadonlyArray<SegmentedOption<T>>;
  value: T;
  onChange: (next: T) => void;
  size?: 'sm' | 'md';
  full?: boolean;
  ariaLabel: string;
}) {
  // A segmented toggle: each option is an aria-pressed button (self-labelled
  // by its text), so no container role is needed; `ariaLabel` becomes the
  // group's tooltip.
  return (
    <div
      title={ariaLabel}
      className={cn(
        'inline-flex items-center gap-0.5 rounded-xl bg-zinc-100 p-1',
        full && 'w-full',
      )}
    >
      {options.map((option) => {
        const active = option.value === value;
        return (
          <button
            key={option.value}
            type="button"
            aria-pressed={active}
            onClick={() => onChange(option.value)}
            className={cn(
              'whitespace-nowrap rounded-lg px-3 font-medium transition',
              size === 'sm' ? 'h-7 text-[11.5px]' : 'h-9 text-[12.5px]',
              full && 'flex-1',
              active ? 'soft-shadow bg-white text-zinc-900' : 'text-zinc-500 hover:text-zinc-900',
            )}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}

export function ApiToggle({
  on,
  onChange,
  ariaLabel,
}: {
  on: boolean;
  onChange: (next: boolean) => void;
  ariaLabel: string;
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={on}
      aria-label={ariaLabel}
      onClick={() => onChange(!on)}
      className={cn(
        'focus-ring relative h-5 w-9 shrink-0 rounded-full transition',
        on ? 'bg-zinc-900' : 'bg-zinc-200',
      )}
    >
      <span
        className={cn(
          'absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all',
          on ? 'left-[18px]' : 'left-0.5',
        )}
      />
    </button>
  );
}
