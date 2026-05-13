import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import type { FilterDsl } from '@/lib/filters/filter-dsl';
import type { SmartFilterPreset } from '@/lib/filters/use-smart-presets';
import { cn } from '@/lib/utils';

const ICON_CHOICES = ['🔧', '⚙️', '⚡', '🛠️', '🏷️', '📦', '🔍', '🌟', '🚀', '🎯', '💡', '📊'];

interface SaveAsSmartPresetModalProps {
  query: FilterDsl | null;
  onClose: () => void;
  onSaved: (preset: SmartFilterPreset) => void;
  create: (input: {
    name: { pl: string; en: string };
    icon: string;
    query: FilterDsl;
  }) => Promise<SmartFilterPreset>;
}

/**
 * VIEW-09 (#535) — modal "Zapisz jako Smart Preset" wywoływany z
 * Advanced filter panel footer + SmartFilterPresetsRow "Własny preset"
 * button.
 *
 * Wymaga niepustego DSL — modal nie pozwoli zapisać presetu bez
 * conditions (Apply button disabled). Nazwa multilingual {pl, en}
 * zgodnie z CLAUDE.md punkt 8. Icon picker z 12 emoji presetów (lucide
 * IconPicker do Fazy 1).
 */
export function SaveAsSmartPresetModal({
  query,
  onClose,
  onSaved,
  create,
}: SaveAsSmartPresetModalProps) {
  const { t } = useTranslation();
  const [namePl, setNamePl] = useState('');
  const [nameEn, setNameEn] = useState('');
  const [icon, setIcon] = useState(ICON_CHOICES[0] ?? '🔧');
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const canSubmit =
    query !== null && namePl.trim().length >= 3 && nameEn.trim().length >= 3 && icon !== '';

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    if (!canSubmit || query === null) return;
    setIsPending(true);
    setError(null);
    try {
      const preset = await create({
        name: { pl: namePl.trim(), en: nameEn.trim() },
        icon,
        query,
      });
      onSaved(preset);
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'unknown');
    } finally {
      setIsPending(false);
    }
  };

  return (
    <Sheet
      open
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <SheetContent side="right" className="w-[460px] p-6">
        <SheetTitle>
          {t('products.smart_filters.save_as_preset_title', {
            defaultValue: 'Zapisz jako Smart Preset',
          })}
        </SheetTitle>
        <form onSubmit={(e) => void handleSubmit(e)} className="mt-4 space-y-4">
          <div className="space-y-2">
            <label htmlFor="smart-preset-name-pl" className="text-sm font-medium">
              {t('products.smart_filters.name_pl', { defaultValue: 'Nazwa (PL)' })}
              <span className="ml-1 text-rose-600">*</span>
            </label>
            <Input
              id="smart-preset-name-pl"
              value={namePl}
              onChange={(e) => setNamePl(e.target.value)}
              minLength={3}
              maxLength={60}
              placeholder={t('products.smart_filters.save_as_preset_name_placeholder', {
                defaultValue: 'np. Festo niski stock',
              })}
            />
          </div>

          <div className="space-y-2">
            <label htmlFor="smart-preset-name-en" className="text-sm font-medium">
              {t('products.smart_filters.name_en', { defaultValue: 'Nazwa (EN)' })}
              <span className="ml-1 text-rose-600">*</span>
            </label>
            <Input
              id="smart-preset-name-en"
              value={nameEn}
              onChange={(e) => setNameEn(e.target.value)}
              minLength={3}
              maxLength={60}
              placeholder="e.g. Festo low stock"
            />
          </div>

          <div className="space-y-2">
            <span className="text-sm font-medium">
              {t('products.smart_filters.save_as_preset_icon_label', { defaultValue: 'Ikona' })}
              <span className="ml-1 text-rose-600">*</span>
            </span>
            <fieldset className="grid grid-cols-6 gap-2 border-0 p-0">
              <legend className="sr-only">Icon</legend>
              {ICON_CHOICES.map((choice) => (
                <label
                  key={choice}
                  className={cn(
                    'h-10 w-10 rounded-xl border text-[18px] grid place-items-center cursor-pointer focus-within:ring-2 focus-within:ring-zinc-900',
                    icon === choice
                      ? 'bg-zinc-900 text-white border-zinc-900'
                      : 'bg-white text-zinc-700 border-zinc-200 hover:border-zinc-300',
                  )}
                >
                  <input
                    type="radio"
                    name="smart-preset-icon"
                    value={choice}
                    checked={icon === choice}
                    onChange={() => setIcon(choice)}
                    className="sr-only"
                  />
                  <span aria-hidden="true">{choice}</span>
                </label>
              ))}
            </fieldset>
          </div>

          <div className="rounded-md border bg-muted/40 p-3 text-xs">
            <div className="mb-1 font-medium">
              {t('products.smart_filters.preview_title', { defaultValue: 'Warunki presetu:' })}
            </div>
            {query === null ? (
              <p className="text-muted-foreground">
                {t('products.smart_filters.preview_empty', {
                  defaultValue: 'Brak warunków. Dodaj filtr w panelu zaawansowanym.',
                })}
              </p>
            ) : (
              <pre className="font-mono whitespace-pre-wrap text-zinc-600">
                {JSON.stringify(query, null, 2)}
              </pre>
            )}
          </div>

          {error !== null ? <p className="text-sm text-rose-600">{error}</p> : null}

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" type="button" onClick={onClose} disabled={isPending}>
              {t('products.smart_filters.save_as_preset_cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button type="submit" disabled={!canSubmit || isPending}>
              {isPending
                ? t('products.smart_filters.submitting', { defaultValue: 'Zapisuję…' })
                : t('products.smart_filters.save_as_preset_save', { defaultValue: 'Zapisz' })}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
