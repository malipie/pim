import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { cn } from '@/lib/utils';

import { WIZARD_STEP_COUNT } from './types';
import { useWizard } from './wizard-store';

interface WizardFooterProps {
  /** Already-translated short title of the current step (mono line). */
  stepTitle: string;
  /** Step 1 validation gate — disables "Dalej" with a reason tooltip. */
  nextDisabled?: boolean;
  /** Called instead of plain step++ when the step needs custom advance. */
  onNext?: () => void;
}

/**
 * EXR-09 — wizard footer: mono `krok N z 4 · <title>` + Anuluj (confirm
 * when dirty) / Wstecz / Dalej (orange CTA).
 */
export function WizardFooter({ stepTitle, nextDisabled = false, onNext }: WizardFooterProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { state, dispatch } = useWizard();
  const last = state.step === WIZARD_STEP_COUNT - 1;

  const handleCancel = () => {
    if (state.dirty && !window.confirm(t('exports.wizard.cancel_confirm'))) {
      return;
    }
    void navigate('/integrations/exports/sessions');
  };

  return (
    <div className="flex items-center gap-3 border-t border-zinc-100 pt-4">
      <div className="num flex-1 font-mono text-[11.5px] text-zinc-400">
        {t('exports.wizard.step_indicator', {
          step: state.step + 1,
          total: WIZARD_STEP_COUNT,
          title: stepTitle,
        })}
      </div>
      <button
        type="button"
        onClick={handleCancel}
        className="focus-ring h-10 rounded-xl px-4 text-[13px] font-medium text-zinc-500 transition hover:bg-zinc-100 hover:text-ink"
      >
        {t('exports.wizard.cancel')}
      </button>
      <button
        type="button"
        disabled={state.step === 0}
        onClick={() => dispatch({ type: 'GO_TO_STEP', step: state.step - 1 })}
        className="focus-ring h-10 rounded-xl border border-zinc-200 bg-surface px-4 text-[13px] font-medium text-zinc-700 transition enabled:hover:border-zinc-400 disabled:cursor-not-allowed disabled:text-zinc-300"
      >
        {t('exports.wizard.back')}
      </button>
      {!last && (
        <button
          type="button"
          disabled={nextDisabled}
          onClick={() => {
            if (onNext) {
              onNext();
              return;
            }
            dispatch({ type: 'GO_TO_STEP', step: state.step + 1 });
          }}
          className={cn(
            'focus-ring h-10 rounded-xl px-5 text-[13px] font-semibold transition',
            nextDisabled
              ? 'cursor-not-allowed bg-zinc-100 text-zinc-400'
              : 'bg-cta text-cta-foreground hover:bg-accent-hover',
          )}
        >
          {t('exports.wizard.next')}
        </button>
      )}
    </div>
  );
}
