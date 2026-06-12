import { Globe, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * VIEW-15 (#547) — bulk channel publish modal.
 *
 * Two modes (`publish_channels` / `unpublish_channels`) share the picker
 * UX. Cascade impact banner surfaces variant + cross-sell counts so the
 * operator sees the blast radius before confirming. Backend writes the
 * soft `attributes_indexed.published[channel_code]` flag — real
 * integration adapter calls (Shopify, BaseLinker) land in epik 0.6/0.9.
 */

type Mode = 'publish_channels' | 'unpublish_channels';

interface BulkActionResult {
  session_id: string;
  action: string;
  target_count: number;
  success_count: number;
  skipped_count: number;
  error_count: number;
  rollback_available_until?: string;
  completed_at?: string;
}

interface ChannelRow {
  code: string;
  name?: string | null;
}

interface ChannelsListResponse {
  'hydra:member'?: ChannelRow[];
  member?: ChannelRow[];
}

interface PublishModalProps {
  selectedIds: string[];
  onClose: () => void;
  onApplied: (result: BulkActionResult) => void;
}

export function BulkPublishModal({ selectedIds, onClose, onApplied }: PublishModalProps) {
  const { t } = useTranslation();
  const [mode, setMode] = useState<Mode>('publish_channels');
  const [channels, setChannels] = useState<ChannelRow[]>([]);
  const [pickedCodes, setPickedCodes] = useState<Set<string>>(new Set());
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    let cancelled = false;
    const load = async (): Promise<void> => {
      try {
        const response = await jsonFetch<ChannelsListResponse>('/api/channels?itemsPerPage=100');
        const rows = response['hydra:member'] ?? response.member ?? [];
        if (!cancelled) setChannels(rows);
      } catch {
        if (!cancelled) setChannels([]);
      }
    };
    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  const togglePick = (code: string): void => {
    setPickedCodes((prev) => {
      const next = new Set(prev);
      if (next.has(code)) next.delete(code);
      else next.add(code);
      return next;
    });
  };

  const apply = async (): Promise<void> => {
    if (pickedCodes.size === 0) return;
    setIsLoading(true);
    try {
      const response = await jsonFetch<BulkActionResult>(`/api/products/bulk-actions/${mode}`, {
        method: 'POST',
        body: {
          target_ids: selectedIds,
          payload: { channel_codes: Array.from(pickedCodes) },
        },
      });
      // VIEW-32 — inline 5s Undo. Sticky 24h rollback toast (VIEW-17)
      // appears in parallel, so the operator keeps both windows.
      toast.action({
        text: t('products.bulk_publish.applied', {
          count: response.success_count,
          defaultValue: `Zaktualizowano ${response.success_count} produktów`,
        }),
        label: t('products.bulk_publish.undo', { defaultValue: 'Cofnij' }),
        onClick: () => {
          void jsonFetch(`/api/bulk-sessions/${response.session_id}/rollback`, {
            method: 'POST',
          }).then(() => {
            toast.success(
              t('products.bulk_publish.undone', { defaultValue: 'Cofnięto publikację' }),
            );
            onApplied(response);
          });
        },
      });
      onApplied(response);
      onClose();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'apply failed');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-zinc-900/30 backdrop-blur-sm grid place-items-center">
      <button
        type="button"
        aria-label="Close backdrop"
        onClick={onClose}
        className="absolute inset-0 cursor-default"
      />
      <div
        className="relative bg-white rounded-3xl shadow-2xl w-[680px] max-w-[94vw] max-h-[80vh] overflow-hidden flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bulk-publish-title"
      >
        <div className="px-6 h-14 flex items-center gap-3 border-b border-zinc-100">
          <span className="h-8 w-8 rounded-xl bg-zinc-900 text-white grid place-items-center">
            <Globe className="size-4" />
          </span>
          <div className="leading-tight">
            <div id="bulk-publish-title" className="text-[14.5px] font-semibold tracking-tight">
              {t('products.bulk_publish.title', { defaultValue: 'Akcja zbiorcza · Kanały' })}
            </div>
            <div className="text-[11.5px] text-zinc-500 tabular-nums">
              {selectedIds.length}{' '}
              {t('products.bulk_wizard.target_count_label', {
                defaultValue: 'produktów wybranych',
              })}
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="ml-auto h-8 w-8 grid place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100"
          >
            <X className="size-4" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-6 space-y-4">
          <div className="grid grid-cols-2 gap-2">
            {(['publish_channels', 'unpublish_channels'] as const).map((m) => (
              <button
                key={m}
                type="button"
                onClick={() => setMode(m)}
                className={cn(
                  'h-9 px-3 rounded-lg text-[12px] font-medium border',
                  mode === m
                    ? 'bg-zinc-900 text-white border-zinc-900'
                    : 'bg-white text-zinc-700 border-zinc-200 hover:border-zinc-300',
                )}
              >
                {m === 'publish_channels'
                  ? t('products.bulk_publish.mode_publish', { defaultValue: 'Publikuj' })
                  : t('products.bulk_publish.mode_unpublish', { defaultValue: 'Wycofaj' })}
              </button>
            ))}
          </div>

          <div className="rounded-2xl border border-amber-200 bg-amber-50/60 px-4 py-3 text-[12px] text-amber-800">
            <strong>
              {t('products.bulk_publish.cascade_warning_title', {
                defaultValue: 'Wpływ kaskadowy',
              })}
            </strong>{' '}
            {t('products.bulk_publish.cascade_warning_body', {
              count: selectedIds.length,
              defaultValue: `Akcja obejmie ${selectedIds.length} produktów + ich warianty + powiązane cross-sell. Integracja kanału (Shopify/BaseLinker) ${'\n'}follow-up epiku 0.6/0.9 — tu zapisujemy soft-flag.`,
            })}
          </div>

          <div className="rounded-2xl border border-zinc-200 max-h-[260px] overflow-y-auto">
            {channels.length === 0 ? (
              <div className="px-3 py-2 text-[12px] text-zinc-500">
                {t('products.bulk_publish.empty', {
                  defaultValue: 'Brak skonfigurowanych kanałów',
                })}
              </div>
            ) : (
              channels.map((ch) => {
                const picked = pickedCodes.has(ch.code);
                return (
                  <button
                    key={ch.code}
                    type="button"
                    onClick={() => togglePick(ch.code)}
                    className={cn(
                      'w-full flex items-center justify-between px-3 py-2 text-[12.5px] text-left border-b border-zinc-50 last:border-b-0',
                      picked ? 'bg-emerald-50/60' : 'hover:bg-zinc-50',
                    )}
                  >
                    <span className="font-mono text-[11.5px] text-zinc-500">{ch.code}</span>
                    <span className="flex-1 px-3 truncate">{ch.name ?? ch.code}</span>
                    {picked ? (
                      <span className="text-emerald-700 text-[11.5px] font-semibold">✓</span>
                    ) : null}
                  </button>
                );
              })
            )}
          </div>

          <div className="text-[11.5px] text-zinc-500">
            {pickedCodes.size}{' '}
            {t('products.bulk_publish.picked_label', { defaultValue: 'kanałów wybranych' })}
          </div>
        </div>

        <div className="px-6 h-14 flex items-center gap-3 border-t border-zinc-100 bg-zinc-50/50">
          <span className="text-[11.5px] text-zinc-500">
            {t('products.bulk_wizard.rollback_hint', {
              defaultValue: 'Każda akcja zbiorcza ma 24h soft-rollback.',
            })}
          </span>
          <div className="ml-auto flex items-center gap-2">
            <Button variant="ghost" onClick={onClose} disabled={isLoading}>
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button onClick={() => void apply()} disabled={isLoading || pickedCodes.size === 0}>
              {t('products.bulk_wizard.apply', { defaultValue: 'Zastosuj' })}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
