import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogTitle,
} from '@/components/ui/dialog';

interface DangerZoneCardProps {
  title: string;
  description: string;
  destructiveLabel: string;
  blockedLabel: string;
  blocked: boolean;
  confirmTitle: string;
  confirmDescription: string;
  onConfirm: () => void | Promise<void>;
}

/**
 * VIEW-01 (#372) — Danger zone card on custom ObjectType detail
 * (object-types.jsx lines 221–240). Confirm dialog uses the shared
 * `<Dialog>` primitive (the only modal in VIEW-01 outside LocaleAddDialog).
 */
export function DangerZoneCard({
  title,
  description,
  destructiveLabel,
  blockedLabel,
  blocked,
  confirmTitle,
  confirmDescription,
  onConfirm,
}: DangerZoneCardProps) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);

  const handleConfirm = async () => {
    setPending(true);
    try {
      await onConfirm();
      setOpen(false);
    } finally {
      setPending(false);
    }
  };

  return (
    <Card className="border border-rose-100">
      <CardContent className="p-6">
        <div className="mb-3 text-[11px] font-medium uppercase tracking-wider text-rose-600">
          {t('object_types.danger_zone_title', { defaultValue: 'Danger zone' })}
        </div>
        <div className="flex items-center justify-between">
          <div>
            <div className="text-[14px] font-semibold tracking-tight">{title}</div>
            <div className="mt-0.5 text-[12px] text-zinc-500">{description}</div>
          </div>
          <Button
            type="button"
            variant={blocked ? 'secondary' : 'destructive'}
            disabled={blocked}
            onClick={() => setOpen(true)}
            className="gap-1.5"
          >
            <Trash2 className="size-3.5" />
            {blocked ? blockedLabel : destructiveLabel}
          </Button>
        </div>
      </CardContent>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogTitle>{confirmTitle}</DialogTitle>
          <DialogDescription className="mt-2">{confirmDescription}</DialogDescription>
          <div className="mt-6 flex justify-end gap-2">
            <DialogClose asChild>
              <Button variant="ghost" disabled={pending}>
                {t('app.cancel', { defaultValue: 'Anuluj' })}
              </Button>
            </DialogClose>
            <Button variant="destructive" disabled={pending} onClick={() => void handleConfirm()}>
              {pending
                ? t('object_types.delete_in_progress', { defaultValue: 'Usuwanie…' })
                : destructiveLabel}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
