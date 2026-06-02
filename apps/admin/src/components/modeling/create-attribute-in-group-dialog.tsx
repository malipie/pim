import { useInvalidate } from '@refinedev/core';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, GripVertical, Info, X, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  RelationConfigPanel,
  type RelationConfigValue,
} from '@/components/modeling/relation-config-panel';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { HttpError, jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * Default relation config (#949) — operator picks targets + cardinality
 * inline via `RelationConfigPanel` before POST.
 */
const DEFAULT_RELATION_CONFIG: RelationConfigValue = {
  targetObjectTypeIds: [],
  cardinality: 'many',
  advanced: false,
  advancedFields: [],
  previewFields: [],
};

interface ObjectTypePickerRow {
  id: string;
  code: string;
  kind: string;
  label?: Record<string, string> | string | null;
}

const TYPES = [
  'text',
  'textarea',
  'identifier',
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
  'color',
  'email',
] as const;

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  groupId: string;
  groupName: string;
  /** Called after attribute is created and attached so the caller refreshes. */
  onCreated: () => void;
}

export function CreateAttributeInGroupDialog({
  open,
  onOpenChange,
  groupId,
  groupName,
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
  // #949 — relation config inline. Reset on dialog open to keep state
  // bounded to the current creation flow.
  const [relationConfig, setRelationConfig] =
    useState<RelationConfigValue>(DEFAULT_RELATION_CONFIG);

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
      setRelationConfig(DEFAULT_RELATION_CONFIG);
    }
  }, [open]);

  // #949 — ObjectTypes list for the relation panel's target multi-select.
  // Only fired when the operator selects `type=relation`.
  const objectTypesQuery = useQuery<ObjectTypePickerRow[]>({
    queryKey: ['relation-config', 'object_types'],
    queryFn: async () => {
      const data = await jsonFetch<{ member?: ObjectTypePickerRow[] }>(
        '/api/object_types?itemsPerPage=200',
      );
      return data.member ?? [];
    },
    staleTime: 60_000,
    enabled: open && type === 'relation',
  });

  const showUnit = type === 'number' || type === 'price' || type === 'metric';
  const showOptionsBanner = type === 'select' || type === 'multiselect';
  const valid =
    code.trim().length > 0 &&
    namePl.trim().length > 0 &&
    (type !== 'relation' || relationConfig.targetObjectTypeIds.length > 0);

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

      // #949 — relation config fields go through the same POST body.
      // Backend validator requires `relationCardinality` + targets on POST
      // for `type=relation`. Non-relation types skip these entirely.
      if (type === 'relation') {
        body.relationTargetObjectTypeIds = relationConfig.targetObjectTypeIds;
        body.relationCardinality = relationConfig.cardinality;
        body.relationAdvanced = relationConfig.advanced;
        if (relationConfig.advanced) {
          body.validationRules = {
            advanced_fields: relationConfig.advancedFields.filter((f) => f.code.trim() !== ''),
          };
        }
        const preview = relationConfig.previewFields.map((c) => c.trim()).filter((c) => c !== '');
        if (preview.length > 0) body.relationPreviewFields = preview;
      }

      // Step 1: create the attribute in the global library.
      await jsonFetch('/api/attributes', {
        method: 'POST',
        contentType: 'application/ld+json',
        accept: 'application/ld+json',
        body,
      });

      // Step 2: attach to the parent group via the existing bulk-attach endpoint.
      // Two calls because the BE has no atomic create-and-attach yet — keeping
      // it explicit means a unique-code 422 from step 1 surfaces before we
      // touch the junction.
      await jsonFetch(`/api/attribute_groups/${groupId}/attributes/bulk-attach`, {
        method: 'POST',
        contentType: 'application/json',
        accept: 'application/json',
        body: { attributeCodes: [code.trim()] },
      });

      await Promise.all([
        invalidate({ resource: 'attributes', invalidates: ['list', 'many'] }),
        queryClient.invalidateQueries({
          predicate: (query) => {
            const key = query.queryKey;
            if (!Array.isArray(key)) return false;
            return key.includes('attributes') || key.includes('attribute_groups');
          },
        }),
      ]);

      onCreated();
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
          t('modeling.attributeGroups.create_in_group.error', {
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
              {t('modeling.attributeGroups.create_in_group.title', {
                defaultValue: 'Nowy atrybut w grupie „{{group}}"',
                group: groupName,
              })}
            </div>
            <div className="mt-0.5 text-[12.5px] text-muted-foreground">
              {t('modeling.attributeGroups.create_in_group.desc', {
                defaultValue:
                  'Atrybut zostanie utworzony w globalnej bibliotece i automatycznie dołączony do tej grupy.',
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
            title={t('modeling.attributeGroups.create_in_group.section_identification', {
              defaultValue: 'Identyfikacja',
            })}
          >
            <div className="grid grid-cols-2 gap-x-6 gap-y-4">
              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground">Code</Label>
                <Input
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  placeholder="np. warranty_months"
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
                      {t(`attribute_type.${opt}`, { defaultValue: opt })}
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
                placeholder="np. Gwarancja (msc)"
              />
            </div>
          </Section>

          {showUnit || showOptionsBanner ? (
            <Section
              title={t('modeling.attributeGroups.create_in_group.section_type_config', {
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

          {/* #949 — relation config inline. Required so the backend
              validator doesn't 422 on POST for `type=relation`. */}
          {type === 'relation' ? (
            <Section
              title={t('attributes.relation_config_section', {
                defaultValue: 'Konfiguracja relacji',
              })}
              divider
            >
              <RelationConfigPanel
                value={relationConfig}
                objectTypes={objectTypesQuery.data ?? []}
                disabled={submitting}
                onChange={setRelationConfig}
              />
              {relationConfig.targetObjectTypeIds.length === 0 ? (
                <p className="mt-2 text-[12px] text-amber-600">
                  {t('attributes.relation_targets_required_hint', {
                    defaultValue: 'Wybierz co najmniej jeden ObjectType celu relacji.',
                  })}
                </p>
              ) : null}
            </Section>
          ) : null}

          <Section
            title={t('modeling.attributeGroups.create_in_group.section_validation', {
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
            title={t('modeling.attributeGroups.create_in_group.section_preview', {
              defaultValue: 'Podgląd w grupie',
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
                <span className="font-mono">group.attach</span>
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
              {t('modeling.attributeGroups.create_in_group.submit_action', {
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
