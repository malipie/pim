import type * as React from 'react';
import { useTranslation } from 'react-i18next';

import { isStructuralImportKind, useImportWizard } from '@/features/imports/hooks/useImportWizard';

import { StepConfirmPlaceholder } from './StepConfirm';
import { StepDetect } from './StepDetect';
import { StepEntityType } from './StepEntityType';
import { StepMapping } from './StepMapping';
import { StepRules } from './StepRules';
import { StepSource } from './StepSource';
import { StepValidationPlaceholder } from './StepValidation';
import { type WizardStep, WizardStepper } from './WizardStepper';

/**
 * VIEW-IMP-05 (#504) → NUI-10 (#1429) — wizard host, now six steps per
 * the Import-nowy.html design: Źródło → Wykrywanie → Mapowanie → Reguły
 * → Podgląd → Start. Endpoints and payloads are identical to the 4-step
 * flow; Detect/Rules surface existing parse-preview data and the target
 * rules scope (mocked controls carry MockBadges).
 */
export function ImportWizardPage(): React.ReactElement {
  const { t } = useTranslation();
  const wizard = useImportWizard();
  const structural = isStructuralImportKind(wizard.state.entityType);

  const allSteps: ReadonlyArray<WizardStep> = [
    {
      id: 'entity',
      label: t('imports.wizard.steps.entity', { defaultValue: 'Dane' }),
      description: t('imports.wizard.descriptions.entity', {
        defaultValue: 'co importujesz',
      }),
    },
    {
      id: 'source',
      label: t('imports.wizard.steps.source', { defaultValue: 'Źródło' }),
      description: t('imports.wizard.descriptions.source', {
        defaultValue: 'skąd plik · CSV / XLSX / ZIP',
      }),
    },
    {
      id: 'detect',
      label: t('imports.wizard.steps.detect', { defaultValue: 'Wykrywanie' }),
      description: t('imports.wizard.descriptions.detect', {
        defaultValue: 'encoding / separator / arkusz',
      }),
    },
    {
      id: 'mapping',
      label: t('imports.wizard.steps.mapping', { defaultValue: 'Mapowanie' }),
      description: t('imports.wizard.descriptions.mapping', {
        defaultValue: 'kolumny → atrybuty + kategorie',
      }),
    },
    {
      id: 'rules',
      label: t('imports.wizard.steps.rules', { defaultValue: 'Reguły' }),
      description: t('imports.wizard.descriptions.rules', {
        defaultValue: 'tryb · walidacja',
      }),
    },
    {
      id: 'validation',
      label: t('imports.wizard.steps.validation', { defaultValue: 'Podgląd' }),
      description: t('imports.wizard.descriptions.validation', {
        defaultValue: 'dry-run, błędy walidacji',
      }),
    },
    {
      id: 'confirm',
      label: t('imports.wizard.steps.confirm', { defaultValue: 'Start' }),
      description: t('imports.wizard.descriptions.confirm', {
        defaultValue: 'backup + commit do bazy',
      }),
    },
  ];

  // Structural imports (attribute / attribute-group definitions) run a
  // simplified 4-step flow — mapping, rules and dry-run are meaningless for a
  // fixed-schema config import (the columns are the export's own headers).
  const steps: ReadonlyArray<WizardStep> = structural
    ? allSteps.filter((step) => !['mapping', 'rules', 'validation'].includes(step.id))
    : allSteps;
  const currentId = steps[wizard.state.step]?.id;

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
              'Każdy plik przechodzi przez 7 kroków: wybór danych, źródło, wykrywanie formatu, mapowanie kolumn, reguły, dry-run i commit do bazy. Po commicie sesja trafia do zakładki „Sesje" gdzie możesz ją wycofać w oknie 24h.',
          })}
        </p>
      </header>

      <WizardStepper steps={steps} currentIndex={wizard.state.step} />

      <section
        role="tabpanel"
        id={`wizard-step-${currentId ?? 'unknown'}`}
        aria-labelledby={`wizard-step-${currentId ?? 'unknown'}-label`}
      >
        {currentId === 'entity' && <StepEntityType wizard={wizard} />}
        {currentId === 'source' && <StepSource wizard={wizard} />}
        {currentId === 'detect' && <StepDetect wizard={wizard} />}
        {currentId === 'mapping' && <StepMapping wizard={wizard} />}
        {currentId === 'rules' && <StepRules wizard={wizard} />}
        {currentId === 'validation' && <StepValidationPlaceholder wizard={wizard} />}
        {currentId === 'confirm' && <StepConfirmPlaceholder wizard={wizard} />}
      </section>
    </div>
  );
}
