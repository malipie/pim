import { Check } from 'lucide-react';

import { cn } from '@/lib/utils';

export interface WizardStep {
  id: string;
  label: string;
  description: string;
}

interface WizardStepperProps {
  steps: ReadonlyArray<WizardStep>;
  currentIndex: number;
}

type StepState = 'done' | 'active' | 'pending';

function stateFor(currentIdx: number, idx: number): StepState {
  if (idx < currentIdx) {
    return 'done';
  }
  if (idx === currentIdx) {
    return 'active';
  }
  return 'pending';
}

/**
 * VIEW-IMP-05 (#504) — wizard top-strip stepper.
 *
 * Per-step pill with numbered circle (or ✓ once done), label, and a
 * short description line. Connectors between pills carry the
 * done/active state colour so the progress bar reads top-to-bottom.
 */
export function WizardStepper({ steps, currentIndex }: WizardStepperProps) {
  return (
    <ol className="flex items-stretch gap-2" aria-label="Wizard steps">
      {steps.map((step, i) => {
        const state = stateFor(currentIndex, i);
        return (
          <li key={step.id} className="flex items-stretch flex-1 min-w-0">
            <div
              className={cn(
                'flex-1 min-w-0 rounded-xl px-3 py-2.5 border transition',
                state === 'done' && 'bg-emerald-50/60 border-emerald-200/70',
                state === 'active' &&
                  'bg-white border-zinc-900/15 shadow-[0_1px_0_rgba(24,24,27,.04),0_8px_22px_-12px_rgba(24,24,27,.18)]',
                state === 'pending' && 'bg-zinc-50/60 border-zinc-100',
              )}
              aria-current={state === 'active' ? 'step' : undefined}
            >
              <div className="flex items-center gap-2">
                <div
                  className={cn(
                    'h-5 w-5 rounded-full grid place-items-center text-[10.5px] font-semibold shrink-0',
                    state === 'done' && 'bg-emerald-500 text-white',
                    state === 'active' && 'bg-zinc-900 text-white',
                    state === 'pending' && 'bg-zinc-200 text-zinc-600',
                  )}
                >
                  {state === 'done' ? <Check className="h-3 w-3" aria-hidden="true" /> : i + 1}
                </div>
                <div
                  className={cn(
                    'text-[13px] font-semibold tracking-tight truncate',
                    state === 'active' && 'text-zinc-900',
                    state === 'done' && 'text-emerald-800',
                    state === 'pending' && 'text-zinc-500',
                  )}
                >
                  {step.label}
                </div>
              </div>
              <div
                className={cn(
                  'text-[11.5px] mt-1 ml-7 truncate',
                  state === 'pending' ? 'text-zinc-500' : 'text-zinc-500',
                )}
              >
                {step.description}
              </div>
            </div>
            {i < steps.length - 1 ? (
              <div
                className={cn(
                  'flex items-center px-1',
                  state === 'done' ? 'text-emerald-400' : 'text-zinc-500',
                )}
                aria-hidden="true"
              >
                <svg
                  width="10"
                  height="10"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.5"
                  aria-hidden="true"
                  focusable="false"
                >
                  <title>step chevron</title>
                  <path d="m9 6 6 6-6 6" />
                </svg>
              </div>
            ) : null}
          </li>
        );
      })}
    </ol>
  );
}
