import { useQuery } from '@tanstack/react-query';
import { AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { jsonFetch } from '@/lib/http';

import type { GroupMeta } from './types';

interface CurrentGroupsResponse {
  product_id: string;
  groups: GroupMeta[];
}

interface PreviewGroupsResponse {
  object_type_id: string;
  groups: GroupMeta[];
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  productId: string;
  objectTypeId: string | null;
  currentCategoryIds: string[];
  nextCategoryIds: string[];
  onConfirm: () => void;
}

/**
 * #891 — confirmation dialog shown before a destructive category change
 * removes (or replaces) categories assigned to a product. Computes the
 * delta between the current effective attribute groups (live for the
 * product) and the preview for the proposed assignment list, then lists
 * the attributes that will be hidden so the operator can decide.
 *
 * Soft-hide policy: backend retains values in `attributes_indexed` JSONB
 * — re-assigning the category surfaces them again with the original
 * value. The dialog explicitly notes this so operators don't fear
 * accidental data loss.
 */
export function CategoryChangeWarningDialog({
  open,
  onOpenChange,
  productId,
  objectTypeId,
  currentCategoryIds: _currentCategoryIds,
  nextCategoryIds,
  onConfirm,
}: Props) {
  const { t } = useTranslation();

  // Current effective groups for the product (live state).
  const currentGroups = useQuery({
    queryKey: ['products', productId, 'effective-attribute-groups'],
    queryFn: () =>
      jsonFetch<CurrentGroupsResponse>(`/api/products/${productId}/effective-attribute-groups`),
    enabled: open && productId !== '',
  });

  // Effective groups for the proposed category list.
  const previewGroups = useQuery({
    queryKey: [
      'object-types',
      objectTypeId,
      'effective-attribute-groups',
      'preview',
      [...nextCategoryIds].sort(),
    ],
    queryFn: () =>
      jsonFetch<PreviewGroupsResponse>(
        `/api/object_types/${objectTypeId}/effective-attribute-groups/preview`,
        {
          method: 'POST',
          contentType: 'application/json',
          accept: 'application/json',
          body: { categoryIds: nextCategoryIds },
        },
      ),
    enabled: open && objectTypeId !== null,
  });

  const isLoading = currentGroups.isLoading || previewGroups.isLoading;

  const removedAttrs = collectRemovedAttributes(
    currentGroups.data?.groups ?? [],
    previewGroups.data?.groups ?? [],
  );

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <div className="flex items-start gap-3">
          <span className="mt-0.5 grid size-9 shrink-0 place-items-center rounded-full bg-amber-100 text-amber-700">
            <AlertTriangle className="size-5" />
          </span>
          <div className="flex-1 space-y-2">
            <DialogTitle>
              {t('products.detail.categories.change_warning.title', {
                defaultValue: 'Zmiana kategorii',
              })}
            </DialogTitle>
            <DialogDescription>
              {t('products.detail.categories.change_warning.body', {
                defaultValue:
                  'Ta zmiana wpłynie na zestaw atrybutów widocznych w formularzu. Wartości w polach które znikną pozostaną zachowane w bazie — wrócą po ponownym przypisaniu kategorii.',
              })}
            </DialogDescription>
          </div>
        </div>

        <div className="mt-3 rounded-xl border border-line bg-surface p-3">
          {isLoading ? (
            <p className="text-[12.5px] text-muted-foreground">
              {t('app.loading', { defaultValue: 'Ładowanie…' })}
            </p>
          ) : removedAttrs.length === 0 ? (
            <p className="text-[12.5px] text-emerald-700">
              {t('products.detail.categories.change_warning.no_changes', {
                defaultValue: 'Żaden atrybut nie zostanie ukryty.',
              })}
            </p>
          ) : (
            <>
              <p className="text-[12.5px] font-medium text-ink">
                {t('products.detail.categories.change_warning.affected_count', {
                  defaultValue: 'Atrybuty które zostaną ukryte ({{count}}):',
                  count: removedAttrs.length,
                })}
              </p>
              <ul className="mt-2 max-h-48 space-y-1 overflow-y-auto pr-1 text-[12px]">
                {removedAttrs.map((attr) => (
                  <li
                    key={attr.code}
                    className="flex items-center gap-2 rounded-md bg-white px-2 py-1 text-ink soft-shadow"
                  >
                    <span className="font-mono text-[11px] text-muted-foreground">{attr.code}</span>
                    <span className="font-medium">{attr.label}</span>
                  </li>
                ))}
              </ul>
            </>
          )}
        </div>

        <div className="mt-4 flex justify-end gap-2">
          <Button variant="ghost" onClick={() => onOpenChange(false)}>
            {t('products.detail.categories.change_warning.cancel', {
              defaultValue: 'Anuluj',
            })}
          </Button>
          <Button
            type="button"
            onClick={() => {
              onConfirm();
              onOpenChange(false);
            }}
            disabled={isLoading}
            className="bg-amber-600 hover:bg-amber-700"
          >
            {t('products.detail.categories.change_warning.confirm', {
              defaultValue: 'Potwierdź zmianę',
            })}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function collectRemovedAttributes(
  currentGroups: GroupMeta[],
  nextGroups: GroupMeta[],
): { code: string; label: string }[] {
  const nextCodes = new Set<string>();
  for (const group of nextGroups) {
    for (const attr of group.attributes) {
      nextCodes.add(attr.code);
    }
  }
  const lang = (typeof document !== 'undefined' && document.documentElement.lang) || 'pl';
  const removed: { code: string; label: string }[] = [];
  for (const group of currentGroups) {
    for (const attr of group.attributes) {
      if (!nextCodes.has(attr.code)) {
        const label =
          typeof attr.label === 'object' && attr.label !== null
            ? ((attr.label as Record<string, string>)[lang] ??
              (attr.label as Record<string, string>).pl ??
              attr.code)
            : attr.code;
        removed.push({ code: attr.code, label });
      }
    }
  }
  return removed;
}
