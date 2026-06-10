import { useTranslation } from 'react-i18next';

import { WizardStepper } from '@/components/ui-v2/wizard-stepper';
import { StepColumns } from './steps/StepColumns';
import { StepEntityType } from './steps/StepEntityType';
import { StepScopeFormat } from './steps/StepScopeFormat';
import { WizardFooter } from './WizardFooter';
import { useWizard, WizardProvider } from './wizard-store';

/**
 * EXR-09 (#1385) — full-page 4-step export wizard at
 * /integrations/exports/new (screen 2). This ticket ships the shell,
 * the store and step 1; steps 2-4 render placeholders until
 * EXR-10/11/12 fill them in.
 */
export function ExportWizardPage(): React.ReactElement {
  return (
    <WizardProvider>
      <WizardContent />
    </WizardProvider>
  );
}

function WizardContent() {
  const { t } = useTranslation();
  const { state, dispatch } = useWizard();

  const steps = [
    {
      id: 'type',
      label: t('exports.wizard.steps.type'),
      hint: t('exports.wizard.steps.type_hint'),
    },
    {
      id: 'scope',
      label: t('exports.wizard.steps.scope'),
      hint: t('exports.wizard.steps.scope_hint'),
    },
    {
      id: 'columns',
      label: t('exports.wizard.steps.columns'),
      hint: t('exports.wizard.steps.columns_hint'),
    },
    {
      id: 'summary',
      label: t('exports.wizard.steps.summary'),
      hint: t('exports.wizard.steps.summary_hint'),
    },
  ];

  const stepTitles = [
    t('exports.wizard.steps.type'),
    t('exports.wizard.steps.scope'),
    t('exports.wizard.steps.columns'),
    t('exports.wizard.steps.summary'),
  ];

  const step1Valid = state.entityType !== 'custom_module' || state.objectTypeId !== null;
  // EXR-10: soft-cap gate — Dalej blocked while the configuration would
  // exceed the 100k export cap (count=0 stays allowed: headers-only file).
  const step2Valid = state.preflight?.exceeds_cap !== true;
  // EXR-11: at least one column required to proceed to the summary.
  const step3Valid = state.columns.length > 0;

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <header>
        <h1 className="display text-[24px] font-semibold tracking-tight text-ink">
          {t('exports.wizard.title')}
        </h1>
        <p className="mt-1 text-[13px] text-zinc-500">{t('exports.wizard.lead')}</p>
      </header>

      <WizardStepper
        steps={steps}
        current={state.step}
        onStepClick={(step) => dispatch({ type: 'GO_TO_STEP', step })}
      />

      <div key={state.step}>
        {state.step === 0 && <StepEntityType />}
        {state.step === 1 && <StepScopeFormat />}
        {state.step === 2 && <StepColumns />}
        {state.step > 2 && (
          <div className="rounded-2xl border border-dashed border-zinc-200 bg-surface p-10 text-center text-[13px] text-zinc-400">
            {t('exports.wizard.step_placeholder')}
          </div>
        )}
      </div>

      <WizardFooter
        stepTitle={stepTitles[state.step] ?? ''}
        nextDisabled={
          (state.step === 0 && !step1Valid) ||
          (state.step === 1 && !step2Valid) ||
          (state.step === 2 && !step3Valid)
        }
      />
    </div>
  );
}

export default ExportWizardPage;
