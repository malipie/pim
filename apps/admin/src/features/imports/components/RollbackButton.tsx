import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { HttpError, jsonFetch } from '@/lib/http';

interface RollbackButtonProps {
  sessionId: string;
  rollbackUntil: string | null;
  onRolledBack: () => void;
}

/**
 * Spec §5.7 results screen — "Wycofaj import" CTA. Disabled when the
 * 24h window has expired; opens a confirm Dialog before issuing the
 * destructive POST. Cascade warning copy mentions Faza 1+ channel
 * pruning so operators know the MVP scope (objects only).
 */
export function RollbackButton({
  sessionId,
  rollbackUntil,
  onRolledBack,
}: RollbackButtonProps): React.ReactElement {
  const { t } = useTranslation();
  const [open, setOpen] = React.useState(false);
  const [submitting, setSubmitting] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  const isExpired = rollbackUntil === null || new Date(rollbackUntil).getTime() < Date.now();

  const handleConfirm = (): void => {
    setSubmitting(true);
    setError(null);
    jsonFetch(`/api/import-sessions/${sessionId}/rollback`, { method: 'POST' })
      .then(() => {
        setOpen(false);
        onRolledBack();
      })
      .catch((err: unknown) => {
        if (err instanceof HttpError) {
          setError(`HTTP ${err.status}`);
        } else {
          setError(err instanceof Error ? err.message : 'unknown');
        }
      })
      .finally(() => setSubmitting(false));
  };

  if (isExpired) {
    return (
      <Button variant="outline" disabled>
        ↶ {t('imports.results.rollback_expired', { defaultValue: 'Okno wycofania wygasło' })}
      </Button>
    );
  }

  return (
    <>
      <Button variant="outline" onClick={() => setOpen(true)}>
        ↶ {t('imports.results.rollback', { defaultValue: 'Wycofaj import' })}
      </Button>
      <p className="text-xs text-muted-foreground">
        ⏰{' '}
        {t('imports.results.rollback_window', {
          date: rollbackUntil !== null ? new Date(rollbackUntil).toLocaleString('pl-PL') : '',
          defaultValue: 'Dostępne do {{date}}',
        })}
      </p>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {t('imports.results.rollback', { defaultValue: 'Wycofaj import' })}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-3 text-sm">
            <p>
              Operacja usunie wszystkie produkty zaimportowane w tej sesji. Powiązane assety (jeśli
              były pobrane) pozostaną w DAM — usuń je ręcznie.
            </p>
            <p className="rounded-md border border-amber-500/40 bg-amber-50 p-2 text-xs">
              ⚠️ Jeśli te produkty były publikowane do kanałów (Faza 1+) — wycofanie MVP usunie je
              tylko z tej tabeli. Cascade do kanałów dochodzi w Fazie 1.
            </p>
            {error !== null && (
              <p role="alert" className="text-destructive">
                {error}
              </p>
            )}
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setOpen(false)} disabled={submitting}>
              {t('imports.wizard.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button variant="destructive" onClick={handleConfirm} disabled={submitting}>
              {t('imports.results.rollback', { defaultValue: 'Wycofaj' })}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
