import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import type { useImportWizard } from '@/features/imports/hooks/useImportWizard';

interface StepConfirmProps {
  wizard: ReturnType<typeof useImportWizard>;
}

/**
 * Placeholder shell for Step 4 (spec §5.5). Full summary card + backup
 * trigger + "Uruchom import" CTA arrive in IMP-11.
 */
export function StepConfirmPlaceholder({ wizard }: StepConfirmProps): React.ReactElement {
  const { t } = useTranslation();
  return (
    <div className="space-y-4 rounded-md border bg-card p-6">
      <p className="text-sm text-muted-foreground">
        {t('imports.wizard.steps.confirm', { defaultValue: 'Potwierdzenie' })} — IMP-11.
      </p>
      <div className="flex justify-between">
        <Button variant="ghost" onClick={() => wizard.back()}>
          ← {t('imports.wizard.back', { defaultValue: 'Wstecz' })}
        </Button>
        <Button onClick={() => wizard.reset()}>
          {t('imports.wizard.run', { defaultValue: 'Uruchom import' })}
        </Button>
      </div>
    </div>
  );
}
