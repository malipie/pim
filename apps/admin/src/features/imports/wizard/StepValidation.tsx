import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import type { useImportWizard } from '@/features/imports/hooks/useImportWizard';

interface StepValidationProps {
  wizard: ReturnType<typeof useImportWizard>;
}

/**
 * Placeholder shell for Step 3 (spec §5.4). Full implementation lands in IMP-11
 * — the validate-dry-run round-trip + top-10 errors table + "show all" modal.
 */
export function StepValidationPlaceholder({ wizard }: StepValidationProps): React.ReactElement {
  const { t } = useTranslation();
  return (
    <div className="space-y-4 rounded-md border bg-card p-6">
      <p className="text-sm text-muted-foreground">
        {t('imports.wizard.steps.validation', { defaultValue: 'Walidacja' })} — IMP-11.
      </p>
      <div className="flex justify-between">
        <Button variant="ghost" onClick={() => wizard.back()}>
          ← {t('imports.wizard.back', { defaultValue: 'Wstecz' })}
        </Button>
        <Button onClick={() => wizard.next()}>
          {t('imports.wizard.next', { defaultValue: 'Dalej →' })}
        </Button>
      </div>
    </div>
  );
}
