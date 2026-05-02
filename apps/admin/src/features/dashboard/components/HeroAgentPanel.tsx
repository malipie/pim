import { Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';
import { cn } from '@/lib/utils';

/**
 * MOCK component — agent CTA hero card with command-palette placeholder.
 * Backend: brak. Wymaga LLM provider integration (Anthropic SDK PHP, Faza 2).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
export function HeroAgentPanel() {
  const { t } = useTranslation();

  return (
    <div
      className={cn(
        'relative overflow-hidden rounded-3xl border border-line bg-gradient-to-br from-violet-50 via-white to-white p-8 soft-shadow-lg',
      )}
    >
      <MockBadge variant="corner" />
      <div className="flex items-start justify-between gap-6">
        <div className="max-w-2xl">
          <div className="mb-3 inline-flex items-center gap-1.5 rounded-full bg-accent-violet/10 px-2.5 py-1 text-[11px] font-medium uppercase tracking-wide text-accent-violet">
            <Sparkles className="size-3.5" />
            {t('dashboard.hero.badge')}
          </div>
          <h1 className="display text-[28px] font-semibold leading-tight text-ink">
            {t('dashboard.hero.title')}
          </h1>
          <p className="mt-2 text-[15px] text-ink-2">{t('dashboard.hero.subtitle')}</p>
        </div>
        <div className="hidden shrink-0 items-center gap-2 sm:flex">
          {/* MOCK: command palette CTA — wymaga agent layer (Faza 2, #TBD) */}
          <button
            type="button"
            disabled
            aria-disabled="true"
            className={cn(
              'cursor-not-allowed rounded-2xl bg-ink px-5 py-3 text-sm font-medium text-white opacity-90 soft-shadow',
              'flex items-center gap-2',
            )}
            title={t('dashboard.hero.cta_disabled_hint') ?? ''}
          >
            <Sparkles className="size-4" />
            {t('dashboard.hero.cta')}
            <kbd className="ml-2 rounded-md bg-white/15 px-1.5 py-0.5 font-mono text-[11px]">
              ⌘K
            </kbd>
          </button>
          <MockBadge tooltip={t('dashboard.hero.cta_disabled_hint') ?? undefined} />
        </div>
      </div>
    </div>
  );
}
