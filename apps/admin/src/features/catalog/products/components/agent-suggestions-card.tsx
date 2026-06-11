import { FileText, Image as ImageIcon, Sparkles, Tag } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { MockBadge } from '@/components/ui/mock-badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

interface AgentSuggestion {
  id: string;
  icon: typeof Sparkles;
  titleKey: string;
  defaultTitle: string;
  hintKey: string;
  defaultHint: string;
}

const SUGGESTIONS: readonly AgentSuggestion[] = [
  {
    id: 'description',
    icon: FileText,
    titleKey: 'products.agent.suggestion_description_title',
    defaultTitle: 'Uzupełnij opis dla SEO',
    hintKey: 'products.agent.suggestion_description_hint',
    defaultHint: 'Brakuje description.en — agent może zaproponować tłumaczenie.',
  },
  {
    id: 'image',
    icon: ImageIcon,
    titleKey: 'products.agent.suggestion_image_title',
    defaultTitle: 'Dodaj zdjęcie 4:5 dla Instagram',
    hintKey: 'products.agent.suggestion_image_hint',
    defaultHint: 'Channel Instagram wymaga 4:5; obecne 1:1.',
  },
  {
    id: 'category',
    icon: Tag,
    titleKey: 'products.agent.suggestion_category_title',
    defaultTitle: 'Wybierz kategorię nadrzędną',
    hintKey: 'products.agent.suggestion_category_hint',
    defaultHint: 'Produkt nie ma przypisanej kategorii — agent może zaproponować.',
  },
] as const;

/**
 * UI-03b detail-sidebar mock surface (#366) for the agent layer.
 *
 * The real agent integration ships in epic 0.7 (Faza 2). This card renders
 * three hardcoded suggestions so the operator sees the intended placement
 * + density next to the Completeness ring and the rest of DetailSidebar.
 *
 * Each "Zastosuj" CTA is wrapped in MockBadge — the buttons are disabled
 * and explain why on hover.
 */
export function AgentSuggestionsCard() {
  const { t } = useTranslation();
  const tooltip = t('products.agent.mock_tooltip', {
    defaultValue: 'MOCK · Wymaga warstwy agenta (epik 0.7, Faza 2)',
  });

  return (
    <section className="relative rounded-2xl border border-line bg-surface p-4 soft-shadow">
      <MockBadge variant="corner" tooltip={tooltip} />
      <header className="mb-3 flex items-center gap-2">
        <Sparkles className="size-4 text-orange-700" />
        <h3 className="text-[13px] font-semibold text-ink">
          {t('products.agent.title', { defaultValue: 'Sugestie agenta' })}
        </h3>
      </header>
      <ul className="space-y-3">
        {SUGGESTIONS.map((s) => {
          const Icon = s.icon;
          return (
            <li key={s.id} className="flex items-start gap-3 rounded-xl bg-surface-2/60 p-3">
              <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-orange-500/10 text-orange-700">
                <Icon className="size-3.5" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-[12.5px] font-medium text-ink">
                  {t(s.titleKey, { defaultValue: s.defaultTitle })}
                </p>
                <p className="mt-0.5 text-[11px] text-muted-foreground">
                  {t(s.hintKey, { defaultValue: s.defaultHint })}
                </p>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      disabled
                      className="mt-1.5 h-6 px-2 text-[11px]"
                    >
                      {t('products.agent.apply', { defaultValue: 'Zastosuj' })}
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>{tooltip}</TooltipContent>
                </Tooltip>
              </div>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
