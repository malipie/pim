import { Eye } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';

export interface PreviewButtonProps {
  disabled?: boolean;
}

/**
 * VIEW-07 (#420) — mock preview button.
 *
 * Operator's spec: "Podgląd jako Mock". A real preview surface needs a
 * per-channel renderer (Faza 1 / epik 09 Shopify). Until then the click
 * just notifies the user that the feature is intentionally deferred.
 */
export function PreviewButton({ disabled }: PreviewButtonProps) {
  const { t } = useTranslation();
  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      onClick={() => {
        toast.info(
          t('products.detail.preview.unavailable', {
            defaultValue: 'Podgląd produktu — funkcja w przygotowaniu',
          }),
        );
      }}
      disabled={disabled}
      className="h-9 gap-1.5 rounded-xl bg-white px-3 text-[12.5px] text-zinc-600 soft-shadow"
    >
      <Eye className="size-4" aria-hidden />
      {t('products.detail.actions.preview', { defaultValue: 'Podgląd' })}
    </Button>
  );
}
