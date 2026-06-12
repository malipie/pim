import { Info } from 'lucide-react';
import type * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { MockBadge } from '@/components/ui/mock-badge';
import type { useImportWizard } from '@/features/imports/hooks/useImportWizard';
import { cn } from '@/lib/utils';

interface StepRulesProps {
  wizard: ReturnType<typeof useImportWizard>;
}

/**
 * NUI-10 (#1429) — Step 4 „Reguły" (design `Import-nowy.html` StepRules).
 * The engine supports exactly ONE mode today: upsert by identifier with
 * per-row validation (dry-run preview on the next step). The truth card
 * states that; every control from the design (ADD/UPDATE mode, validation
 * toggles, per-attribute locks, per-row confirmation) is rendered
 * disabled with a MockBadge — backend follow-ups tracked in
 * Project Plan/UI/Retrofit_v2/importy-do-oprogramowania.md.
 */
export function StepRules({ wizard }: StepRulesProps): React.ReactElement {
  const { t } = useTranslation();
  const { next, back, state, setField } = wizard;

  const validations = [
    {
      l: t('imports.rules.v_type', { defaultValue: 'Type-check (number, date, URL, email)' }),
      on: true,
    },
    {
      l: t('imports.rules.v_required', { defaultValue: 'Pola wymagane (wg modelu ObjectType)' }),
      on: true,
    },
    {
      l: t('imports.rules.v_unique', { defaultValue: 'Unikalność identyfikatora (plik + baza)' }),
      on: true,
    },
    {
      l: t('imports.rules.v_allowed', { defaultValue: 'Dozwolone wartości (select/multiselect)' }),
      on: true,
    },
    {
      l: t('imports.rules.v_range', { defaultValue: 'Range check (zakresy liczbowe)' }),
      on: false,
    },
    {
      l: t('imports.rules.v_regex', { defaultValue: 'Regex per kolumna (np. EAN ^[0-9]{13}$)' }),
      on: false,
    },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-start gap-2.5 rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3">
        <Info className="mt-0.5 size-4 shrink-0 text-zinc-500" aria-hidden />
        <div className="text-[12.5px] leading-relaxed text-zinc-700">
          {t('imports.rules.truth', {
            defaultValue:
              'Tryb importu jest realny (IMP2-1.3): CREATE pomija istniejące, UPDATE pomija brakujące, UPSERT tworzy lub aktualizuje po SKU. Pozostałe przełączniki walidacji pokazują docelowy zakres i czekają na backend.',
          })}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.2fr_1fr]">
        <div className="relative rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm">
          <div className="text-[13.5px] font-semibold">
            {t('imports.rules.mode_title', { defaultValue: 'Tryb importu' })}
          </div>
          <div className="mt-0.5 text-[12px] text-zinc-500">
            {t('imports.rules.mode_subtitle', {
              defaultValue: 'Co system robi, gdy identyfikator jest już w bazie',
            })}
          </div>
          <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
            {[
              {
                id: 'UPSERT',
                desc: t('imports.rules.mode_upsert', {
                  defaultValue: 'Aktualizuj istniejące, twórz nowe (domyślny)',
                }),
              },
              {
                id: 'CREATE',
                desc: t('imports.rules.mode_add', {
                  defaultValue: 'Tylko nowe rekordy · pomiń duplikaty',
                }),
              },
              {
                id: 'UPDATE',
                desc: t('imports.rules.mode_update', {
                  defaultValue: 'Tylko istniejące · nadpisz wartości',
                }),
              },
            ].map((m) => (
              <button
                type="button"
                key={m.id}
                onClick={() => setField('mode', m.id as 'CREATE' | 'UPDATE' | 'UPSERT')}
                aria-pressed={state.mode === m.id}
                className={cn(
                  'rounded-xl border px-3 py-3 text-left',
                  state.mode === m.id
                    ? 'border-zinc-900 bg-zinc-900 text-white'
                    : 'border-zinc-200 bg-white hover:border-zinc-400',
                )}
              >
                <div className="font-mono text-[11px] font-semibold uppercase tracking-wider">
                  {m.id}
                </div>
                <div
                  className={cn(
                    'mt-1.5 text-[12px]',
                    state.mode === m.id ? 'text-white/80' : 'text-zinc-600',
                  )}
                >
                  {m.desc}
                </div>
              </button>
            ))}
          </div>

          <div className="mt-5 text-[13.5px] font-semibold">
            {t('imports.rules.validation_title', { defaultValue: 'Walidacja' })}
          </div>
          <div className="mt-2 space-y-2">
            {validations.map((r) => (
              <label
                key={r.l}
                className="flex cursor-not-allowed items-center gap-2.5 text-[12.5px] opacity-60"
              >
                <input
                  type="checkbox"
                  disabled
                  checked={r.on}
                  readOnly
                  className="h-4 w-4 rounded"
                />
                <span className="text-zinc-700">{r.l}</span>
              </label>
            ))}
          </div>
        </div>

        <div className="relative rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm">
          <MockBadge variant="corner" />
          <div className="text-[13.5px] font-semibold">
            {t('imports.rules.locks_title', { defaultValue: 'Per-attribute lock' })}
          </div>
          <div className="mt-0.5 text-[12px] text-zinc-500">
            {t('imports.rules.locks_subtitle', {
              defaultValue: 'Pola, których import nigdy nie nadpisze (zawsze human-edited)',
            })}
          </div>
          <div className="mt-3 space-y-1.5 opacity-60">
            {['description.pl', 'name.pl', 'image_url'].map((attr, i) => (
              <div
                key={attr}
                className="flex cursor-not-allowed items-center gap-2 rounded-lg px-3 py-2"
              >
                <input
                  type="checkbox"
                  disabled
                  checked={i === 0}
                  readOnly
                  className="h-4 w-4 rounded"
                />
                <span className="flex-1 font-mono text-[12.5px] text-zinc-800">{attr}</span>
                {i === 0 && (
                  <span className="rounded bg-zinc-900 px-1.5 py-0.5 font-mono text-[10.5px] uppercase tracking-wider text-white">
                    lock
                  </span>
                )}
              </div>
            ))}
          </div>

          <div className="mt-5 text-[13.5px] font-semibold">
            {t('imports.rules.confirm_title', { defaultValue: 'Per-row confirmation' })}
          </div>
          <div className="mt-0.5 text-[12px] text-zinc-500">
            {t('imports.rules.confirm_subtitle', {
              defaultValue: 'Wstrzymaj wiersz i zażądaj akceptacji, jeśli…',
            })}
          </div>
          <div className="mt-2 space-y-2 text-[12.5px] opacity-60">
            <label className="flex cursor-not-allowed items-center gap-2.5">
              <input type="checkbox" disabled readOnly className="h-4 w-4 rounded" />
              <span className="text-zinc-700">
                {t('imports.rules.confirm_price', { defaultValue: 'zmiana ceny > 20%' })}
              </span>
            </label>
            <label className="flex cursor-not-allowed items-center gap-2.5">
              <input type="checkbox" disabled readOnly className="h-4 w-4 rounded" />
              <span className="text-zinc-700">
                {t('imports.rules.confirm_fields', {
                  defaultValue: 'zmiana > 10 pól w jednym rekordzie',
                })}
              </span>
            </label>
          </div>
        </div>
      </div>

      <div className="flex justify-between">
        <Button variant="ghost" onClick={() => back()}>
          ← {t('imports.wizard.back', { defaultValue: 'Wstecz' })}
        </Button>
        <Button onClick={() => next()}>
          {t('imports.wizard.next', { defaultValue: 'Dalej →' })}
        </Button>
      </div>
    </div>
  );
}
