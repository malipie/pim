import { useList } from '@refinedev/core';
import { Check, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface CurrencyRow {
  id: string;
  code: string;
  symbol: string;
  label: string;
}

interface CurrencyPickerProps {
  value: string[];
  onChange: (codes: string[]) => void;
  ariaLabelledBy?: string;
}

export function CurrencyPicker({ value, onChange, ariaLabelledBy }: CurrencyPickerProps) {
  const { t } = useTranslation();
  const { result, query } = useList<CurrencyRow>({
    resource: 'currencies',
    pagination: { mode: 'off' },
  });

  const currencies = result.data;
  const isLoading = query.isLoading;

  const toggle = (code: string) => {
    if (value.includes(code)) {
      onChange(value.filter((c) => c !== code));
    } else {
      onChange([...value, code]);
    }
  };

  return (
    <fieldset className="space-y-3" aria-labelledby={ariaLabelledBy}>
      {value.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {value.map((code) => (
            <span
              key={code}
              className="inline-flex items-center gap-1 rounded bg-accent-violet/10 px-2 py-1 font-mono text-[11px] text-accent-violet"
            >
              {code}
              <button
                type="button"
                onClick={() => toggle(code)}
                aria-label={t('channels.form.remove_chip', { defaultValue: 'Usuń' })}
                className="rounded hover:bg-accent-violet/20"
              >
                <X className="size-3" />
              </button>
            </span>
          ))}
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">
          {t('channels.form.currencies_empty', { defaultValue: 'Brak wybranych walut.' })}
        </p>
      )}

      <div className="rounded border bg-card p-2">
        {isLoading ? (
          <p className="px-2 py-1 text-xs text-muted-foreground">{t('app.loading')}</p>
        ) : currencies.length === 0 ? (
          <p className="px-2 py-1 text-xs text-muted-foreground">
            {t('channels.form.currencies_none_available', {
              defaultValue: 'Brak dostępnych walut.',
            })}
          </p>
        ) : (
          <div className="flex max-h-40 flex-col gap-0.5 overflow-y-auto">
            {currencies.map((currency) => {
              const checked = value.includes(currency.code);
              return (
                <Button
                  key={currency.id ?? currency.code}
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => toggle(currency.code)}
                  className={cn(
                    'justify-start font-mono text-xs',
                    checked && 'bg-accent-violet/10 text-accent-violet',
                  )}
                  aria-pressed={checked}
                >
                  <Check className={cn('size-3.5', !checked && 'opacity-0')} />
                  <span>{currency.code}</span>
                  <span className="ml-2 text-[11px] text-muted-foreground">
                    {currency.symbol} · {currency.label}
                  </span>
                </Button>
              );
            })}
          </div>
        )}
      </div>
    </fieldset>
  );
}
