import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { jsonFetch } from '@/lib/http';

interface SavedView {
  id: string;
  slug: string;
  name: string;
  description: string | null;
  resource: string;
  config: Record<string, unknown>;
  is_default: boolean;
  is_system: boolean;
}

/**
 * UI-02.15 (#305) — picker over `GET /api/saved-views?resource=products`
 * (UI-02.7 backend). Click on a view → `onApply(view.config)` so the
 * parent list can hydrate filters/sort/columns.
 *
 * Save / Manage / Set-as-default modals are deliberately deferred —
 * this slice ships the dropdown contract end-to-end so the products
 * list can swap view configs in one click. The "Save current as new"
 * trigger button is exposed via `onSaveCurrent`; the parent owns the
 * modal until UI-02.15 follow-up adds the dedicated SaveViewModal.
 */
export function SavedViewsDropdown({
  resource = 'products',
  activeSlug,
  onApply,
  onSaveCurrent,
}: {
  resource?: string;
  activeSlug?: string | null;
  onApply: (view: SavedView) => void;
  onSaveCurrent?: () => void;
}) {
  const { t } = useTranslation();
  const [views, setViews] = useState<SavedView[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    jsonFetch<{ views: SavedView[] }>(`/api/saved-views?resource=${encodeURIComponent(resource)}`)
      .then((body) => {
        if (!cancelled) setViews(body.views);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setError(err instanceof Error ? err.message : 'unknown');
      });
    return () => {
      cancelled = true;
    };
  }, [resource]);

  const activeName =
    views.find((v) => v.slug === activeSlug)?.name ??
    t('products.saved_views.placeholder', { defaultValue: 'Saved views' });

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm">
          {activeName}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent className="w-72">
        <DropdownMenuLabel>
          {t('products.saved_views.label', { defaultValue: 'Saved views' })}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        {error !== null ? (
          <DropdownMenuItem disabled>{`Error: ${error}`}</DropdownMenuItem>
        ) : views.length === 0 ? (
          <DropdownMenuItem disabled>
            {t('products.saved_views.empty', { defaultValue: 'No saved views yet.' })}
          </DropdownMenuItem>
        ) : (
          views.map((view) => (
            <DropdownMenuItem
              key={view.id}
              onSelect={() => onApply(view)}
              className="flex items-center gap-2"
            >
              <span className="flex-1 truncate">{view.name}</span>
              {view.is_default ? (
                <span className="rounded bg-secondary px-1.5 py-0.5 text-[10px] uppercase">
                  {t('products.saved_views.default_badge', { defaultValue: 'Default' })}
                </span>
              ) : null}
            </DropdownMenuItem>
          ))
        )}
        {onSaveCurrent ? (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={onSaveCurrent}>
              {t('products.saved_views.save_current', {
                defaultValue: '+ Save current as new view',
              })}
            </DropdownMenuItem>
          </>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
