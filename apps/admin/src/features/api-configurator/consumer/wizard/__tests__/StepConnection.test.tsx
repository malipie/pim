import { fireEvent, render, screen } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import { describe, expect, it, vi } from 'vitest';

import { StepConnection } from '../StepConnection';
import { INITIAL_FORM, type WizardForm } from '../types';

expect.extend(toHaveNoViolations);

function renderStep(overrides: Partial<WizardForm> = {}) {
  const set = vi.fn();
  const form = { ...INITIAL_FORM, ...overrides };
  const utils = render(<StepConnection form={form} set={set} />);
  return { ...utils, set };
}

describe('StepConnection', () => {
  it('derives the code slug from the name', () => {
    const { set } = renderStep();
    fireEvent.change(screen.getByPlaceholderText('np. Nexar Components'), {
      target: { value: 'Acme EU!' },
    });
    expect(set).toHaveBeenCalledWith({ name: 'Acme EU!', code: 'acme-eu' });
  });

  it('shows the api_key credential fields by default', () => {
    renderStep();
    expect(screen.getByText('Nagłówek')).toBeInTheDocument();
    expect(screen.getByText('Wartość klucza')).toBeInTheDocument();
  });

  it('shows basic-auth fields when that scheme is selected', () => {
    renderStep({ authType: 'basic' });
    expect(screen.getByText('Użytkownik')).toBeInTheDocument();
    expect(screen.getByText('Hasło')).toBeInTheDocument();
  });

  it('has no axe violations', async () => {
    const { container } = renderStep();
    expect(await axe(container)).toHaveNoViolations();
  });
});
