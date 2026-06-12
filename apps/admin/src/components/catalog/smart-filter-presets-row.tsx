import { Plus, X, Zap } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import type { SmartFilterPreset } from '@/lib/filters/use-smart-presets';
import { cn } from '@/lib/utils';

/**
 * VIEW-09 (#535) — Smart Filter Presets row (mockup `list-view-v2.jsx`
 * l. 71-107).
 *
 * Pixel-perfect Tailwind tokens mirror the prototype. Note PRD §11
 * krytyczna nota marketingowa — copy explicit nazywa to "reguły"
 * (nie "AI-powered") i wskazuje "LLM od Fazy 1" jako rzetelny vector
 * roadmap, nie obietnicę.
 */

const BUILT_IN_LABEL_KEY: Record<string, string> = {
  'inconsistent-translations': 'products.smart_filters.builtin.inconsistent_translations',
  'missing-images': 'products.smart_filters.builtin.missing_images',
  'weak-seo': 'products.smart_filters.builtin.weak_seo',
  'red-low-completeness': 'products.smart_filters.builtin.red_low_completeness',
  'no-category': 'products.smart_filters.builtin.no_category',
};

interface SmartFilterPresetsRowProps {
  presets: SmartFilterPreset[];
  activeId: string | null;
  onSelect: (preset: SmartFilterPreset | null) => void;
  onCreate: () => void;
  isLoading?: boolean;
  /**
   * #1205 — when provided, user-created presets (not built-in/system) get a
   * hover/focus-revealed delete affordance. Built-in and system presets are
   * never deletable (the backend rejects them anyway), so the control is
   * suppressed for them.
   */
  onDelete?: (preset: SmartFilterPreset) => void;
}

export function SmartFilterPresetsRow({
  presets,
  activeId,
  onSelect,
  onCreate,
  isLoading = false,
  onDelete,
}: SmartFilterPresetsRowProps) {
  const { t, i18n } = useTranslation();
  const lang = i18n.language.startsWith('en') ? 'en' : 'pl';

  return (
    <div className="rounded-3xl bg-white shadow-sm border border-zinc-100 px-3 py-2.5 flex items-center gap-2">
      <div className="px-2 flex items-center gap-1.5 shrink-0">
        <span
          className="h-7 w-7 rounded-xl bg-zinc-900 text-white grid place-items-center"
          aria-hidden="true"
        >
          <Zap className="size-3.5" />
        </span>
        <div className="leading-tight">
          <div className="text-[11.5px] font-semibold tracking-tight">
            {t('products.smart_filters.label', { defaultValue: 'Smart filtry' })}
          </div>
          <div className="text-[10px] text-zinc-500 inline-flex items-center gap-1">
            <span className="font-mono px-1 rounded bg-zinc-100 text-zinc-500">
              {t('products.smart_filters.subtitle_rules', { defaultValue: 'reguły' })}
            </span>
            <span>
              ·{' '}
              {t('products.smart_filters.subtitle_llm_phase_1', { defaultValue: 'LLM od Fazy 1' })}
            </span>
          </div>
        </div>
      </div>

      <span className="h-7 w-px bg-zinc-100 shrink-0" />

      {/* NUI-13 — an empty tablist reads as hidden to AT; the role mounts
          only once presets exist (skeleton/empty states are decorative). */}
      {isLoading || presets.length === 0 ? (
        <div className="flex items-center gap-1.5 overflow-x-auto scrollbar-thin flex-1 min-w-0">
          {isLoading && (
            <>
              <SkeletonChip />
              <SkeletonChip />
              <SkeletonChip />
            </>
          )}
        </div>
      ) : (
        <div
          role="tablist"
          aria-label={t('products.smart_filters.label', { defaultValue: 'Smart filtry' })}
          className="flex items-center gap-1.5 overflow-x-auto scrollbar-thin flex-1 min-w-0"
        >
          {presets.map((preset) => {
            const isActive = activeId === preset.id;
            const labelKey = BUILT_IN_LABEL_KEY[preset.slug];
            const fallbackLabel = preset.name[lang] ?? preset.name.pl;
            const label = labelKey ? t(labelKey, { defaultValue: fallbackLabel }) : fallbackLabel;
            const isDeletable = onDelete !== undefined && !preset.is_built_in && !preset.is_system;
            return (
              // Wrapper keeps the select chip and the delete control as
              // siblings (a button cannot be nested inside another button).
              <div key={preset.id} className="group/preset relative shrink-0 inline-flex">
                <button
                  type="button"
                  role="tab"
                  aria-selected={isActive}
                  onClick={() => {
                    onSelect(isActive ? null : preset);
                  }}
                  className={cn(
                    'inline-flex items-center gap-2 h-9 px-3 rounded-2xl text-[12.5px] font-medium transition border focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900',
                    isDeletable && 'pr-7',
                    isActive
                      ? 'bg-zinc-900 text-white border-zinc-900'
                      : 'bg-zinc-50 text-zinc-700 border-zinc-100 hover:bg-white hover:border-zinc-200',
                  )}
                >
                  <span className="text-[14px] leading-none" aria-hidden="true">
                    {preset.icon}
                  </span>
                  <span>{label}</span>
                  {preset.count !== undefined && (
                    <span
                      className={cn(
                        'text-[10.5px] tabular-nums font-mono',
                        isActive ? 'text-white/60' : 'text-zinc-500',
                      )}
                    >
                      {preset.count}
                    </span>
                  )}
                </button>
                {isDeletable && (
                  <button
                    type="button"
                    onClick={() => {
                      onDelete(preset);
                    }}
                    aria-label={t('products.smart_filters.delete_aria', {
                      defaultValue: 'Usuń preset {{name}}',
                      name: label,
                    })}
                    className={cn(
                      'absolute right-1 top-1/2 -translate-y-1/2 grid h-5 w-5 place-items-center rounded-full opacity-0 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 group-hover/preset:opacity-100 group-focus-within/preset:opacity-100',
                      isActive
                        ? 'text-white/70 hover:bg-white/20 hover:text-white'
                        : 'text-zinc-500 hover:bg-zinc-200 hover:text-zinc-700',
                    )}
                  >
                    <X className="size-3" />
                  </button>
                )}
              </div>
            );
          })}
        </div>
      )}

      <button
        type="button"
        onClick={onCreate}
        className="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-2xl text-[12px] text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900"
        aria-label={t('products.smart_filters.custom_preset_button', {
          defaultValue: 'Własny preset',
        })}
      >
        <Plus className="size-3.5" />
        <span>
          {t('products.smart_filters.custom_preset_button', { defaultValue: 'Własny preset' })}
        </span>
      </button>
    </div>
  );
}

function SkeletonChip() {
  return (
    <span className="h-9 w-32 rounded-2xl bg-zinc-100 animate-pulse shrink-0" aria-hidden="true" />
  );
}
