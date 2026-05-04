import { Copy } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';

interface DuplicateResponse {
  id: string;
  code: string;
  source_id: string;
}

export interface DuplicateButtonProps {
  productId: string;
  disabled?: boolean;
}

/**
 * VIEW-07 (#420) — single-click duplicate over `POST /api/products/{id}/duplicate`.
 *
 * Operator chose the dialog-less flow ("Duplikuj ma działać na zasadzie
 * kopiowania produktu"). Defaults: `with_categories=true`,
 * `with_assets=false`, `with_relations=false` — backend auto-generates
 * the SKU as `{src}-COPY-N`. Subject lands on `/products/{newId}`.
 *
 * The richer dialog with explicit SKU + flag toggles still ships from
 * `components/catalog/duplicate-product-dialog.tsx` for the list view's
 * row-actions menu (UI-02.13).
 */
export function DuplicateButton({ productId, disabled }: DuplicateButtonProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [pending, setPending] = useState(false);

  const handleClick = async (): Promise<void> => {
    setPending(true);
    try {
      toast.info(t('products.detail.duplicate.pending', { defaultValue: 'Tworzę kopię…' }));
      const response = await jsonFetch<DuplicateResponse>(`/api/products/${productId}/duplicate`, {
        method: 'POST',
        contentType: 'application/json',
        body: { with_categories: true, with_assets: false, with_relations: false },
      });
      toast.success(
        t('products.detail.duplicate.success', {
          defaultValue: 'Utworzono kopię {{code}}',
          code: response.code,
        }),
      );
      navigate(`/products/${response.id}`);
    } catch {
      toast.error(
        t('products.detail.duplicate.failed', { defaultValue: 'Nie udało się utworzyć kopii' }),
      );
    } finally {
      setPending(false);
    }
  };

  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      onClick={() => void handleClick()}
      disabled={disabled || pending}
      className="h-9 gap-1.5 rounded-xl bg-white px-3 text-[12.5px] text-zinc-600 soft-shadow"
    >
      <Copy className="size-4" aria-hidden />
      {t('products.detail.actions.duplicate', { defaultValue: 'Duplikuj' })}
    </Button>
  );
}
