import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { LOCALE_LIBRARY, type LocaleEntry } from '@/lib/locales';
import { cn } from '@/lib/utils';

interface LocaleAddDialogProps {
  open: boolean;
  onOpenChange: (next: boolean) => void;
  alreadyEnabled: string[];
  onSelect: (locale: string) => Promise<void>;
}

/**
 * VIEW-01 (#372) — small modal for picking a locale from `LOCALE_LIBRARY`.
 * The only popup in the modeling UI; everything else is a full-screen
 * route. Width capped at ~400px so it stays a quick action.
 */
export function LocaleAddDialog({
  open,
  onOpenChange,
  alreadyEnabled,
  onSelect,
}: LocaleAddDialogProps) {
  const { t } = useTranslation();
  const [pendingCode, setPendingCode] = useState<string | null>(null);

  const enabledSet = new Set(alreadyEnabled);
  const available = LOCALE_LIBRARY.filter((l) => !enabledSet.has(l.code));

  const handlePick = async (locale: LocaleEntry) => {
    setPendingCode(locale.code);
    try {
      await onSelect(locale.code);
      onOpenChange(false);
    } finally {
      setPendingCode(null);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[400px]">
        <DialogTitle>{t('locale_add_dialog.title', { defaultValue: 'Dodaj język' })}</DialogTitle>
        <DialogDescription className="mt-2">
          {t('locale_add_dialog.description', {
            defaultValue:
              'Wybrany język pojawi się jako nowy tab w polach wielojęzycznych w modelowaniu.',
          })}
        </DialogDescription>

        {available.length === 0 ? (
          <p className="mt-4 text-center text-[13px] text-muted-foreground">
            {t('locale_add_dialog.empty', {
              defaultValue: 'Wszystkie obsługiwane języki są już aktywne.',
            })}
          </p>
        ) : (
          <ul className="mt-4 max-h-[320px] overflow-y-auto rounded-xl border border-zinc-100">
            {available.map((locale) => {
              const isPending = pendingCode === locale.code;
              return (
                <li key={locale.code}>
                  <button
                    type="button"
                    onClick={() => void handlePick(locale)}
                    disabled={pendingCode !== null}
                    className={cn(
                      'flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-zinc-50',
                      isPending && 'bg-zinc-50',
                    )}
                  >
                    <span aria-hidden className="text-[18px]">
                      {locale.flag}
                    </span>
                    <span className="flex-1">
                      <span className="block text-[13px] font-medium tracking-tight">
                        {locale.label}
                      </span>
                      <span className="block font-mono text-[11px] uppercase text-zinc-500">
                        {locale.code}
                      </span>
                    </span>
                    {isPending ? (
                      <span className="text-[11px] text-zinc-400">
                        {t('locale_add_dialog.adding', { defaultValue: 'Dodawanie…' })}
                      </span>
                    ) : null}
                  </button>
                </li>
              );
            })}
          </ul>
        )}

        <div className="mt-6 flex justify-end">
          <Button
            variant="ghost"
            onClick={() => onOpenChange(false)}
            disabled={pendingCode !== null}
          >
            {t('app.cancel', { defaultValue: 'Anuluj' })}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
