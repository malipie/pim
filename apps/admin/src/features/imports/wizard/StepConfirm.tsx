import * as React from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { BackupTriggerCheckbox } from '@/features/imports/components/BackupTriggerCheckbox';
import type { useImportWizard } from '@/features/imports/hooks/useImportWizard';
import { HttpError, jsonFetch } from '@/lib/http';

interface StepConfirmProps {
  wizard: ReturnType<typeof useImportWizard>;
}

/**
 * Spec §5.5 — Step 4 confirm. Renders the summary card, optional
 * pgBackRest trigger (IMP-06), email notification toggle, and the
 * "Uruchom import" CTA. The CTA is gated on the backup being either
 * `idle` (operator opted out) or `completed` so the backend always
 * runs on a snapshot when the user asked for one.
 */
export function StepConfirmPlaceholder({ wizard }: StepConfirmProps): React.ReactElement {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { state, setField } = wizard;

  const [backupStatus, setBackupStatus] = React.useState<
    'idle' | 'pending' | 'running' | 'completed' | 'failed'
  >('idle');
  const [submitting, setSubmitting] = React.useState(false);
  const [submitError, setSubmitError] = React.useState<string | null>(null);

  const canRun =
    state.file !== null &&
    state.targetObjectTypeId !== null &&
    !submitting &&
    (state.doBackup === false || backupStatus === 'completed' || backupStatus === 'idle');

  const handleRun = (): void => {
    if (state.file === null || state.targetObjectTypeId === null) {
      return;
    }
    setSubmitting(true);
    setSubmitError(null);

    const formData = new FormData();
    formData.set('file', state.file);
    formData.set('target_object_type_id', state.targetObjectTypeId);
    formData.set('mapping', JSON.stringify(state.mapping));
    formData.set('encoding', state.encoding);
    formData.set('delimiter', state.delimiter);
    formData.set('do_backup', state.doBackup ? '1' : '0');

    jsonFetch<{ id: string }>('/api/import-sessions', {
      method: 'POST',
      body: formData,
    })
      .then((data) => {
        wizard.reset();
        navigate(`/integrations/imports/${data.id}`);
      })
      .catch((err: unknown) => {
        if (err instanceof HttpError) {
          setSubmitError(`HTTP ${err.status}`);
        } else {
          setSubmitError(err instanceof Error ? err.message : 'unknown');
        }
        setSubmitting(false);
      });
  };

  return (
    <div className="space-y-6 rounded-md border bg-card p-6">
      <header>
        <h2 className="text-lg font-semibold">
          {t('imports.confirm.summary', { defaultValue: 'Podsumowanie' })}
        </h2>
      </header>

      <Card className="space-y-2 p-4 text-sm">
        <SummaryRow label="Plik" value={state.file?.name ?? '—'} />
        <SummaryRow label="Locale" value={state.locale ?? 'auto'} />
        <SummaryRow label="Encoding" value={state.encoding} />
        <SummaryRow label="Delimiter" value={state.delimiter} />
        <SummaryRow label="Mapowanie" value={`${Object.keys(state.mapping).length} kolumn`} />
        <SummaryRow label="Zdjęcia" value={state.imageSource} />
        {state.validation !== null && (
          <SummaryRow
            label="Do importu"
            value={`${state.validation.successCount} OK (+ ${state.validation.errorCount} pominiętych)`}
          />
        )}
      </Card>

      <BackupTriggerCheckbox
        checked={state.doBackup}
        onChange={(next) => setField('doBackup', next)}
        onStatusChange={setBackupStatus}
      />

      <label className="flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          checked={state.emailNotification}
          onChange={(event) => setField('emailNotification', event.target.checked)}
        />
        <span>
          {t('imports.confirm.email', {
            defaultValue: 'Wyślij email po zakończeniu (>5 min runtime)',
          })}
        </span>
      </label>

      <p className="rounded-md border border-amber-500/40 bg-amber-50 px-3 py-2 text-xs">
        ⚠️{' '}
        {t('imports.confirm.warning', {
          defaultValue: 'Akcja jest finalna. Możesz wycofać import w 24h.',
        })}
      </p>

      {submitError !== null && (
        <p role="alert" className="text-sm text-destructive">
          {submitError}
        </p>
      )}

      <div className="flex justify-between">
        <Button variant="ghost" onClick={() => wizard.back()}>
          ← {t('imports.wizard.back', { defaultValue: 'Wstecz' })}
        </Button>
        <Button onClick={handleRun} disabled={!canRun}>
          ▶ {t('imports.wizard.run', { defaultValue: 'Uruchom import' })}
        </Button>
      </div>
    </div>
  );
}

function SummaryRow({ label, value }: { label: string; value: string }): React.ReactElement {
  return (
    <div className="flex items-center justify-between gap-4">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-mono text-xs">{value}</span>
    </div>
  );
}
