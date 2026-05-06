import * as React from 'react';

import { cn } from '@/lib/utils';

export interface StepperStep {
  id: string;
  label: string;
  description?: string;
}

interface StepperProps extends React.HTMLAttributes<HTMLDivElement> {
  steps: StepperStep[];
  currentStepIndex: number;
}

/**
 * Visual progress indicator for the 4-step import wizard (spec §5).
 *
 * Stays headless: no router awareness, no click handling — the parent
 * decides whether the user can jump back to a previous step. Pure
 * CSS, no extra deps; the bar uses tailwind tokens so it picks up
 * the design-system theme.
 */
export const Stepper = React.forwardRef<HTMLDivElement, StepperProps>(
  ({ steps, currentStepIndex, className, ...props }, ref) => {
    return (
      <div ref={ref} className={cn('w-full', className)} {...props}>
        <ol className="flex items-center" aria-label="Wizard progress">
          {steps.map((step, index) => {
            const isCompleted = index < currentStepIndex;
            const isActive = index === currentStepIndex;
            const isLast = index === steps.length - 1;

            return (
              <li
                key={step.id}
                className={cn('flex items-center', !isLast && 'flex-1')}
                aria-current={isActive ? 'step' : undefined}
              >
                <div className="flex flex-col items-center text-center">
                  <span
                    className={cn(
                      'flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-medium transition-colors',
                      isCompleted && 'border-primary bg-primary text-primary-foreground',
                      isActive && 'border-primary bg-primary/10 text-primary',
                      !isCompleted &&
                        !isActive &&
                        'border-muted-foreground/30 bg-background text-muted-foreground',
                    )}
                  >
                    {isCompleted ? '✓' : index + 1}
                  </span>
                  <span
                    className={cn(
                      'mt-1 text-xs font-medium',
                      isActive ? 'text-primary' : 'text-muted-foreground',
                    )}
                  >
                    {step.label}
                  </span>
                </div>
                {!isLast && (
                  <span
                    className={cn(
                      'mx-2 h-0.5 flex-1 transition-colors',
                      isCompleted ? 'bg-primary' : 'bg-muted-foreground/30',
                    )}
                    aria-hidden="true"
                  />
                )}
              </li>
            );
          })}
        </ol>
      </div>
    );
  },
);
Stepper.displayName = 'Stepper';
