import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Input } from '@/components/ui/input';
import { findLocaleEntry } from '@/lib/locales';
import { cn } from '@/lib/utils';

interface LocaleTabsFieldProps {
  values: Record<string, string>;
  enabledLocales: string[];
  primaryLocale: string;
  onChange?: (next: Record<string, string>) => void;
  placeholder?: string;
  readOnly?: boolean;
}

/**
 * VIEW-01 (#372) — multi-locale text field rendered as horizontal tabs
 * + a single shared input that follows the active tab.
 *
 * #1414 — the "+ Dodaj język" trigger is gone: the tab list always shows
 * the full set of workspace `enabledLocales`, and the locale list itself
 * is managed exclusively in Settings → Languages (LOC-07).
 *
 * Onboarding: when the editor lands on a locale missing in `values`,
 * the input starts empty and the change handler stamps the new entry.
 */
export function LocaleTabsField({
  values,
  enabledLocales,
  primaryLocale,
  onChange,
  placeholder,
  readOnly = false,
}: LocaleTabsFieldProps) {
  const { t } = useTranslation();
  const [active, setActive] = useState<string>(primaryLocale);

  useEffect(() => {
    if (!enabledLocales.includes(active)) {
      setActive(enabledLocales[0] ?? primaryLocale);
    }
  }, [enabledLocales, active, primaryLocale]);

  const inputValue = values[active] ?? '';

  return (
    <div className="space-y-2">
      <div
        role="tablist"
        aria-label={t('locale_tabs_field.aria', { defaultValue: 'Wybór języka' })}
        className="flex flex-wrap items-center gap-1"
      >
        {enabledLocales.map((code) => {
          const isActive = code === active;
          const entry = findLocaleEntry(code);
          return (
            <button
              key={code}
              role="tab"
              type="button"
              aria-selected={isActive}
              onClick={() => setActive(code)}
              className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[12px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                isActive
                  ? 'bg-zinc-900 text-white'
                  : 'border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50',
              )}
            >
              {entry ? <span aria-hidden>{entry.flag}</span> : null}
              <span className="font-mono uppercase">{code}</span>
              {code === primaryLocale ? (
                <span
                  className={cn(
                    'ml-1 rounded px-1 text-[9.5px] uppercase tracking-wider',
                    isActive ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500',
                  )}
                >
                  {t('locale_tabs_field.primary', { defaultValue: 'Primary' })}
                </span>
              ) : null}
            </button>
          );
        })}
      </div>
      <Input
        value={inputValue}
        readOnly={readOnly}
        onChange={(e) =>
          onChange?.({
            ...values,
            [active]: e.target.value,
          })
        }
        placeholder={placeholder}
        aria-label={t('locale_tabs_field.input_aria', {
          defaultValue: 'Wartość dla {{locale}}',
          locale: active,
        })}
      />
    </div>
  );
}
