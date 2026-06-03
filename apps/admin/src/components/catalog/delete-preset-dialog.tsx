import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { toast } from '@/components/ui/toast';
import type { SmartFilterPreset } from '@/lib/filters/use-smart-presets';

interface DeletePresetDialogProps {
  /** The preset to delete, or `null` when the dialog is closed. */
  preset: SmartFilterPreset | null;
  /** Closes the dialog (sets `preset` back to null in the parent). */
  onClose: () => void;
  /** Called after a successful delete so the parent can clear active state. */
  onDeleted: (presetId: string) => void;
  /** `useSmartPresets().remove` — issues the DELETE and reloads the list. */
  remove: (id: string) => Promise<void>;
}

/**
 * #1205 — confirmation before deleting a user-created Smart Filter preset.
 * Destructive (irreversible) action, so it goes through a modal per the
 * CLAUDE.md rule. Built-in/system presets never reach here (the row hides
 * the affordance for them).
 */
export function DeletePresetDialog({
  preset,
  onClose,
  onDeleted,
  remove,
}: DeletePresetDialogProps) {
  const { t, i18n } = useTranslation();
  const lang = i18n.language.startsWith('en') ? 'en' : 'pl';
  const [isDeleting, setIsDeleting] = useState(false);

  const name = preset ? (preset.name[lang] ?? preset.name.pl) : '';

  const confirm = async (): Promise<void> => {
    if (preset === null) return;
    setIsDeleting(true);
    try {
      await remove(preset.id);
      onDeleted(preset.id);
      toast.success(
        t('products.smart_filters.delete_success', { defaultValue: 'Preset usunięty' }),
      );
      onClose();
    } catch {
      toast.error(
        t('products.smart_filters.delete_failed', { defaultValue: 'Nie udało się usunąć presetu' }),
      );
    } finally {
      setIsDeleting(false);
    }
  };

  return (
    <Dialog
      open={preset !== null}
      onOpenChange={(open) => {
        if (!open && !isDeleting) onClose();
      }}
    >
      <DialogContent className="max-w-sm">
        <DialogHeader>
          <DialogTitle>
            {t('products.smart_filters.delete_confirm_title', { defaultValue: 'Usunąć preset?' })}
          </DialogTitle>
          <DialogDescription>
            {t('products.smart_filters.delete_confirm_body', {
              defaultValue: 'Preset „{{name}}" zostanie trwale usunięty.',
              name,
            })}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter className="mt-4 gap-2">
          <Button variant="outline" onClick={onClose} disabled={isDeleting}>
            {t('common.cancel', { defaultValue: 'Anuluj' })}
          </Button>
          <Button
            variant="destructive"
            onClick={() => {
              void confirm();
            }}
            disabled={isDeleting}
          >
            {t('products.smart_filters.delete_confirm_cta', { defaultValue: 'Usuń preset' })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
