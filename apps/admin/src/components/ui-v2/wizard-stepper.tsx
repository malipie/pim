import { Check } from 'lucide-react';

import { cn } from '@/lib/utils';

export interface WizardStep {
  id: string;
  /** Already-translated step title. */
  label: string;
  /** Already-translated 11px subtitle. */
  hint?: string;
}

interface WizardStepperProps {
  steps: WizardStep[];
  /** Index of the active step (0-based). */
  current: number;
  /**
   * Called when the user clicks a DONE step (going back). Confirmation on
   * dirty state is the wizard's responsibility (EXR-09), not the stepper's.
   */
  onStepClick?: (index: number) => void;
  className?: string;
}

/**
 * 4-step wizard bar (design Stepper): done = emerald tile with check,
 * active = navy tile, future = white/zinc. Only done steps are clickable.
 */
export function WizardStepper({ steps, current, onStepClick, className }: WizardStepperProps) {
  return (
    <ol className={cn('flex items-stretch gap-1.5', className)}>
      {steps.map((step, index) => {
        const active = index === current;
        const done = index < current;
        return (
          <li key={step.id} className="min-w-0 flex-1">
            <button
              type="button"
              disabled={!done}
              aria-current={active ? 'step' : undefined}
              onClick={() => {
                if (done) {
                  onStepClick?.(index);
                }
              }}
              className={cn(
                'focus-ring w-full rounded-xl border px-3.5 py-3 text-left transition',
                active && 'border-zinc-900 bg-zinc-900 text-white',
                done && 'cursor-pointer border-emerald-200/70 bg-emerald-50/60 text-emerald-900',
                !active && !done && 'cursor-default border-zinc-200 bg-surface text-zinc-500',
              )}
            >
              <span className="flex items-center gap-2">
                <span
                  aria-hidden="true"
                  className={cn(
                    'grid h-5 w-5 place-items-center rounded-full font-mono text-[10px] font-bold',
                    active && 'bg-white text-zinc-900',
                    done && 'bg-emerald-500 text-white',
                    !active && !done && 'bg-zinc-100 text-zinc-400',
                  )}
                >
                  {done ? <Check size={11} strokeWidth={3} /> : index + 1}
                </span>
                <span className="truncate text-[12.5px] font-semibold">{step.label}</span>
              </span>
              {step.hint && (
                <span
                  className={cn(
                    'mt-1 block truncate text-[11px]',
                    active ? 'text-white/70' : done ? 'text-emerald-700' : 'text-zinc-400',
                  )}
                >
                  {step.hint}
                </span>
              )}
            </button>
          </li>
        );
      })}
    </ol>
  );
}
