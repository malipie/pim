import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';

import { ATTRIBUTE_GROUP_SWATCHES, ColorPicker } from '@/components/modeling/color-picker';
import { ATTRIBUTE_GROUP_ICONS, IconPicker } from '@/components/modeling/icon-picker';
import { SettingToggleRow } from '@/components/modeling/setting-toggle-row';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { HttpError, jsonFetch } from '@/lib/http';

/**
 * VIEW-03 (#375) — pixel-perfect rebuild of the AttributeGroup create
 * form (`NewAttributeGroupView` in `groups-categories.jsx:482–603`).
 *
 * Upgrades over the UI-08.13 minimal form (#268):
 *   - 8-swatch ColorPicker preset (was: free-text hex input).
 *   - 14-emoji IconPicker preset (was: free-text Lucide name).
 *   - 3 behavior toggles (Wymagana sekcja / Współdzielona /
 *     Conditional visibility) backed by VIEW-03 schema additions.
 *   - Sidebar Preview + Następnie cards still deferred to follow-up
 *     (full-width form is the MVP; sidebar polish in VIEW-03b).
 *
 * Posts to `POST /api/attribute_groups` (UI-08.5 #260 + VIEW-03 #382
 * extension for the 3 boolean flags).
 */

interface CreatePayload {
  code: string;
  labelEn: string;
  labelPl: string;
  descriptionEn: string;
  descriptionPl: string;
  icon: string;
  color: string;
  position: number;
  requiredSection: boolean;
  shared: boolean;
  conditionalVisibility: boolean;
}

const EMPTY: CreatePayload = {
  code: '',
  labelEn: '',
  labelPl: '',
  descriptionEn: '',
  descriptionPl: '',
  icon: ATTRIBUTE_GROUP_ICONS[0],
  color: ATTRIBUTE_GROUP_SWATCHES[0],
  position: 0,
  requiredSection: false,
  shared: true,
  conditionalVisibility: false,
};

export function AttributeGroupCreatePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [values, setValues] = useState<CreatePayload>(EMPTY);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const body: Record<string, unknown> = {
        code: values.code,
        label: stripEmpty({ pl: values.labelPl, en: values.labelEn }),
        position: values.position,
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
        setError(t('modeling.attribute_groups.create_error'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <Button asChild variant="ghost" size="sm" className="-ml-3">
          <Link to="/modeling/attribute-groups">
            <ArrowLeft className="size-4" />
            {t('attribute_groups.back')}
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('modeling.attribute_groups.create_title')}
        </h1>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card>
          <CardContent className="grid gap-4 pt-6">
            <FormRow id="code" label={t('attribute_groups.fields.code')}>
              <Input
                id="code"
                value={values.code}
                onChange={(e) => setValues({ ...values, code: e.target.value })}
                pattern="[a-z0-9_-]+"
                required
              />
            </FormRow>
            <div className="grid gap-3 sm:grid-cols-2">
              <FormRow id="label-pl" label={t('modeling.attribute_groups.label_pl')}>
                <Input
                  id="label-pl"
                  value={values.labelPl}
                  onChange={(e) => setValues({ ...values, labelPl: e.target.value })}
                />
              </FormRow>
              <FormRow id="label-en" label={t('modeling.attribute_groups.label_en')}>
                <Input
                  id="label-en"
                  value={values.labelEn}
                  onChange={(e) => setValues({ ...values, labelEn: e.target.value })}
                />
              </FormRow>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <FormRow id="desc-pl" label={t('modeling.attribute_groups.description_pl')}>
                <Textarea
                  id="desc-pl"
                  value={values.descriptionPl}
                  onChange={(e) => setValues({ ...values, descriptionPl: e.target.value })}
                  rows={2}
                />
              </FormRow>
              <FormRow id="desc-en" label={t('modeling.attribute_groups.description_en')}>
                <Textarea
                  id="desc-en"
                  value={values.descriptionEn}
                  onChange={(e) => setValues({ ...values, descriptionEn: e.target.value })}
                  rows={2}
                />
              </FormRow>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="space-y-6 pt-6">
            <div className="space-y-3">
              <Label>{t('modeling.attribute_groups.color')}</Label>
              <ColorPicker
                selected={values.color}
                onSelect={(hex) => setValues({ ...values, color: hex })}
                options={ATTRIBUTE_GROUP_SWATCHES}
              />
            </div>
            <div className="space-y-3">
              <Label>{t('modeling.attribute_groups.icon')}</Label>
              <IconPicker
                selected={values.icon}
                onSelect={(icon) => setValues({ ...values, icon })}
                options={ATTRIBUTE_GROUP_ICONS}
              />
            </div>
            <FormRow id="position" label={t('attribute_groups.fields.position')}>
              <Input
                id="position"
                type="number"
                min={0}
                value={values.position}
                onChange={(e) =>
                  setValues({ ...values, position: Number.parseInt(e.target.value, 10) || 0 })
                }
              />
            </FormRow>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="space-y-4 pt-6">
            <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
              {t('modeling.attribute_groups.behavior_title', { defaultValue: 'Zachowanie' })}
            </div>
            <SettingToggleRow
              label={t('modeling.attribute_groups.behavior_required_label', {
                defaultValue: 'Wymagana sekcja',
              })}
              description={t('modeling.attribute_groups.behavior_required_desc', {
                defaultValue: 'Grupa zawsze widoczna w formularzu',
              })}
              checked={values.requiredSection}
              onChange={(next) => setValues({ ...values, requiredSection: next })}
            />
            <SettingToggleRow
              label={t('modeling.attribute_groups.behavior_shared_label', {
                defaultValue: 'Współdzielona',
              })}
              description={t('modeling.attribute_groups.behavior_shared_desc', {
                defaultValue: 'Może być dołączona do wielu ObjectType',
              })}
              checked={values.shared}
              onChange={(next) => setValues({ ...values, shared: next })}
            />
            <SettingToggleRow
              label={t('modeling.attribute_groups.behavior_conditional_label', {
                defaultValue: 'Conditional visibility',
              })}
              description={t('modeling.attribute_groups.behavior_conditional_desc', {
                defaultValue: 'Pokaż grupę warunkowo (visible_when)',
              })}
              checked={values.conditionalVisibility}
              onChange={(next) => setValues({ ...values, conditionalVisibility: next })}
            />
          </CardContent>
        </Card>

        {error !== null ? (
          <p className="rounded-md border border-destructive/50 bg-destructive/5 px-3 py-2 text-sm text-destructive">
            {error}
          </p>
        ) : null}

        <div className="flex flex-wrap items-center gap-2">
          <Button asChild variant="ghost">
            <Link to="/modeling/attribute-groups">{t('app.cancel')}</Link>
          </Button>
          <Button type="submit" disabled={submitting}>
            {t('modeling.attribute_groups.create_submit')}
          </Button>
        </div>
      </form>
    </div>
  );
}

function FormRow({
  id,
  label,
  children,
}: {
  id: string;
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
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
