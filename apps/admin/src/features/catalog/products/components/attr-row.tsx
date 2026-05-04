import { Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { WysiwygEditor } from '@/components/catalog/wysiwyg-editor';
import { type Provenance, ProvenanceBadge } from '@/components/provenance-badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

import type { AttributeMeta, ProductLocale } from './types';

export interface AttrRowProps {
  attribute: AttributeMeta;
  value: unknown;
  provenance: Provenance;
  locale: ProductLocale;
  isEditing: boolean;
  isLocked: boolean;
  onChange: (next: unknown) => void;
}

/**
 * VIEW-07 (#420) — single attribute row mirrored from
 * `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/produkty/detail-view.jsx`
 * lines 25–61. Layout grid `[180px_1fr_auto]`, hover surface, inline
 * input/textarea when the page is in edit mode and the field is not
 * locked or system-owned.
 */
export function AttrRow({
  attribute,
  value,
  provenance,
  locale,
  isEditing,
  isLocked,
  onChange,
}: AttrRowProps) {
  const { t, i18n } = useTranslation();
  const lang = i18n.language === 'pl' ? 'pl' : 'en';
  const label = attribute.label[lang] ?? attribute.code;
  const editable = isEditing && !isLocked;
  const stringValue = typeof value === 'string' ? value : value == null ? '' : String(value);
  const localeChip = isLocaleScoped(attribute) ? locale : null;

  return (
    <div
      className={cn(
        'grid grid-cols-[180px_minmax(0,1fr)_auto] items-start gap-3 rounded-xl px-3 py-2.5',
        'group transition-colors hover:bg-white/60',
      )}
    >
      <div className="flex items-center gap-1.5 pt-1.5 text-[13px] font-medium text-zinc-600">
        <span>{label}</span>
        {localeChip ? (
          <span
            title={t('products.detail.field.locale_aria', {
              locale: localeChip.toUpperCase(),
              defaultValue: 'Locale {{locale}}',
            })}
            className="rounded bg-zinc-100 px-1 py-0.5 font-mono text-[9px] uppercase text-zinc-500"
          >
            {localeChip}
          </span>
        ) : null}
        {isLocked ? <Lock className="size-3 text-zinc-300" aria-hidden /> : null}
        {attribute.is_required_in_group ? (
          <span
            className="text-rose-500"
            title={t('app.required', { defaultValue: 'wymagane' })}
            aria-hidden
          >
            *
          </span>
        ) : null}
      </div>

      <div className="min-w-0">
        {editable ? (
          attribute.type === 'wysiwyg' ? (
            <WysiwygEditor
              value={stringValue}
              onChange={(next) => onChange(next)}
              ariaLabel={label}
            />
          ) : attribute.type === 'richtext' || attribute.type === 'textarea' ? (
            <Textarea
              id={`attr-${attribute.code}`}
              rows={3}
              value={stringValue}
              onChange={(event) => onChange(event.target.value)}
              className="w-full rounded-xl border-zinc-200 bg-white px-3 py-2 text-[13.5px]"
            />
          ) : attribute.type === 'number' ? (
            <Input
              id={`attr-${attribute.code}`}
              type="number"
              value={typeof value === 'number' ? value : stringValue}
              onChange={(event) => {
                const parsed = Number.parseFloat(event.target.value);
                onChange(Number.isNaN(parsed) ? null : parsed);
              }}
              className="w-full rounded-xl border-zinc-200 bg-white px-3 py-2 text-[13.5px]"
            />
          ) : attribute.type === 'boolean' ? (
            <input
              id={`attr-${attribute.code}`}
              type="checkbox"
              checked={value === true}
              onChange={(event) => onChange(event.target.checked)}
              className="size-4 rounded"
            />
          ) : (
            <Input
              id={`attr-${attribute.code}`}
              type="text"
              value={stringValue}
              onChange={(event) => onChange(event.target.value)}
              className="w-full rounded-xl border-zinc-200 bg-white px-3 py-2 text-[13.5px]"
            />
          )
        ) : attribute.type === 'wysiwyg' ? (
          <WysiwygEditor value={stringValue} onChange={() => undefined} readOnly />
        ) : (
          <div
            className={cn(
              'rounded-xl border border-transparent px-3 py-2 text-[13.5px]',
              isLocked ? 'cursor-default text-zinc-700' : 'text-ink',
            )}
          >
            {stringValue === '' ? (
              <span className="italic text-zinc-400">
                {t('products.detail.field.empty', { defaultValue: '—' })}
              </span>
            ) : (
              stringValue
            )}
          </div>
        )}
      </div>

      <div className="flex items-center gap-1 pt-1.5">
        <ProvenanceBadge provenance={provenance} />
      </div>
    </div>
  );
}

function isLocaleScoped(attribute: AttributeMeta): boolean {
  // Heuristic until backend exposes `is_localized` on AttributeMeta:
  // PL/EN suffixed codes (`name_pl`, `description_en`) and richtext/text
  // attributes flagged as system are treated as content surfaces that
  // benefit from a locale chip in the row label.
  if (/_pl$|_en$|_de$|_cs$/i.test(attribute.code)) return true;
  return attribute.type === 'richtext' || attribute.type === 'textarea';
}
