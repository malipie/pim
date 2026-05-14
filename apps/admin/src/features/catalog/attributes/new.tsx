import { useInvalidate } from '@refinedev/core';
import { useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, FolderPlus, FolderTree, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';

import { CreateGroupInlineDialog } from '@/components/modeling/create-group-inline-dialog';
import { PickGroupsForAttributeDialog } from '@/components/modeling/pick-groups-for-attribute-dialog';
import { SettingToggleRow } from '@/components/modeling/setting-toggle-row';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { HttpError, jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * VIEW-02 (#374) — pixel-perfect rebuild of `NewAttributeView`
 * (`attributes.jsx:352–448`).
 *
 * Layout:
 *   - Back link "Wstecz do biblioteki Attributes".
 *   - Header (flex justify-between): caption "Nowy Attribute" + big
 *     mono live `attribute_code` + description; right stack
 *     "Anuluj | + Utwórz atrybut".
 *   - Grid 1fr+320px:
 *     - Left Card (p-6 space-y-6) with three sections:
 *       * Identyfikacja: Code input + Nazwa PL/EN + Opis (PL/EN).
 *       * Typ danych: 4-col tile picker (10 enum values), font-mono.
 *       * Walidacja: 3 SettingToggleRow (Required / Unique / Indexed).
 *     - Right aside: Card "Podgląd" (live code + TypeBadge + name)
 *       + Card "Następnie" (3 next steps).
 *
 * POSTs `/api/attributes` (#381) and redirects to the show page on
 * success. The "Indexed" toggle is non-persistent in MVP — backend
 * has no `is_indexed` column; flag stays in form for visual parity.
 */

interface CreatePayload {
  code: string;
  labelPl: string;
  labelEn: string;
  helpPl: string;
  helpEn: string;
  type: string;
  required: boolean;
  unique: boolean;
  indexed: boolean;
  filterable: boolean;
}

const EMPTY: CreatePayload = {
  code: '',
  labelPl: '',
  labelEn: '',
  helpPl: '',
  helpEn: '',
  type: 'text',
  required: false,
  unique: false,
  indexed: false,
  filterable: false,
};

const TYPES = [
  'text',
  'number',
  'select',
  'multiselect',
  'date',
  'boolean',
  'asset',
  'relation',
  'price',
  'metric',
  'wysiwyg',
] as const;

export function AttributeCreatePage() {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const invalidate = useInvalidate();
  const queryClient = useQueryClient();
  const [values, setValues] = useState<CreatePayload>(EMPTY);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Reverse-direction "+Z grupy" / "+Stwórz grupę" — operator picks (or
  // creates) groups while building the attribute. After POST /api/attributes
  // succeeds we POST bulk-attach for each picked group.
  const [pickedGroupCodes, setPickedGroupCodes] = useState<Set<string>>(new Set());
  const [pickerOpen, setPickerOpen] = useState(false);
  const [createGroupOpen, setCreateGroupOpen] = useState(false);

  const handleSubmit = async () => {
    setError(null);
    setSubmitting(true);
    try {
      const body: Record<string, unknown> = {
        code: values.code,
        label: stripEmpty({ pl: values.labelPl, en: values.labelEn }),
        type: values.type,
        required: values.required,
        filterable: values.filterable,
      };
      const help = stripEmpty({ pl: values.helpPl, en: values.helpEn });
      if (Object.keys(help).length > 0) body.help = help;
      // `unique` is not in AttributeInput — surfaced via `validationRules`
      // when the BE adds it. For now keep it form-only so the FE matches
      // the mockup; submit-side stays no-op.
      // `indexed` is similarly form-only (no `is_indexed` BE column yet).

      const response = await jsonFetch<{ id?: string }>('/api/attributes', {
        method: 'POST',
        contentType: 'application/ld+json',
        accept: 'application/ld+json',
        body,
      });

      // Reverse-direction attach: for each picked group, fan out a bulk-attach
      // call with this single new attribute. Sequential to keep BE audit log
      // ordering deterministic; failures bubble up as the same error message.
      if (pickedGroupCodes.size > 0) {
        const groupsList = await jsonFetch<{
          member?: Array<{ id: string; code: string }>;
        }>('/api/attribute_groups?itemsPerPage=200');
        const codeToId = new Map<string, string>();
        for (const g of groupsList.member ?? []) codeToId.set(g.code, g.id);
        for (const code of pickedGroupCodes) {
          const groupId = codeToId.get(code);
          if (groupId === undefined) continue;
          await jsonFetch(`/api/attribute_groups/${groupId}/attributes/bulk-attach`, {
            method: 'POST',
            contentType: 'application/json',
            accept: 'application/json',
            body: { attributeCodes: [values.code] },
          });
        }
      }

      // Drop the cached list so the new row shows up after redirect.
      // Belt-and-suspenders: Refine's useInvalidate covers its own cache
      // keys (`['data','attributes','list']`); the queryClient fallback
      // wipes any custom React Query keys (e.g. usage prefetch caches)
      // that might also be holding a stale snapshot. Both are awaited so
      // the navigate below races with a fresh refetch, not a pending one.
      await Promise.all([
        invalidate({ resource: 'attributes', invalidates: ['list', 'many'] }),
        queryClient.invalidateQueries({
          predicate: (query) => {
            const key = query.queryKey;
            if (!Array.isArray(key)) return false;
            return key.includes('attributes') || key[0] === 'attribute_options';
          },
        }),
      ]);
      if (typeof response.id === 'string' && response.id !== '') {
        navigate(`/modeling/attributes/${response.id}`, { replace: true });
      } else {
        navigate('/modeling/attributes', { replace: true });
      }
    } catch (err) {
      if (err instanceof HttpError) {
        const detail =
          err.body && typeof err.body === 'object' && 'detail' in err.body
            ? String((err.body as Record<string, unknown>).detail)
            : null;
        setError(detail ?? `HTTP ${err.status}`);
      } else {
        setError(t('attributes.create_error', { defaultValue: 'Nie udało się utworzyć atrybutu' }));
      }
    } finally {
      setSubmitting(false);
    }
  };

  const valid = values.code.trim().length > 0 && values.labelPl.trim().length > 0;

  return (
    <div className="space-y-6">
      <Link
        to="/modeling/attributes"
        className="inline-flex items-center gap-1.5 text-[12.5px] font-medium text-muted-foreground hover:text-foreground"
      >
        <ArrowLeft className="size-4" />
        {t('attributes.back_to_library', { defaultValue: 'Wstecz do biblioteki Attributes' })}
      </Link>

      <div className="flex flex-wrap items-start justify-between gap-6">
        <div className="flex-1 min-w-0">
          <div className="text-[13px] font-medium text-muted-foreground">
            {t('attributes.create_caption', { defaultValue: 'Nowy Attribute' })}
          </div>
          <h1 className="font-display font-mono text-[28px] font-semibold tracking-tight">
            {values.code.trim().length > 0
              ? values.code.trim()
              : t('attributes.create_code_placeholder', { defaultValue: 'attribute_code' })}
          </h1>
          <p className="mt-1 max-w-2xl text-[13px] text-muted-foreground">
            {t('attributes.create_description', {
              defaultValue:
                'Atrybut to typowane pole, które można dołączać do ObjectType lub Attribute Group. Po utworzeniu pojawi się w globalnej bibliotece.',
            })}
          </p>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <Button asChild variant="ghost" className="h-9 rounded-xl px-3 text-[13px]">
            <Link to="/modeling/attributes">{t('app.cancel')}</Link>
          </Button>
          <Button
            type="button"
            disabled={!valid || submitting}
            onClick={() => {
              void handleSubmit();
            }}
            className="h-9 rounded-xl bg-zinc-900 px-4 text-[13px] hover:bg-zinc-800"
          >
            <Check className="size-4" />
            {t('attributes.create_submit', { defaultValue: 'Utwórz atrybut' })}
          </Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <Card className="space-y-6 p-6">
          <Section title={t('attributes.identification_title', { defaultValue: 'Identyfikacja' })}>
            <div className="space-y-4">
              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground" htmlFor="code">
                  {t('attributes.fields.code', { defaultValue: 'Code' })}
                </Label>
                <Input
                  id="code"
                  value={values.code}
                  onChange={(e) => setValues({ ...values, code: e.target.value })}
                  pattern="[a-z][a-z0-9_]*"
                  placeholder="np. warranty_months"
                  className="mt-1.5 h-10 font-mono"
                />
                <p className="mt-1 text-[11px] text-muted-foreground">
                  {t('attributes.code_helper', {
                    defaultValue: 'snake_case · niezmienialny po utworzeniu',
                  })}
                </p>
              </div>
              <LocaleField
                label={t('attributes.fields.name', { defaultValue: 'Nazwa' })}
                placeholder={t('attributes.fields.name_placeholder', {
                  defaultValue: 'np. Gwarancja (msc)',
                })}
                values={{ pl: values.labelPl, en: values.labelEn }}
                onChange={(locale, next) => {
                  if (locale === 'pl') setValues({ ...values, labelPl: next });
                  else setValues({ ...values, labelEn: next });
                }}
              />
              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground">
                  {t('attributes.fields.help', { defaultValue: 'Opis (opcjonalny)' })}
                </Label>
                <div className="mt-1.5 grid gap-2 sm:grid-cols-2">
                  <Textarea
                    rows={2}
                    value={values.helpPl}
                    onChange={(e) => setValues({ ...values, helpPl: e.target.value })}
                    placeholder="PL · krótki opis dla zespołu"
                  />
                  <Textarea
                    rows={2}
                    value={values.helpEn}
                    onChange={(e) => setValues({ ...values, helpEn: e.target.value })}
                    placeholder="EN · short description for the team"
                  />
                </div>
              </div>
            </div>
          </Section>

          <Section title={t('attributes.type_title', { defaultValue: 'Typ danych' })}>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
              {TYPES.map((type) => (
                <button
                  key={type}
                  type="button"
                  onClick={() => setValues({ ...values, type })}
                  className={cn(
                    'h-10 rounded-xl font-mono text-[12px] font-medium transition',
                    values.type === type
                      ? 'bg-zinc-900 text-white'
                      : 'border border-zinc-200 bg-white text-muted-foreground hover:bg-zinc-50',
                  )}
                >
                  {type}
                </button>
              ))}
            </div>
          </Section>

          <Section title={t('attributes.validation_title', { defaultValue: 'Walidacja i flagi' })}>
            <div className="space-y-3">
              <SettingToggleRow
                label={t('attributes.flags.required_label', { defaultValue: 'Required' })}
                description={t('attributes.flags.required_desc', {
                  defaultValue: 'Pole musi być wypełnione',
                })}
                checked={values.required}
                onChange={(next) => setValues({ ...values, required: next })}
              />
              <SettingToggleRow
                label={t('attributes.flags.unique_label', { defaultValue: 'Unique' })}
                description={t('attributes.flags.unique_desc', {
                  defaultValue: 'Wartość unikalna w obrębie ObjectType',
                })}
                checked={values.unique}
                onChange={(next) => setValues({ ...values, unique: next })}
              />
              <SettingToggleRow
                label={t('attributes.flags.indexed_label', { defaultValue: 'Indexed' })}
                description={t('attributes.flags.indexed_desc', {
                  defaultValue: 'Indeks dla wyszukiwania',
                })}
                checked={values.indexed}
                onChange={(next) => setValues({ ...values, indexed: next })}
              />
              <SettingToggleRow
                label={t('attributes.flags.filterable_label', { defaultValue: 'Filtrowalny' })}
                description={t('attributes.flags.filterable_desc', {
                  defaultValue:
                    'Pojawia się w panelu Filtruj zaawansowane. Reindex wymaga zmian ustawień Meilisearch.',
                })}
                checked={values.filterable}
                onChange={(next) => setValues({ ...values, filterable: next })}
              />
            </div>
          </Section>

          <Section
            title={t('modeling.attributes.attach_groups_title', { defaultValue: 'Dołącz do grup' })}
          >
            <div className="space-y-3">
              <div className="flex flex-wrap items-center gap-2">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => setPickerOpen(true)}
                  className="h-8 rounded-lg px-2.5 text-[12px]"
                >
                  <FolderTree className="size-3.5" />
                  {t('modeling.attributes.attach_from_groups_action', {
                    defaultValue: 'Z grupy',
                  })}
                </Button>
                <Button
                  type="button"
                  size="sm"
                  onClick={() => setCreateGroupOpen(true)}
                  className="h-8 rounded-lg bg-violet-50 px-2.5 text-[12px] text-violet-700 hover:bg-violet-100"
                >
                  <FolderPlus className="size-3.5" />
                  {t('modeling.attributes.attach_create_group_action', {
                    defaultValue: 'Stwórz grupę',
                  })}
                </Button>
              </div>
              {pickedGroupCodes.size === 0 ? (
                <p className="text-[11.5px] text-muted-foreground">
                  {t('modeling.attributes.attach_groups_hint', {
                    defaultValue:
                      'Opcjonalnie — wybierz lub utwórz grupy do których atrybut zostanie dołączony po utworzeniu.',
                  })}
                </p>
              ) : (
                <div className="flex flex-wrap gap-1.5">
                  {Array.from(pickedGroupCodes).map((code) => (
                    <span
                      key={code}
                      className="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1 font-mono text-[11.5px] text-zinc-700"
                    >
                      {code}
                      <button
                        type="button"
                        onClick={() => {
                          setPickedGroupCodes((prev) => {
                            const next = new Set(prev);
                            next.delete(code);
                            return next;
                          });
                        }}
                        aria-label={t('app.remove', { defaultValue: 'Usuń' })}
                        className="grid size-3.5 place-items-center rounded text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700"
                      >
                        <X className="size-3" />
                      </button>
                    </span>
                  ))}
                </div>
              )}
            </div>
          </Section>

          {error !== null ? (
            <p className="rounded-md border border-destructive/50 bg-destructive/5 px-3 py-2 text-sm text-destructive">
              {error}
            </p>
          ) : null}
        </Card>

        <aside className="space-y-3">
          <Card className="p-5">
            <div className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
              {t('attributes.preview_title', { defaultValue: 'Podgląd' })}
            </div>
            <div className="mt-3 space-y-1.5">
              <div className="flex items-center gap-2">
                <span className="font-mono text-[13px] font-semibold">
                  {values.code.trim().length > 0 ? values.code.trim() : 'code…'}
                </span>
                <span className="rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium uppercase text-muted-foreground">
                  {values.type}
                </span>
              </div>
              <div className="text-[12px] text-muted-foreground">
                {values.labelPl.trim() || values.labelEn.trim() || 'Nazwa atrybutu…'}
              </div>
            </div>
          </Card>
          <Card className="p-5">
            <div className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
              {t('attributes.next_title', { defaultValue: 'Następnie' })}
            </div>
            <ul className="mt-3 space-y-1.5 text-[12px] text-muted-foreground">
              <li>1. {t('attributes.next_step_1', { defaultValue: 'Utwórz atrybut' })}</li>
              <li>
                2.{' '}
                {t('attributes.next_step_2', {
                  defaultValue: 'Dołącz do Attribute Group lub ObjectType',
                })}
              </li>
              <li>
                3.{' '}
                {t('attributes.next_step_3', {
                  defaultValue: 'Ustaw mapowania na kanały (Shopify, Allegro)',
                })}
              </li>
            </ul>
          </Card>
        </aside>
      </div>

      <PickGroupsForAttributeDialog
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        initialPicked={pickedGroupCodes}
        onConfirm={(codes) => setPickedGroupCodes(codes)}
        locale={i18n.language}
      />
      <CreateGroupInlineDialog
        open={createGroupOpen}
        onOpenChange={setCreateGroupOpen}
        onCreated={(group) => {
          setPickedGroupCodes((prev) => {
            const next = new Set(prev);
            next.add(group.code);
            return next;
          });
        }}
      />
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="mb-4 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
        {title}
      </div>
      {children}
    </div>
  );
}

function stripEmpty(record: Record<string, string>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(record)) {
    if (v.trim() !== '') out[k] = v;
  }
  return out;
}

const NEW_ATTRIBUTE_LOCALES: Array<{ code: 'pl' | 'en'; flag: string }> = [
  { code: 'pl', flag: '🇵🇱' },
  { code: 'en', flag: '🇬🇧' },
];

function LocaleField({
  label,
  placeholder,
  values,
  onChange,
}: {
  label: string;
  placeholder?: string;
  values: { pl: string; en: string };
  onChange: (locale: 'pl' | 'en', next: string) => void;
}) {
  const [active, setActive] = useState<'pl' | 'en'>('pl');
  return (
    <div>
      <Label className="text-[11.5px] font-medium text-muted-foreground">{label}</Label>
      <div className="mt-1.5 flex items-center gap-1 border-b border-zinc-100">
        {NEW_ATTRIBUTE_LOCALES.map(({ code, flag }) => {
          const filled = values[code].trim().length > 0;
          return (
            <button
              key={code}
              type="button"
              onClick={() => setActive(code)}
              className={cn(
                '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-[12.5px] font-medium uppercase tracking-wider transition',
                active === code
                  ? 'border-zinc-900 text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground',
              )}
            >
              <span aria-hidden>{flag}</span>
              <span>{code}</span>
              {!filled ? <span className="size-1.5 rounded-full bg-amber-400" aria-hidden /> : null}
            </button>
          );
        })}
      </div>
      <Input
        className="mt-2"
        value={values[active]}
        onChange={(e) => onChange(active, e.target.value)}
        placeholder={placeholder}
      />
    </div>
  );
}
