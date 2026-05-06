import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Stepper } from '@/components/ui/stepper';
import { useImportWizard } from '@/features/imports/hooks/useImportWizard';

import { StepConfirmPlaceholder } from './StepConfirm';
import { StepMapping } from './StepMapping';
import { StepUpload } from './StepUpload';
import { StepValidationPlaceholder } from './StepValidation';

/**
 * IMP-10 (#451) — wizard host. Renders the IMP-08 stepper + the
 * current step body. Step 3 + Step 4 ship as placeholders so the
 * shell flows even though IMP-11 owns their full implementation.
 */
export function ImportWizardPage(): React.ReactElement {
  const { t } = useTranslation();
  const wizard = useImportWizard();

  // Honour the deep-link round-trip from `/modeling/attributes/new`:
  // the operator clicked "+ Stwórz atrybut" on Step 2, came back
  // here, we restore mapping state so the table picks up where
  // they left off.
  React.useEffect(() => {
    wizard.restore();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const steps = [
    { id: 'upload', label: t('imports.wizard.steps.upload', { defaultValue: 'Upload' }) },
    { id: 'mapping', label: t('imports.wizard.steps.mapping', { defaultValue: 'Mapping' }) },
    {
      id: 'validation',
      label: t('imports.wizard.steps.validation', { defaultValue: 'Walidacja' }),
    },
    { id: 'confirm', label: t('imports.wizard.steps.confirm', { defaultValue: 'Potwierdzenie' }) },
  ];

  return (
    <div className="space-y-6">
      <Stepper steps={steps} currentStepIndex={wizard.state.step} />

      {wizard.state.step === 0 && <StepUpload wizard={wizard} />}
      {wizard.state.step === 1 && <StepMapping wizard={wizard} />}
      {wizard.state.step === 2 && <StepValidationPlaceholder wizard={wizard} />}
      {wizard.state.step === 3 && <StepConfirmPlaceholder wizard={wizard} />}
    </div>
  );
}
