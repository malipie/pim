import { useInvalidate } from '@refinedev/core';
import { useQueryClient } from '@tanstack/react-query';
import { Check, GripVertical, Info, X, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { HttpError, jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

const TYPES = [
  'text',
  'number',
  'select',
  'multiselect',
  'date',
  'datetime',
  'boolean',
  'asset',
  'reference',
  'relation',
  'price',
  'metric',
  'wysiwyg',
] as const;

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Empty string in pick-only mode (wizard, OT not yet created). */
  objectTypeId: string;
  objectTypeName: string;
  /**
   * Called after the attribute is created (and attached, if `objectTypeId`
   * is non-empty). The wizard uses this to push the new attribute UUID
   * onto its `pickedAttributeIds` set so the post-create bulk-attach
   * picks it up too.
   */
  onCreated: (attribute: { id: string; code: string }) => void;
}

interface CreatedAttributeResponse {
  id?: string;
  '@id'?: string;
}

/**
 * VIEW-01b (#413) — create a new Attribute and attach it directly to an
 * ObjectType (no AttributeGroup intermediary). Two-step flow:
 *  1. `POST /api/attributes` — create in the global library.
 *  2. `POST /api/object_types/{id}/attributes/{newAttrId}` — direct attach.
 *
 * Mirrors `CreateAttributeInGroupDialog` but step 2 hits the OT junction.
 * Sequential calls so a unique-code 422 from step 1 surfaces before we
 * touch the junction.
 */
export function CreateAttributeForObjectTypeDialog({
  open,
  onOpenChange,
  objectTypeId,
  objectTypeName,
  onCreated,
}: Props) {
  const { t } = useTranslation();
  const invalidate = useInvalidate();
  const queryClient = useQueryClient();
  const [code, setCode] = useState('');
  const [namePl, setNamePl] = useState('');
  const [nameEn, setNameEn] = useState('');
  const [type, setType] = useState<(typeof TYPES)[number]>('text');
  const [unit, setUnit] = useState('');
  const [required, setRequired] = useState(false);
  const [unique, setUnique] = useState(false);
  const [localizable, setLocalizable] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [activeLocale, setActiveLocale] = useState<'pl' | 'en'>('pl');

  useEffect(() => {
    if (open) {
      setCode('');
      setNamePl('');
      setNameEn('');
      setType('text');
      setUnit('');
      setRequired(false);
      setUnique(false);
      setLocalizable(false);
      setError(null);
      setActiveLocale('pl');
    }
  }, [open]);

  const showUnit = type === 'number' || type === 'price' || type === 'metric';
  const showOptionsBanner = type === 'select' || type === 'multiselect';
  const valid = code.trim().length > 0 && namePl.trim().length > 0;

  const submit = async () => {
    if (!valid) return;
    setSubmitting(true);
    setError(null);
    try {
      const label: Record<string, string> = { pl: namePl.trim() };
      if (nameEn.trim().length > 0) label.en = nameEn.trim();

      const body: Record<string, unknown> = {
        code: code.trim(),
        type,
        label,
        required,
      };
      if (unit.trim().length > 0) body.unit = unit.trim();

      const created = await jsonFetch<CreatedAttributeResponse>('/api/attributes', {
        method: 'POST',
        contentType: 'application/ld+json',
        accept: 'application/ld+json',
        body,
      });

      const newId =
        typeof created.id === 'string' && created.id.length > 0
          ? created.id
          : typeof created['@id'] === 'string'
            ? (created['@id'].split('/').pop() ?? '')
            : '';

      if (newId.length === 0) {
        throw new Error(
          t('modeling.objectTypes.create_attribute.missing_id', {
            defaultValue: 'API nie zwróciło ID nowego atrybutu.',
          }),
        );
      }

      // Skip the attach step in pick-only mode (wizard collects IDs and
      // bulk-attaches after the OT is created).
      if (objectTypeId.length > 0) {
        await jsonFetch(`/api/object_types/${objectTypeId}/attributes/${newId}`, {
          method: 'POST',
        });
      }

      await Promise.all([
        invalidate({ resource: 'attributes', invalidates: ['list', 'many'] }),
        queryClient.invalidateQueries({
          predicate: (query) => {
            const key = query.queryKey;
            if (!Array.isArray(key)) return false;
            return key.includes('attributes') || key.includes('object_types');
          },
        }),
      ]);

      onCreated({ id: newId, code: code.trim() });
      onOpenChange(false);
    } catch (err) {
      if (err instanceof HttpError) {
        const detail =
          err.body && typeof err.body === 'object' && 'detail' in err.body
            ? String((err.body as Record<string, unknown>).detail)
            : null;
        setError(detail ?? `HTTP ${err.status}`);
      } else {
        setError(
          err instanceof Error
            ? err.message
            : t('modeling.objectTypes.create_attribute.error', {
                defaultValue: 'Nie udało się utworzyć atrybutu',
              }),
        );
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[88vh] max-w-[820px] flex-col gap-0 p-0">
        <div className="flex items-start gap-3 border-b border-zinc-100 px-7 pb-4 pt-6">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-violet-100 text-violet-700">
            <Zap className="size-4" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="font-display text-[18px] font-semibold tracking-tight">
              {t('modeling.objectTypes.create_attribute.title', {
                defaultValue: 'Nowy atrybut na typie „{{name}}"',
                name: objectTypeName,
              })}
            </div>
            <div className="mt-0.5 text-[12.5px] text-muted-foreground">
              {t('modeling.objectTypes.create_attribute.desc', {
                defaultValue:
                  'Atrybut zostanie utworzony w globalnej bibliotece i automatycznie dołączony bezpośrednio do tego typu (poza grupą atrybutów).',
              })}
            </div>
          </div>
          <button
            type="button"
            onClick={() => onOpenChange(false)}
            className="grid size-9 shrink-0 place-items-center rounded-xl text-muted-foreground hover:bg-zinc-100"
            aria-label={t('app.close', { defaultValue: 'Zamknij' })}
          >
            <X className="size-4" />
          </button>
        </div>

        <div className="space-y-6 overflow-y-auto px-7 py-5">
          <Section
            title={t('modeling.objectTypes.create_attribute.section_identification', {
              defaultValue: 'Identyfikacja',
            })}
          >
            <div className="grid grid-cols-2 gap-x-6 gap-y-4">
              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground">Code</Label>
                <Input
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  placeholder="np. plan_tier"
                  className="mt-1.5 h-10 font-mono"
                />
                <p className="mt-1 text-[11px] text-muted-foreground">
                  snake_case · niezmienialny po utworzeniu
                </p>
              </div>
              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground">
                  Typ danych
                </Label>
                <select
                  value={type}
                  onChange={(e) => setType(e.target.value as (typeof TYPES)[number])}
                  className="mt-1.5 h-10 w-full rounded-xl border border-zinc-200 bg-white px-3 text-[13px] font-medium"
                >
                  {TYPES.map((opt) => (
                    <option key={opt} value={opt}>
                      {opt}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="mt-4">
              <Label className="text-[11.5px] font-medium text-muted-foreground">
                {t('attributes.fields.name', { defaultValue: 'Nazwa wyświetlana' })}
              </Label>
              <div className="mt-1.5 flex items-center gap-1 border-b border-zinc-100">
                {(['pl', 'en'] as const).map((lc) => {
                  const filled = (lc === 'pl' ? namePl : nameEn).trim().length > 0;
                  return (
                    <button
                      key={lc}
                      type="button"
                      onClick={() => setActiveLocale(lc)}
                      className={cn(
                        '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-[12.5px] font-medium uppercase tracking-wider transition',
                        activeLocale === lc
                          ? 'border-zinc-900 text-foreground'
                          : 'border-transparent text-muted-foreground hover:text-foreground',
                      )}
                    >
                      <span>{lc === 'pl' ? '🇵🇱' : '🇬🇧'}</span>
                      <span>{lc}</span>
                      {!filled ? (
                        <span className="size-1.5 rounded-full bg-amber-400" aria-hidden />
                      ) : null}
                    </button>
                  );
                })}
              </div>
              <Input
                className="mt-2"
                value={activeLocale === 'pl' ? namePl : nameEn}
                onChange={(e) =>
                  activeLocale === 'pl' ? setNamePl(e.target.value) : setNameEn(e.target.value)
                }
                placeholder="np. Poziom planu"
              />
            </div>
          </Section>

          {showUnit || showOptionsBanner ? (
            <Section
              title={t('modeling.objectTypes.create_attribute.section_type_config', {
                defaultValue: 'Konfiguracja typu',
              })}
              divider
            >
              {showUnit ? (
                <div>
                  <Label className="text-[11.5px] font-medium text-muted-foreground">
                    Jednostka
                  </Label>
                  <Input
                    value={unit}
                    onChange={(e) => setUnit(e.target.value)}
                    placeholder={type === 'price' ? 'PLN' : 'kg / mm / V'}
                    className="mt-1.5 h-10 max-w-[260px]"
                  />
                </div>
              ) : null}
              {showOptionsBanner ? (
                <div className="flex items-start gap-2.5 rounded-xl border border-violet-200 bg-violet-50/60 px-4 py-3">
                  <Info className="size-4 shrink-0 text-violet-700" />
                  <div className="text-[12px] text-violet-900">
                    Po utworzeniu atrybutu typu <span className="font-mono">{type}</span> będziesz
                    mógł zdefiniować wartości (z tłumaczeniami) w widoku „Zarządzaj wartościami".
                  </div>
                </div>
              ) : null}
            </Section>
          ) : null}

          <Section
            title={t('modeling.objectTypes.create_attribute.section_validation', {
              defaultValue: 'Walidacja i flagi',
            })}
            divider
          >
            <div className="grid grid-cols-3 gap-3">
              <FlagCard
                label="Required"
                desc="Pole musi być wypełnione"
                checked={required}
                onChange={setRequired}
              />
              <FlagCard
                label="Unique"
                desc="Wartość unikalna w typie"
                checked={unique}
                onChange={setUnique}
              />
              <FlagCard
                label="Localizable"
                desc="Per locale (PL/EN/DE)"
                checked={localizable}
                onChange={setLocalizable}
              />
            </div>
          </Section>

          <Section
            title={t('modeling.objectTypes.create_attribute.section_preview', {
              defaultValue: 'Podgląd na typie',
            })}
            divider
          >
            <div className="grid grid-cols-[24px_1fr_120px_140px] items-center gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3">
              <GripVertical className="size-4 text-zinc-300" />
              <div className="min-w-0">
                <div className="truncate font-mono text-[13px] font-medium">
                  {code.trim() || 'attribute_code'}
                </div>
                <div className="truncate text-[11.5px] text-muted-foreground">
                  {namePl.trim() || 'Nazwa atrybutu…'}
                  {unit.trim().length > 0 ? ` (${unit.trim()})` : ''}
                </div>
              </div>
              <span className="rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium uppercase text-muted-foreground">
                {type}
              </span>
              <div className="flex flex-wrap items-center justify-end gap-1.5">
                {required ? <Chip className="bg-rose-50 text-rose-700">required</Chip> : null}
                {unique ? <Chip className="bg-blue-50 text-blue-700">unique</Chip> : null}
                {localizable ? <Chip className="bg-violet-50 text-violet-700">i18n</Chip> : null}
              </div>
            </div>
          </Section>
        </div>

        <div className="flex items-center justify-between border-t border-zinc-100 bg-zinc-50/60 px-7 py-4">
          <div className="text-[11.5px]">
            {error !== null ? (
              <span className="text-destructive">{error}</span>
            ) : valid ? (
              <span className="text-muted-foreground">
                Audit log: <span className="font-mono">attribute.create</span> +{' '}
                <span className="font-mono">object_type.attribute.attach</span>
              </span>
            ) : (
              <span className="text-amber-700">Wymagane: code i nazwa PL</span>
            )}
          </div>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9 rounded-xl"
              onClick={() => onOpenChange(false)}
              disabled={submitting}
            >
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button
              type="button"
              size="sm"
              disabled={!valid || submitting}
              onClick={() => {
                void submit();
              }}
              className="h-9 rounded-xl bg-zinc-900 hover:bg-zinc-800"
            >
              <Check className="size-4" />
              {t('modeling.objectTypes.create_attribute.submit_action', {
                defaultValue: 'Utwórz i dołącz',
              })}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function Section({
  title,
  divider,
  children,
}: {
  title: string;
  divider?: boolean;
  children: React.ReactNode;
}) {
  return (
    <div className={cn(divider ? 'border-t border-zinc-100 pt-5' : '')}>
      <div className="mb-3 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
        {title}
      </div>
      {children}
    </div>
  );
}

function FlagCard({
  label,
  desc,
  checked,
  onChange,
}: {
  label: string;
  desc: string;
  checked: boolean;
  onChange: (next: boolean) => void;
}) {
  return (
    <button
      type="button"
      onClick={() => onChange(!checked)}
      className={cn(
        'flex flex-col gap-0.5 rounded-xl border px-3 py-2.5 text-left transition',
        checked
          ? 'border-emerald-200 bg-emerald-50/50'
          : 'border-zinc-200 bg-white hover:bg-zinc-50',
      )}
    >
      <div className="flex items-center gap-2 text-[13px] font-medium">
        {label}
        <span
          className={cn(
            'ml-auto text-[10px] font-mono uppercase tracking-wider',
            checked ? 'text-emerald-700' : 'text-muted-foreground',
          )}
        >
          {checked ? 'ON' : 'OFF'}
        </span>
      </div>
      <div className="text-[11.5px] text-muted-foreground">{desc}</div>
    </button>
  );
}

function Chip({ className, children }: { className?: string; children: React.ReactNode }) {
  return (
    <span
      className={cn(
        'rounded px-1.5 py-0.5 text-[9.5px] font-semibold uppercase tracking-wider',
        className,
      )}
    >
      {children}
    </span>
  );
}
