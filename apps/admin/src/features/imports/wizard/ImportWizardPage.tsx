import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { useImportWizard } from '@/features/imports/hooks/useImportWizard';

import { StepConfirmPlaceholder } from './StepConfirm';
import { StepMapping } from './StepMapping';
import { StepUpload } from './StepUpload';
import { StepValidationPlaceholder } from './StepValidation';
import { type WizardStep, WizardStepper } from './WizardStepper';

/**
 * VIEW-IMP-05 (#504) — wizard host. Header eyebrow + WizardStepper +
 * step body. The stepper carries done/active/pending colours per
 * pill and a short description line so the operator knows where in
 * the flow they are without reading the body title.
 */
export function ImportWizardPage(): React.ReactElement {
  const { t } = useTranslation();
  const wizard = useImportWizard();

  // Honour the deep-link round-trip from `/modeling/attributes/new`:
  // the operator clicked "+ Stwórz atrybut" on Step 2, came back
  // here, we restore mapping state so the table picks up where
  // they left off.
  // biome-ignore lint/correctness/useExhaustiveDependencies: restore() only on mount
  React.useEffect(() => {
    wizard.restore();
  }, []);

  const steps: ReadonlyArray<WizardStep> = [
    {
      id: 'upload',
      label: t('imports.wizard.steps.upload', { defaultValue: 'Upload' }),
      description: t('imports.wizard.descriptions.upload', {
        defaultValue: 'CSV / XLSX + opcjonalny ZIP ze zdjęciami',
      }),
    },
    {
      id: 'mapping',
      label: t('imports.wizard.steps.mapping', { defaultValue: 'Mapping' }),
      description: t('imports.wizard.descriptions.mapping', {
        defaultValue: 'Kolumny → atrybuty + kategorie',
      }),
    },
    {
      id: 'validation',
      label: t('imports.wizard.steps.validation', { defaultValue: 'Walidacja' }),
      description: t('imports.wizard.descriptions.validation', {
        defaultValue: 'Dry-run, błędy walidacji',
      }),
    },
    {
      id: 'confirm',
      label: t('imports.wizard.steps.confirm', { defaultValue: 'Potwierdzenie' }),
      description: t('imports.wizard.descriptions.confirm', {
        defaultValue: 'Backup + commit do bazy',
      }),
    },
  ];

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <div className="text-[13px] text-zinc-500 font-medium">
          {t('imports.wizard.eyebrow', { defaultValue: 'Krok wizard — self-service import' })}
        </div>
        <h2 className="font-display text-[24px] font-semibold tracking-tight">
          {t('imports.wizard.title', { defaultValue: 'Nowy import' })}
        </h2>
        <p className="text-[13.5px] text-zinc-500 leading-relaxed max-w-3xl">
          {t('imports.wizard.subtitle', {
            defaultValue:
              'Każdy plik przechodzi przez 4 kroki: upload, mapowanie kolumn na atrybuty, walidacja dry-run, commit do bazy. Po commicie sesja trafia do zakładki „Sesje" gdzie możesz ją wycofać w oknie 24h.',
          })}
        </p>
      </header>

      <WizardStepper steps={steps} currentIndex={wizard.state.step} />

      <section
        role="tabpanel"
        id={`wizard-step-${steps[wizard.state.step]?.id ?? 'unknown'}`}
        aria-labelledby={`wizard-step-${steps[wizard.state.step]?.id ?? 'unknown'}-label`}
      >
        {wizard.state.step === 0 && <StepUpload wizard={wizard} />}
        {wizard.state.step === 1 && <StepMapping wizard={wizard} />}
        {wizard.state.step === 2 && <StepValidationPlaceholder wizard={wizard} />}
        {wizard.state.step === 3 && <StepConfirmPlaceholder wizard={wizard} />}
      </section>
    </div>
  );
}
