import { Languages } from 'lucide-react';
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

interface LanguageOption {
  code: string;
  labelKey: string;
}

const LANGUAGES: LanguageOption[] = [
  { code: 'pl', labelKey: 'language.pl' },
  { code: 'en', labelKey: 'language.en' },
];

/**
 * Top-bar language switcher (#62 / 0.6.9).
 *
 * `i18next-browser-languagedetector` already persists the choice to
 * localStorage by default (lookup order: localStorage → navigator).
 * The switcher just calls `i18n.changeLanguage` and the detector picks
 * up the new value on its next read. No manual storage juggling.
 */
export function LanguageSwitcher() {
  const { t, i18n } = useTranslation();
  const active = i18n.resolvedLanguage ?? i18n.language;
  const activeShort = active.split('-')[0] ?? active;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          aria-label={t('language.aria_label', { defaultValue: 'Switch language' })}
          className="relative"
        >
          <Languages className="size-4" />
          <span className="absolute bottom-1 right-1 rounded bg-secondary px-1 text-[8px] font-bold uppercase">
            {activeShort}
          </span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-44">
        <DropdownMenuLabel>{t('language.title', { defaultValue: 'Language' })}</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {LANGUAGES.map((option) => (
          <DropdownMenuItem
            key={option.code}
            onClick={() => {
              void i18n.changeLanguage(option.code);
            }}
            className={activeShort === option.code ? 'bg-secondary' : ''}
          >
            <span className="font-mono text-xs uppercase">{option.code}</span>
            <span>{t(option.labelKey)}</span>
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
