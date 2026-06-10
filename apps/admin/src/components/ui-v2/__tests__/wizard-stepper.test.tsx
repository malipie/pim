import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { type WizardStep, WizardStepper } from '../wizard-stepper';

const STEPS: WizardStep[] = [
  { id: 'type', label: 'Typ', hint: 'encja' },
  { id: 'scope', label: 'Zakres', hint: 'filtr + format' },
  { id: 'columns', label: 'Kolumny' },
  { id: 'summary', label: 'Podsumowanie' },
];

describe('WizardStepper', () => {
  it('marks the active step with aria-current', () => {
    render(<WizardStepper steps={STEPS} current={1} />);
    expect(screen.getByRole('button', { name: /Zakres/ })).toHaveAttribute('aria-current', 'step');
  });

  it('lets the user go back to a done step but not forward', async () => {
    const user = userEvent.setup();
    const onStepClick = vi.fn();
    render(<WizardStepper steps={STEPS} current={1} onStepClick={onStepClick} />);
    await user.click(screen.getByRole('button', { name: /Typ/ }));
    expect(onStepClick).toHaveBeenCalledWith(0);
    expect(screen.getByRole('button', { name: /Kolumny/ })).toBeDisabled();
  });
});
