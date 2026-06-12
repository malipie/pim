import { Lock, Unlock } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * VIEW-18 (#549) — per-attribute lock toggle.
 *
 * Renders a small icon button next to the attribute label in the detail
 * view. Toggling posts the full replacement list to
 * `PATCH /api/products/{id}/locks` (PUT/PATCH share the route). Bulk
 * actions read the same JSONB slot via `AttributeLockReader` and
 * skip+report locked attrs.
 */

interface AttributeLockToggleProps {
  productId: string;
  attrCode: string;
  initialLocked: boolean;
  currentLocks: string[];
  onChanged?: (locks: string[]) => void;
}

export function AttributeLockToggle({
  productId,
  attrCode,
  initialLocked,
  currentLocks,
  onChanged,
}: AttributeLockToggleProps) {
  const { t } = useTranslation();
  const [locked, setLocked] = useState(initialLocked);
  const [isLoading, setIsLoading] = useState(false);

  const toggle = async (): Promise<void> => {
    setIsLoading(true);
    const nextLocks = locked
      ? currentLocks.filter((code) => code !== attrCode)
      : Array.from(new Set([...currentLocks, attrCode]));
    try {
      const response = await jsonFetch<{ locked_attributes: string[] }>(
        `/api/products/${productId}/locks`,
        {
          method: 'PATCH',
          body: { locked_attributes: nextLocks },
        },
      );
      setLocked(response.locked_attributes.includes(attrCode));
      onChanged?.(response.locked_attributes);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'lock toggle failed');
    } finally {
      setIsLoading(false);
    }
  };

  const Icon = locked ? Lock : Unlock;
  return (
    <button
      type="button"
      onClick={() => void toggle()}
      disabled={isLoading}
      aria-pressed={locked}
      aria-label={
        locked
          ? t('products.attr_lock.unlock', { defaultValue: 'Odblokuj atrybut' })
          : t('products.attr_lock.lock', { defaultValue: 'Zablokuj atrybut' })
      }
      className={cn(
        'h-6 w-6 grid place-items-center rounded-md transition disabled:opacity-50',
        locked
          ? 'text-amber-600 bg-amber-50 hover:bg-amber-100'
          : 'text-zinc-500 hover:bg-zinc-100',
      )}
    >
      <Icon className="size-3.5" aria-hidden="true" />
    </button>
  );
}
