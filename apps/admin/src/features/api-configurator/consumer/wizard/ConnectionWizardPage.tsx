import { useCreate, useUpdate } from '@refinedev/core';
import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import { StepConnection } from './StepConnection';
import { StepTest } from './StepTest';
import { StepEndpoints } from './steps/StepEndpoints';
import { StepSchema } from './steps/StepSchema';
import { INITIAL_FORM, toConnectionInput, type WizardForm } from './types';

const HUB = '/integrations/api-configurator/connections';

type StepState = 'done' | 'active' | 'pending';

/**
 * APIC-P1-08 / P2-06 — the consumer connection wizard. Step 1 defines the
 * connection, step 2 tests it, step 3 builds the endpoint descriptors, step 4
 * discovers the schema and accepts fields. The connection is persisted as a
 * draft on leaving step 1 so the later steps have an id to attach to, and
 * re-edits PATCH that draft.
 */
export function ConnectionWizardPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { mutate: create } = useCreate();
  const { mutate: update } = useUpdate();

  const [step, setStep] = useState(0);
  const [form, setForm] = useState<WizardForm>(INITIAL_FORM);
  const [connectionId, setConnectionId] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const set = (patch: Partial<WizardForm>): void => setForm((f) => ({ ...f, ...patch }));

  const steps = [
    { id: 'conn', label: t('api_configurator.wizard.step_conn'), note: 'URL + auth' },
    { id: 'test', label: t('api_configurator.wizard.step_test'), note: 'health + payload' },
    { id: 'endp', label: t('api_configurator.wizard.step_endp'), note: 'read / write' },
    {
      id: 'schema',
      label: t('api_configurator.wizard.step_schema'),
      note: t('api_configurator.wizard.schema.note'),
    },
  ];

  const baseUrlValid = /^https?:\/\/.+/.test(form.baseUrl.trim());
  const canLeaveStep0 = form.name.trim() !== '' && form.code !== '' && baseUrlValid;

  function persistDraft(onDone: () => void): void {
    setSaving(true);
    setSaveError(null);
    const values = toConnectionInput(form);
    const onError = (err: { message?: string }): void => {
      setSaving(false);
      setSaveError(err?.message ?? t('api_configurator.wizard.save_failed'));
    };

    if (connectionId === null) {
      create(
        { resource: 'connections', values, successNotification: false },
        {
          onSuccess: ({ data }) => {
            setSaving(false);
            const id = (data as { id?: string }).id;
            if (id !== undefined) {
              setConnectionId(id);
            }
            onDone();
          },
          onError,
        },
      );
      return;
    }

    // Re-entry after going back: PATCH the draft (code is immutable, so omitted).
    const { code: _code, ...patch } = values;
    update(
      { resource: 'connections', id: connectionId, values: patch, successNotification: false },
      {
        onSuccess: () => {
          setSaving(false);
          onDone();
        },
        onError,
      },
    );
  }

  function handleNext(): void {
    if (step === 0) {
      if (!canLeaveStep0 || saving) {
        return;
      }
      persistDraft(() => setStep(1));
      return;
    }
    setStep((s) => Math.min(s + 1, steps.length - 1));
  }

  function handleBack(): void {
    if (step === 0) {
      navigate(HUB);
      return;
    }
    setStep((s) => s - 1);
  }

  const isLast = step === steps.length - 1;

  return (
    <div className="max-w-[1100px] space-y-5">
      <div className="flex items-center gap-3">
        <Button
          variant="outline"
          size="icon"
          onClick={() => navigate(HUB)}
          aria-label={t('api_configurator.wizard.back')}
        >
          <ArrowLeft className="size-4" aria-hidden="true" />
        </Button>
        <div className="min-w-0 flex-1">
          <h1 className="font-display text-[22px] font-semibold tracking-tight">
            {t('api_configurator.wizard.title')}
          </h1>
          <p className="text-[12.5px] text-zinc-500">{t('api_configurator.wizard.subtitle')}</p>
        </div>
      </div>

      <div className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-3">
        <ol className="flex items-stretch gap-2">
          {steps.map((s, i) => {
            const state: StepState = i < step ? 'done' : i === step ? 'active' : 'pending';
            const reachable = i <= step;
            return (
              <li key={s.id} className="flex flex-1 items-center gap-2">
                <button
                  type="button"
                  onClick={() => reachable && setStep(i)}
                  disabled={!reachable}
                  aria-current={state === 'active' ? 'step' : undefined}
                  className={cn(
                    'min-w-0 flex-1 rounded-xl border px-3 py-2 text-left transition',
                    state === 'done' && 'border-emerald-200/70 bg-emerald-50/60',
                    state === 'active' && 'border-zinc-900/15 bg-white shadow-sm',
                    state === 'pending' && 'cursor-default border-zinc-100 bg-zinc-50/60',
                  )}
                >
                  <div className="flex items-center gap-1.5">
                    <span
                      className={cn(
                        'grid size-5 place-items-center rounded-full text-[10px] font-semibold',
                        state === 'done' && 'bg-emerald-500 text-white',
                        state === 'active' && 'bg-zinc-900 text-white',
                        state === 'pending' && 'bg-zinc-200 text-zinc-400',
                      )}
                    >
                      {state === 'done' ? <Check className="size-3" aria-hidden="true" /> : i + 1}
                    </span>
                    <span
                      className={cn(
                        'truncate text-[12.5px] font-semibold tracking-tight',
                        state === 'active' && 'text-zinc-900',
                        state === 'done' && 'text-emerald-800',
                        state === 'pending' && 'text-zinc-400',
                      )}
                    >
                      {s.label}
                    </span>
                  </div>
                  <div
                    className={cn(
                      'mt-0.5 truncate font-mono text-[10.5px]',
                      state === 'pending' ? 'text-zinc-300' : 'text-zinc-500',
                    )}
                  >
                    {s.note}
                  </div>
                </button>
                {i < steps.length - 1 ? (
                  <ArrowRight className="size-3 shrink-0 text-zinc-300" aria-hidden="true" />
                ) : null}
              </li>
            );
          })}
        </ol>
      </div>

      {step === 0 ? <StepConnection form={form} set={set} /> : null}
      {step === 1 ? <StepTest form={form} connectionId={connectionId} /> : null}
      {step === 2 ? <StepEndpoints connectionId={connectionId} /> : null}
      {step === 3 ? <StepSchema connectionId={connectionId} /> : null}

      {saveError !== null ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50/60 p-3 text-[12.5px] text-rose-700">
          {saveError}
        </div>
      ) : null}

      <div className="flex items-center gap-3 pt-1">
        <Button variant="outline" onClick={handleBack} disabled={saving}>
          {step === 0 ? t('app.cancel') : t('api_configurator.wizard.back')}
        </Button>
        <div className="flex-1" />
        <div className="text-[12px] text-zinc-500">
          {t('api_configurator.wizard.step_counter', { current: step + 1, total: steps.length })}
        </div>
        {isLast ? (
          <Button onClick={() => navigate(HUB)}>
            {t('api_configurator.wizard.finish')}
            <ArrowRight className="ml-1.5 size-4" aria-hidden="true" />
          </Button>
        ) : (
          <Button onClick={handleNext} disabled={(step === 0 && !canLeaveStep0) || saving}>
            {saving ? t('api_configurator.wizard.saving') : t('api_configurator.wizard.next')}
            <ArrowRight className="ml-1.5 size-4" aria-hidden="true" />
          </Button>
        )}
      </div>
    </div>
  );
}
