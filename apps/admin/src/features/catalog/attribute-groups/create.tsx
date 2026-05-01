import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { HttpError, jsonFetch } from '@/lib/http';

/**
 * UI-08.13 (#268) — minimal create form for AttributeGroup.
 *
 * Hits UI-08.5 backend (`POST /api/attribute_groups`). Form lives at
 * `/modeling/attribute-groups/new` (route, not Sheet drawer) for the
 * same reason MigrateAttributeTypePage does — destructive workflow +
 * deep-linkable + no Radix Dialog primitive yet.
 *
 * Fields covered:
 *   - code (lowercase + dash + underscore + digits, immutable post-create)
 *   - label PL/EN (multi-locale JSONB)
 *   - description PL/EN (optional)
 *   - icon (free-form Lucide name string)
 *   - color (hex)
 *   - position (sort order)
 *
 * No icon/color picker widgets in MVP — operator types Lucide name +
 * hex; Phase 2 ships visual pickers.
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
}

const EMPTY: CreatePayload = {
  code: '',
  labelEn: '',
  labelPl: '',
  descriptionEn: '',
  descriptionPl: '',
  icon: '',
  color: '',
  position: 0,
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
            <div className="grid gap-3 sm:grid-cols-3">
              <FormRow id="icon" label={t('modeling.attribute_groups.icon')}>
                <Input
                  id="icon"
                  value={values.icon}
                  onChange={(e) => setValues({ ...values, icon: e.target.value })}
                  placeholder="Megaphone"
                />
              </FormRow>
              <FormRow id="color" label={t('modeling.attribute_groups.color')}>
                <Input
                  id="color"
                  value={values.color}
                  onChange={(e) => setValues({ ...values, color: e.target.value })}
                  placeholder="#EC4899"
                />
              </FormRow>
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
            </div>
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
