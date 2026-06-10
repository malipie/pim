import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { jsonFetch } from '@/lib/http';
import { objectKeys } from '@/lib/object-query-keys';

interface Props {
  productId: string;
}

/**
 * CHC-04 (#1288) — banner shown in the product "Kategorie" tab when a category
 * move changed the product's effective schema since it was last filled
 * (`schemaDrift`). Acknowledging clears the flag and re-baselines the snapshot.
 */
export function SchemaDriftBanner({ productId }: Props) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [busy, setBusy] = useState(false);

  const { data } = useQuery({
    queryKey: objectKeys.schemaDrift(productId),
    queryFn: async () =>
      jsonFetch<{ schemaDrift?: boolean }>(`/api/products/${productId}`, {
        accept: 'application/json',
      }),
    enabled: productId !== '',
  });

  if (data?.schemaDrift !== true) {
    return null;
  }

  const acknowledge = async (): Promise<void> => {
    if (busy) return;
    setBusy(true);
    try {
      await jsonFetch(`/api/products/${productId}/schema-drift/acknowledge`, {
        method: 'POST',
        accept: 'application/json',
      });
      await queryClient.invalidateQueries({ queryKey: objectKeys.all(productId) });
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="rounded-xl border border-amber-200 bg-amber-50 p-3" role="alert">
      <p className="flex items-center gap-1.5 text-[12.5px] font-medium text-amber-900">
        <AlertTriangle className="size-4 shrink-0" />
        {t('products.detail.schema_drift.title', {
          defaultValue:
            'Schemat tego produktu zmienił się po ostatnim wypełnieniu. Sprawdź czy wszystkie dane są kompletne.',
        })}
      </p>
      <Button
        size="sm"
        variant="outline"
        className="mt-2 border-amber-300 text-amber-900 hover:bg-amber-100"
        onClick={() => void acknowledge()}
        disabled={busy}
      >
        {t('products.detail.schema_drift.acknowledge', {
          defaultValue: 'Rozumiem, zaktualizuj schemat',
        })}
      </Button>
    </div>
  );
}
