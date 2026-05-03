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
 * VIEW-02 (#374) — Attribute create form. Minimal viable slice
 * matching the FE smoke flow operator needs after #381 wired
 * POST /api/attributes:
 *   - code (snake_case, regex), label PL/EN, type select (10 enum
 *     values), help PL/EN, 3 flags (localizable / scopable / required).
 *   - attachToGroups omitted from this form — that field is consumed
 *     by the AttributeGroup detail "Stwórz nowy" popup (VIEW-03b).
 *
 * Pixel-perfect upgrades from `attributes.jsx:352–448` (AttributeTypeGrid
 * tile picker + sidebar Preview + Następnie cards) land in VIEW-02b
 * follow-up — this PR ships the working form so the operator can
 * actually create attributes from the UI today.
 */

interface CreatePayload {
  code: string;
  labelEn: string;
  labelPl: string;
  helpEn: string;
  helpPl: string;
  type: string;
  localizable: boolean;
  scopable: boolean;
  required: boolean;
}

const EMPTY: CreatePayload = {
  code: '',
  labelEn: '',
  labelPl: '',
  helpEn: '',
  helpPl: '',
  type: 'text',
  localizable: false,
  scopable: false,
  required: false,
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
] as const;

export function AttributeCreatePage() {
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
        type: values.type,
        localizable: values.localizable,
        scopable: values.scopable,
        required: values.required,
      };
      const help = stripEmpty({ pl: values.helpPl, en: values.helpEn });
      if (Object.keys(help).length > 0) body.help = help;

      const response = await jsonFetch<{ id?: string }>('/api/attributes', {
        method: 'POST',
        contentType: 'application/ld+json',
        accept: 'application/ld+json',
        body,
      });
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

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <Button asChild variant="ghost" size="sm" className="-ml-3">
          <Link to="/modeling/attributes">
            <ArrowLeft className="size-4" />
            {t('attributes.back', { defaultValue: 'Wróć do listy atrybutów' })}
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('attributes.create_title', { defaultValue: 'Nowy atrybut' })}
        </h1>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card>
          <CardContent className="grid gap-4 pt-6">
            <FormRow id="code" label={t('attributes.fields.code', { defaultValue: 'Code' })}>
              <Input
                id="code"
                value={values.code}
                onChange={(e) => setValues({ ...values, code: e.target.value })}
                pattern="[a-z][a-z0-9_]*"
                placeholder="np. warranty_months"
                required
                className="font-mono"
              />
              <p className="text-[11px] text-muted-foreground">
                {t('attributes.code_helper', {
                  defaultValue: 'snake_case · niezmienialny po utworzeniu',
                })}
              </p>
            </FormRow>
            <div className="grid gap-3 sm:grid-cols-2">
              <FormRow
                id="label-pl"
                label={t('attributes.fields.label_pl', { defaultValue: 'Nazwa (PL)' })}
              >
                <Input
                  id="label-pl"
                  value={values.labelPl}
                  onChange={(e) => setValues({ ...values, labelPl: e.target.value })}
                  placeholder="np. Gwarancja (msc)"
                />
              </FormRow>
              <FormRow
                id="label-en"
                label={t('attributes.fields.label_en', { defaultValue: 'Nazwa (EN)' })}
              >
                <Input
                  id="label-en"
                  value={values.labelEn}
                  onChange={(e) => setValues({ ...values, labelEn: e.target.value })}
                  placeholder="e.g. Warranty (months)"
                />
              </FormRow>
            </div>
            <FormRow id="type" label={t('attributes.fields.type', { defaultValue: 'Typ danych' })}>
              <select
                id="type"
                value={values.type}
                onChange={(e) => setValues({ ...values, type: e.target.value })}
                className="h-10 w-full rounded-md border border-input bg-background px-3 font-mono text-sm"
              >
                {TYPES.map((type) => (
                  <option key={type} value={type}>
                    {type}
                  </option>
                ))}
              </select>
            </FormRow>
            <div className="grid gap-3 sm:grid-cols-2">
              <FormRow
                id="help-pl"
                label={t('attributes.fields.help_pl', { defaultValue: 'Opis (PL)' })}
              >
                <Textarea
                  id="help-pl"
                  rows={2}
                  value={values.helpPl}
                  onChange={(e) => setValues({ ...values, helpPl: e.target.value })}
                />
              </FormRow>
              <FormRow
                id="help-en"
                label={t('attributes.fields.help_en', { defaultValue: 'Opis (EN)' })}
              >
                <Textarea
                  id="help-en"
                  rows={2}
                  value={values.helpEn}
                  onChange={(e) => setValues({ ...values, helpEn: e.target.value })}
                />
              </FormRow>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="space-y-3 pt-6">
            <div className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
              {t('attributes.flags_title', { defaultValue: 'Walidacja i flagi' })}
            </div>
            {(['localizable', 'scopable', 'required'] as const).map((key) => (
              <label
                key={key}
                className="flex cursor-pointer items-start gap-3 rounded-xl border border-zinc-200 px-3 py-2.5 transition hover:bg-zinc-50"
              >
                <input
                  type="checkbox"
                  checked={values[key]}
                  onChange={(e) => setValues({ ...values, [key]: e.target.checked })}
                  className="mt-1"
                />
                <span>
                  <span className="block text-[13px] font-medium">
                    {t(`attributes.flags.${key}_label`, { defaultValue: capitalize(key) })}
                  </span>
                  <span className="block text-[11.5px] text-muted-foreground">
                    {flagDescription(key, t)}
                  </span>
                </span>
              </label>
            ))}
          </CardContent>
        </Card>

        {error !== null ? (
          <p className="rounded-md border border-destructive/50 bg-destructive/5 px-3 py-2 text-sm text-destructive">
            {error}
          </p>
        ) : null}

        <div className="flex flex-wrap items-center gap-2">
          <Button asChild variant="ghost">
            <Link to="/modeling/attributes">{t('app.cancel')}</Link>
          </Button>
          <Button type="submit" disabled={submitting}>
            {t('attributes.create_submit', { defaultValue: 'Utwórz atrybut' })}
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

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

function flagDescription(
  key: 'localizable' | 'scopable' | 'required',
  t: (key: string, opts?: Record<string, string>) => string,
): string {
  switch (key) {
    case 'localizable':
      return t('attributes.flags.localizable_desc', {
        defaultValue: 'Per locale (PL/EN/DE)',
      });
    case 'scopable':
      return t('attributes.flags.scopable_desc', {
        defaultValue: 'Per channel (Shopify / Allegro)',
      });
    case 'required':
      return t('attributes.flags.required_desc', {
        defaultValue: 'Pole musi być wypełnione',
      });
  }
}

function stripEmpty(record: Record<string, string>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(record)) {
    if (v.trim() !== '') out[k] = v;
  }
  return out;
}
