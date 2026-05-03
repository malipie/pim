import { ArrowLeft, Check } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';

import { ATTRIBUTE_GROUP_SWATCHES, ColorPicker } from '@/components/modeling/color-picker';
import { ATTRIBUTE_GROUP_ICONS, IconPicker } from '@/components/modeling/icon-picker';
import { SettingToggleRow } from '@/components/modeling/setting-toggle-row';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { HttpError, jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * VIEW-03b — pixel-perfect rebuild of `NewAttributeGroupView`
 * (`groups-categories.jsx:482–603`):
 *
 *   - Back link "Wstecz do Attribute Groups".
 *   - Header (flex justify-between): live color icon 14×14 + caption +
 *     live title `displayName` + Pimcore/Akeneo description; right
 *     buttons "Anuluj | + Utwórz grupę".
 *   - Grid 1fr+320px:
 *     - Left Card: Identyfikacja (Code mono + helper + Nazwa PL/EN tabs
 *       + Description), Wygląd (8-swatch ColorPicker + 14-icon IconPicker),
 *       Zachowanie (3 SettingToggleRow).
 *     - Right aside: Card "Podgląd" (live color icon + name + code) +
 *       Card "Następnie" (3-step roadmap).
 */

interface CreatePayload {
  code: string;
  labelPl: string;
  labelEn: string;
  descriptionPl: string;
  descriptionEn: string;
  icon: string;
  color: string;
  requiredSection: boolean;
  shared: boolean;
  conditionalVisibility: boolean;
}

const EMPTY: CreatePayload = {
  code: '',
  labelPl: '',
  labelEn: '',
  descriptionPl: '',
  descriptionEn: '',
  icon: ATTRIBUTE_GROUP_ICONS[0],
  color: ATTRIBUTE_GROUP_SWATCHES[0],
  requiredSection: false,
  shared: true,
  conditionalVisibility: false,
};

export function AttributeGroupCreatePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [values, setValues] = useState<CreatePayload>(EMPTY);
  const [activeLocale, setActiveLocale] = useState<'pl' | 'en'>('pl');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const displayName =
    values.labelPl.trim() ||
    values.labelEn.trim() ||
    t('modeling.attributeGroups.create.title_default', { defaultValue: 'Nazwa grupy' });

  const handleSubmit = async () => {
    setError(null);
    setSubmitting(true);
    try {
      const body: Record<string, unknown> = {
        code: values.code,
        label: stripEmpty({ pl: values.labelPl, en: values.labelEn }),
        position: 0,
        requiredSection: values.requiredSection,
        shared: values.shared,
        conditionalVisibility: values.conditionalVisibility,
      };
      const description = stripEmpty({ pl: values.descriptionPl, en: values.descriptionEn });
      if (Object.keys(description).length > 0) body.description = description;
      if (values.icon !== '') body.icon = values.icon;
      if (values.color !== '') body.color = values.color;

      const response = await jsonFetch<{ id?: string }>('/api/attribute_groups', {
        method: 'POST',
        contentType: 'application/ld+json',
        accept: 'application/ld+json',
        body,
      });
      if (typeof response.id === 'string' && response.id !== '') {
        navigate(`/modeling/attribute-groups/${response.id}`, { replace: true });
      } else {
        navigate('/modeling/attribute-groups', { replace: true });
      }
    } catch (err) {
      if (err instanceof HttpError) {
        const detail =
          err.body && typeof err.body === 'object' && 'detail' in err.body
            ? String((err.body as Record<string, unknown>).detail)
            : null;
        setError(detail ?? `HTTP ${err.status}`);
      } else {
        setError(
          t('modeling.attributeGroups.create.error', {
            defaultValue: 'Nie udało się utworzyć grupy',
          }),
        );
      }
    } finally {
      setSubmitting(false);
    }
  };

  const valid = values.code.trim().length > 0 && values.labelPl.trim().length > 0;

  return (
    <div className="space-y-6">
      <Link
        to="/modeling/attribute-groups"
        className="inline-flex items-center gap-1.5 text-[12.5px] font-medium text-muted-foreground hover:text-foreground"
      >
        <ArrowLeft className="size-4" />
        {t('modeling.attributeGroups.back_to_library', {
          defaultValue: 'Wstecz do Attribute Groups',
        })}
      </Link>

      <div className="flex flex-wrap items-start justify-between gap-6">
        <div className="flex min-w-0 flex-1 items-start gap-4">
          <div
            className="grid size-14 shrink-0 place-items-center rounded-2xl text-[24px]"
            style={{ background: `${values.color}18`, color: values.color }}
          >
            {values.icon}
          </div>
          <div className="min-w-0 flex-1">
            <div className="text-[13px] font-medium text-muted-foreground">
              {t('modeling.attributeGroups.create.caption', {
                defaultValue: 'Nowa Attribute Group',
              })}
            </div>
            <h1 className="font-display text-[28px] font-semibold tracking-tight">{displayName}</h1>
            <p className="mt-1 max-w-2xl text-[13px] text-muted-foreground">
              {t('modeling.attributeGroups.create.description', {
                defaultValue:
                  'Grupa to wielokrotnego użytku zbiór atrybutów (np. „Wymiary", „Bezpieczeństwo"), który można dołączać do dowolnego ObjectType. Po utworzeniu zacznij dodawać atrybuty z biblioteki.',
              })}
            </p>
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <Button asChild variant="ghost" size="sm" className="h-9 rounded-xl">
            <Link to="/modeling/attribute-groups">
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Link>
          </Button>
          <Button
            type="button"
            disabled={!valid || submitting}
            onClick={() => {
              void handleSubmit();
            }}
            className="h-9 rounded-xl bg-zinc-900 hover:bg-zinc-800"
          >
            <Check className="size-4" />
            {t('modeling.attributeGroups.create.submit_action', {
              defaultValue: 'Utwórz grupę',
            })}
          </Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <Card className="space-y-6 p-6">
          <Section
            title={t('modeling.attributeGroups.definition_title', {
              defaultValue: 'Identyfikacja',
            })}
          >
            <div className="space-y-4">
              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground" htmlFor="code">
                  Code
                </Label>
                <Input
                  id="code"
                  value={values.code}
                  onChange={(e) => setValues({ ...values, code: e.target.value })}
                  pattern="[a-z][a-z0-9_-]*"
                  placeholder="np. wymiary"
                  className="mt-1.5 h-10 font-mono"
                />
                <p className="mt-1 text-[11px] text-muted-foreground">
                  {t('modeling.attributeGroups.create.code_helper', {
                    defaultValue: 'Niezmienialny po utworzeniu. Używany w API i mapowaniach.',
                  })}
                </p>
              </div>

              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground">
                  {t('modeling.attributeGroups.fields.name', { defaultValue: 'Nazwa' })}
                </Label>
                <div className="mt-1.5 flex items-center gap-1 border-b border-zinc-100">
                  {(['pl', 'en'] as const).map((lc) => {
                    const filled =
                      (lc === 'pl' ? values.labelPl : values.labelEn).trim().length > 0;
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
                  value={activeLocale === 'pl' ? values.labelPl : values.labelEn}
                  onChange={(e) =>
                    setValues({
                      ...values,
                      ...(activeLocale === 'pl'
                        ? { labelPl: e.target.value }
                        : { labelEn: e.target.value }),
                    })
                  }
                  placeholder="np. Wymiary"
                />
              </div>

              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground">
                  {t('modeling.attributeGroups.fields.description_optional', {
                    defaultValue: 'Opis (opcjonalny)',
                  })}
                </Label>
                <div className="mt-1.5 grid gap-2 sm:grid-cols-2">
                  <Textarea
                    rows={2}
                    value={values.descriptionPl}
                    onChange={(e) => setValues({ ...values, descriptionPl: e.target.value })}
                    placeholder="PL · krótki opis grupy"
                  />
                  <Textarea
                    rows={2}
                    value={values.descriptionEn}
                    onChange={(e) => setValues({ ...values, descriptionEn: e.target.value })}
                    placeholder="EN · short description"
                  />
                </div>
              </div>
            </div>
          </Section>

          <Section
            title={t('modeling.attributeGroups.appearance_title', { defaultValue: 'Wygląd' })}
          >
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground">
                  {t('modeling.attributeGroups.fields.color', { defaultValue: 'Kolor' })}
                </Label>
                <div className="mt-2">
                  <ColorPicker
                    selected={values.color}
                    onSelect={(c) => setValues({ ...values, color: c })}
                    options={ATTRIBUTE_GROUP_SWATCHES}
                  />
                </div>
              </div>
              <div>
                <Label className="text-[11.5px] font-medium text-muted-foreground">
                  {t('modeling.attributeGroups.fields.icon', { defaultValue: 'Ikona' })}
                </Label>
                <div className="mt-2">
                  <IconPicker
                    selected={values.icon}
                    onSelect={(ic) => setValues({ ...values, icon: ic })}
                    options={ATTRIBUTE_GROUP_ICONS}
                  />
                </div>
              </div>
            </div>
          </Section>

          <Section
            title={t('modeling.attributeGroups.behavior_title', { defaultValue: 'Zachowanie' })}
          >
            <div className="space-y-3">
              <SettingToggleRow
                label={t('modeling.attributeGroups.behavior_required_section_label', {
                  defaultValue: 'Wymagana sekcja',
                })}
                description={t('modeling.attributeGroups.behavior_required_section_desc', {
                  defaultValue: 'Grupa zawsze widoczna w formularzu',
                })}
                checked={values.requiredSection}
                onChange={(next) => setValues({ ...values, requiredSection: next })}
              />
              <SettingToggleRow
                label={t('modeling.attributeGroups.behavior_shared_label', {
                  defaultValue: 'Współdzielona',
                })}
                description={t('modeling.attributeGroups.behavior_shared_desc', {
                  defaultValue: 'Może być dołączona do wielu ObjectType',
                })}
                checked={values.shared}
                onChange={(next) => setValues({ ...values, shared: next })}
              />
              <SettingToggleRow
                label={t('modeling.attributeGroups.behavior_conditional_label', {
                  defaultValue: 'Conditional visibility',
                })}
                description={t('modeling.attributeGroups.behavior_conditional_desc', {
                  defaultValue: 'Pokaż grupę warunkowo (visible_when)',
                })}
                checked={values.conditionalVisibility}
                onChange={(next) => setValues({ ...values, conditionalVisibility: next })}
              />
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
              {t('modeling.attributeGroups.preview_card_title', { defaultValue: 'Podgląd' })}
            </div>
            <div className="mt-3 flex items-center gap-2.5">
              <div
                className="grid size-10 shrink-0 place-items-center rounded-2xl text-[18px]"
                style={{ background: `${values.color}18`, color: values.color }}
              >
                {values.icon}
              </div>
              <div className="min-w-0">
                <div className="truncate text-[13.5px] font-semibold tracking-tight">
                  {displayName}
                </div>
                <div className="truncate font-mono text-[11px] text-muted-foreground">
                  {values.code.trim() ||
                    t('modeling.attributeGroups.create.preview_code_placeholder', {
                      defaultValue: 'code…',
                    })}
                </div>
              </div>
            </div>
          </Card>
          <Card className="p-5">
            <div className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
              {t('modeling.attributeGroups.next_card_title', { defaultValue: 'Następnie' })}
            </div>
            <ul className="mt-3 space-y-1.5 text-[12px] text-muted-foreground">
              <li>
                1. {t('modeling.attributeGroups.next_step_1', { defaultValue: 'Utwórz grupę' })}
              </li>
              <li>
                2.{' '}
                {t('modeling.attributeGroups.next_step_2', {
                  defaultValue: 'Dodaj atrybuty z biblioteki',
                })}
              </li>
              <li>
                3.{' '}
                {t('modeling.attributeGroups.next_step_3', {
                  defaultValue: 'Dołącz grupę do ObjectType',
                })}
              </li>
            </ul>
          </Card>
        </aside>
      </div>
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
