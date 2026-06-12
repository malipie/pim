import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { MockBadge } from '@/components/ui/mock-badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const SEPARATOR_PRESETS = [' ', ' · ', ' — ', ', ', ' / ', ''] as const;

interface ComputedColumnModalProps {
  open: boolean;
  onClose: () => void;
  headers: string[];
  sampleRow: Array<string | null>;
}

/**
 * NUI-10 (#1429) — MOCK „Nowa kolumna obliczona" (design `Import-nowy.html`
 * ComputedModal): pick source columns + a separator, watch a live preview
 * built from the first sample row. „Zastosuj" stays disabled — server-side
 * concatenation has no backend support yet
 * (backlog: Project Plan/UI/Retrofit_v2/importy-do-oprogramowania.md).
 */
export function ComputedColumnModal({
  open,
  onClose,
  headers,
  sampleRow,
}: ComputedColumnModalProps): React.ReactElement {
  const { t } = useTranslation();
  const [picked, setPicked] = React.useState<string[]>([]);
  const [separator, setSeparator] = React.useState<string>(' · ');

  const toggle = (header: string): void => {
    setPicked((prev) =>
      prev.includes(header) ? prev.filter((h) => h !== header) : [...prev, header],
    );
  };

  const preview = picked
    .map((header) => sampleRow[headers.indexOf(header)] ?? '')
    .filter((value) => value !== '')
    .join(separator);

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-[560px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {t('imports.computed.title', { defaultValue: 'Nowa kolumna obliczona' })}
            <MockBadge
              tooltip={t('imports.computed.mock_tooltip', {
                defaultValue:
                  'MOCK — konkatenacja server-side wymaga rozszerzenia kontraktu mappingu (backlog NUI-10)',
              })}
            />
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <div className="mb-1.5 text-[12px] font-semibold">
              {t('imports.computed.columns', {
                defaultValue: 'Kolumny źródłowe (kolejność klikania)',
              })}
            </div>
            <div className="flex max-h-44 flex-wrap gap-1.5 overflow-y-auto">
              {headers.map((header) => {
                const index = picked.indexOf(header);
                const active = index !== -1;
                return (
                  <button
                    key={header}
                    type="button"
                    onClick={() => toggle(header)}
                    className={cn(
                      'inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 font-mono text-[11.5px] transition',
                      active
                        ? 'border-zinc-900 bg-zinc-900 text-white'
                        : 'border-zinc-200 text-zinc-600 hover:border-zinc-400',
                    )}
                  >
                    {active ? <span className="num">{index + 1}.</span> : null}
                    {header}
                  </button>
                );
              })}
            </div>
          </div>

          <div>
            <div className="mb-1.5 text-[12px] font-semibold">
              {t('imports.computed.separator', { defaultValue: 'Separator' })}
            </div>
            <div className="flex flex-wrap items-center gap-1.5">
              {SEPARATOR_PRESETS.map((preset) => (
                <button
                  key={JSON.stringify(preset)}
                  type="button"
                  onClick={() => setSeparator(preset)}
                  className={cn(
                    'rounded-lg border px-2.5 py-1 font-mono text-[11.5px]',
                    separator === preset
                      ? 'border-zinc-900 bg-zinc-900 text-white'
                      : 'border-zinc-200 text-zinc-600 hover:border-zinc-400',
                  )}
                >
                  {preset === '' ? '∅' : `"${preset}"`}
                </button>
              ))}
              <input
                value={separator}
                onChange={(event) => setSeparator(event.target.value)}
                className="h-7 w-20 rounded-lg border border-zinc-200 px-2 font-mono text-[11.5px]"
                aria-label={t('imports.computed.separator_custom', {
                  defaultValue: 'Własny separator',
                })}
              />
            </div>
          </div>

          <div>
            <div className="mb-1.5 text-[12px] font-semibold">
              {t('imports.computed.preview', { defaultValue: 'Podgląd (pierwszy wiersz pliku)' })}
            </div>
            <div className="rounded-xl border border-zinc-100 bg-zinc-50/70 px-3 py-2.5 font-mono text-[12px] text-zinc-800">
              {preview !== ''
                ? preview
                : t('imports.computed.preview_empty', { defaultValue: '— wybierz kolumny —' })}
            </div>
          </div>

          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={onClose}>
              {t('imports.wizard.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Tooltip>
              <TooltipTrigger asChild>
                <span>
                  <Button disabled className="cursor-not-allowed opacity-60">
                    {t('imports.computed.apply', { defaultValue: 'Zastosuj' })}
                  </Button>
                </span>
              </TooltipTrigger>
              <TooltipContent side="top">
                {t('imports.computed.mock_tooltip', {
                  defaultValue:
                    'MOCK — konkatenacja server-side wymaga rozszerzenia kontraktu mappingu (backlog NUI-10)',
                })}
              </TooltipContent>
            </Tooltip>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
