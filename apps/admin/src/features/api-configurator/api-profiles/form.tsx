import { useList } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export interface ApiProfileFormValues {
  code: string;
  name: string;
  description: string;
  outputFormat: 'json_ld' | 'json';
  rateLimitPerHour: number;
  objectTypeIds: string[];
  includedAttributes: string[];
  filters: Record<string, unknown>;
  webhookUrl: string;
  webhookEvents: string[];
}

export const WEBHOOK_EVENTS: readonly string[] = [
  'object.created.product',
  'object.created.category',
  'object.created.asset',
  'object.attributes_changed',
  'object.enabled_changed',
  'object.published',
  'object.archived',
];

interface ApiProfileFormProps {
  mode: 'create' | 'edit';
  initialValues?: Partial<ApiProfileFormValues>;
  isSubmitting?: boolean;
  apiError?: string | null;
  onSubmit: (values: ApiProfileFormValues) => Promise<void> | void;
}

interface ObjectTypeRow {
  id: string;
  code: string;
  kind: string;
  label?: Record<string, string> | string | null;
}

interface AttributeRow {
  id: string;
  code: string;
  type: string;
  label?: Record<string, string> | string | null;
  group?: string | null;
}

type Tab = 'basic' | 'attributes' | 'filters' | 'webhook' | 'preview';

const TABS: Tab[] = ['basic', 'attributes', 'filters', 'webhook', 'preview'];

export function ApiProfileForm({
  mode,
  initialValues,
  isSubmitting = false,
  apiError = null,
  onSubmit,
}: ApiProfileFormProps) {
  const { t, i18n } = useTranslation();
  const [activeTab, setActiveTab] = useState<Tab>('basic');
  const [values, setValues] = useState<ApiProfileFormValues>({
    code: initialValues?.code ?? '',
    name: initialValues?.name ?? '',
    description: initialValues?.description ?? '',
    outputFormat: initialValues?.outputFormat ?? 'json_ld',
    rateLimitPerHour: initialValues?.rateLimitPerHour ?? 1000,
    objectTypeIds: initialValues?.objectTypeIds ?? [],
    includedAttributes: initialValues?.includedAttributes ?? [],
    filters: initialValues?.filters ?? {},
    webhookUrl: initialValues?.webhookUrl ?? '',
    webhookEvents: initialValues?.webhookEvents ?? [],
  });
  const [validationError, setValidationError] = useState<string | null>(null);

  const objectTypesQuery = useList<ObjectTypeRow>({
    resource: 'object_types',
    pagination: { mode: 'off' },
  });
  const objectTypes = objectTypesQuery.result.data;

  const attributesQuery = useList<AttributeRow>({
    resource: 'attributes',
    pagination: { mode: 'off' },
  });
  const attributes = attributesQuery.result.data;

  function handleChange<K extends keyof ApiProfileFormValues>(
    key: K,
    val: ApiProfileFormValues[K],
  ): void {
    setValues((prev) => ({ ...prev, [key]: val }));
  }

  function toggleListMember(
    key: 'objectTypeIds' | 'includedAttributes' | 'webhookEvents',
    id: string,
  ): void {
    setValues((prev) => {
      const current = prev[key];
      const next = current.includes(id) ? current.filter((x) => x !== id) : [...current, id];
      return { ...prev, [key]: next };
    });
  }

  async function handleSubmit(event: React.FormEvent): Promise<void> {
    event.preventDefault();
    setValidationError(null);

    if (mode === 'create' && !/^[a-z0-9_-]+$/.test(values.code)) {
      setValidationError(t('api_profiles.validation.code_format'));
      setActiveTab('basic');
      return;
    }
    if (values.name.trim() === '') {
      setValidationError(t('api_profiles.validation.name_required'));
      setActiveTab('basic');
      return;
    }
    if (values.rateLimitPerHour <= 0 || values.rateLimitPerHour > 100_000) {
      setValidationError(t('api_profiles.validation.rate_limit_range'));
      setActiveTab('basic');
      return;
    }

    await onSubmit(values);
  }

  return (
    <form onSubmit={handleSubmit} className="max-w-3xl space-y-6">
      <div
        className="flex gap-1 border-b"
        role="tablist"
        aria-label={t('api_profiles.form.tabs_label')}
      >
        {TABS.map((tab) => (
          <button
            key={tab}
            type="button"
            role="tab"
            aria-selected={activeTab === tab}
            onClick={() => setActiveTab(tab)}
            className={
              activeTab === tab
                ? 'border-primary text-foreground -mb-px border-b-2 px-4 py-2 text-sm font-medium'
                : '-mb-px border-b-2 border-transparent px-4 py-2 text-sm text-muted-foreground hover:text-foreground'
            }
          >
            {t(`api_profiles.form.tabs.${tab}`)}
          </button>
        ))}
      </div>

      {activeTab === 'basic' ? (
        <BasicInfoTab values={values} mode={mode} handleChange={handleChange} />
      ) : null}

      {activeTab === 'attributes' ? (
        <AttributesTab
          values={values}
          objectTypes={objectTypes}
          attributes={attributes}
          locale={i18n.language}
          toggleListMember={toggleListMember}
        />
      ) : null}

      {activeTab === 'filters' ? <FiltersTab values={values} handleChange={handleChange} /> : null}

      {activeTab === 'webhook' ? (
        <WebhookTab
          values={values}
          mode={mode}
          handleChange={handleChange}
          toggleListMember={toggleListMember}
        />
      ) : null}

      {activeTab === 'preview' ? <PreviewTab values={values} attributes={attributes} /> : null}

      {validationError !== null && (
        <p role="alert" className="text-sm text-destructive">
          {validationError}
        </p>
      )}
      {apiError !== null && apiError !== '' && (
        <p role="alert" className="text-sm text-destructive">
          {apiError}
        </p>
      )}

      <div className="flex gap-2">
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? t('app.saving') : t(`api_profiles.actions.${mode}_submit`)}
        </Button>
      </div>
    </form>
  );
}

function BasicInfoTab({
  values,
  mode,
  handleChange,
}: {
  values: ApiProfileFormValues;
  mode: 'create' | 'edit';
  handleChange: <K extends keyof ApiProfileFormValues>(
    key: K,
    val: ApiProfileFormValues[K],
  ) => void;
}) {
  const { t } = useTranslation();
  return (
    <div className="space-y-6">
      <div className="space-y-2">
        <label htmlFor="api-profile-code" className="block text-sm font-medium">
          {t('api_profiles.fields.code')}
        </label>
        <Input
          id="api-profile-code"
          value={values.code}
          onChange={(e) => handleChange('code', e.currentTarget.value)}
          disabled={mode === 'edit'}
          placeholder="storefront"
          className="font-mono"
          required
        />
        <p className="text-xs text-muted-foreground">{t('api_profiles.fields.code_help')}</p>
      </div>

      <div className="space-y-2">
        <label htmlFor="api-profile-name" className="block text-sm font-medium">
          {t('api_profiles.fields.name')}
        </label>
        <Input
          id="api-profile-name"
          value={values.name}
          onChange={(e) => handleChange('name', e.currentTarget.value)}
          required
        />
      </div>

      <div className="space-y-2">
        <label htmlFor="api-profile-description" className="block text-sm font-medium">
          {t('api_profiles.fields.description')}
        </label>
        <textarea
          id="api-profile-description"
          value={values.description}
          onChange={(e) => handleChange('description', e.currentTarget.value)}
          className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        />
      </div>

      <fieldset className="space-y-2">
        <legend className="block text-sm font-medium">
          {t('api_profiles.fields.output_format')}
        </legend>
        <div className="flex gap-2">
          {(['json_ld', 'json'] as const).map((fmt) => (
            <Button
              key={fmt}
              type="button"
              variant={values.outputFormat === fmt ? 'default' : 'outline'}
              size="sm"
              onClick={() => handleChange('outputFormat', fmt)}
            >
              {t(`api_profiles.output_format.${fmt}`)}
            </Button>
          ))}
        </div>
      </fieldset>

      <div className="space-y-2">
        <label htmlFor="api-profile-rate-limit" className="block text-sm font-medium">
          {t('api_profiles.fields.rate_limit')}
        </label>
        <Input
          id="api-profile-rate-limit"
          type="number"
          min={1}
          max={100_000}
          value={values.rateLimitPerHour}
          onChange={(e) => handleChange('rateLimitPerHour', Number(e.currentTarget.value) || 0)}
          required
        />
        <p className="text-xs text-muted-foreground">{t('api_profiles.fields.rate_limit_help')}</p>
      </div>
    </div>
  );
}

function AttributesTab({
  values,
  objectTypes,
  attributes,
  locale,
  toggleListMember,
}: {
  values: ApiProfileFormValues;
  objectTypes: ObjectTypeRow[];
  attributes: AttributeRow[];
  locale: string;
  toggleListMember: (key: 'objectTypeIds' | 'includedAttributes', id: string) => void;
}) {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <fieldset className="space-y-3">
        <legend className="block text-sm font-medium">
          {t('api_profiles.form.object_types_legend')}
        </legend>
        <p className="text-xs text-muted-foreground">{t('api_profiles.form.object_types_help')}</p>
        {objectTypes.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('api_profiles.form.no_object_types')}</p>
        ) : (
          <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
            {objectTypes.map((ot) => {
              const checked = values.objectTypeIds.includes(ot.id);
              return (
                <label
                  key={ot.id}
                  className="flex items-center gap-2 rounded border bg-card px-3 py-2 text-sm"
                >
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={() => toggleListMember('objectTypeIds', ot.id)}
                  />
                  <span className="font-mono text-xs">{ot.code}</span>
                  <span className="ml-auto text-xs text-muted-foreground">{ot.kind}</span>
                </label>
              );
            })}
          </div>
        )}
      </fieldset>

      <fieldset className="space-y-3">
        <legend className="block text-sm font-medium">
          {t('api_profiles.form.attributes_legend')}
        </legend>
        <p className="text-xs text-muted-foreground">{t('api_profiles.form.attributes_help')}</p>
        {attributes.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('api_profiles.form.no_attributes')}</p>
        ) : (
          <div className="max-h-[400px] overflow-y-auto rounded border bg-card p-2">
            <ul className="space-y-1">
              {attributes.map((attr) => {
                const checked = values.includedAttributes.includes(attr.code);
                return (
                  <li key={attr.id}>
                    <label className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted/50">
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => toggleListMember('includedAttributes', attr.code)}
                      />
                      <span className="font-mono text-xs">{attr.code}</span>
                      <span className="text-xs text-muted-foreground">
                        {resolveLabel(attr.label, locale)}
                      </span>
                      <span className="ml-auto rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] uppercase text-muted-foreground">
                        {attr.type}
                      </span>
                    </label>
                  </li>
                );
              })}
            </ul>
          </div>
        )}
        <p className="text-xs text-muted-foreground">
          {t('api_profiles.form.attributes_count', { count: values.includedAttributes.length })}
        </p>
      </fieldset>
    </div>
  );
}

function FiltersTab({
  values,
  handleChange,
}: {
  values: ApiProfileFormValues;
  handleChange: <K extends keyof ApiProfileFormValues>(
    key: K,
    val: ApiProfileFormValues[K],
  ) => void;
}) {
  const { t } = useTranslation();
  const status = (values.filters.status as string | undefined) ?? '';
  const category = (values.filters.category as string | undefined) ?? '';

  function setFilter(key: string, val: string): void {
    const next: Record<string, unknown> = { ...values.filters };
    if (val === '') {
      delete next[key];
    } else {
      next[key] = val;
    }
    handleChange('filters', next);
  }

  return (
    <div className="space-y-6">
      <p className="text-xs text-muted-foreground">{t('api_profiles.form.filters_help')}</p>

      <fieldset className="space-y-2">
        <legend className="block text-sm font-medium">
          {t('api_profiles.form.filter_status')}
        </legend>
        <div className="flex flex-wrap gap-2">
          {(['', 'enabled', 'disabled', 'published', 'archived'] as const).map((s) => (
            <Button
              key={s === '' ? 'any' : s}
              type="button"
              variant={status === s ? 'default' : 'outline'}
              size="sm"
              onClick={() => setFilter('status', s)}
            >
              {s === '' ? t('api_profiles.form.filter_status_any') : s}
            </Button>
          ))}
        </div>
      </fieldset>

      <div className="space-y-2">
        <label htmlFor="api-profile-filter-category" className="block text-sm font-medium">
          {t('api_profiles.form.filter_category')}
        </label>
        <Input
          id="api-profile-filter-category"
          value={category}
          onChange={(e) => setFilter('category', e.currentTarget.value)}
          placeholder="electronics"
          className="font-mono"
        />
        <p className="text-xs text-muted-foreground">
          {t('api_profiles.form.filter_category_help')}
        </p>
      </div>
    </div>
  );
}

function WebhookTab({
  values,
  mode,
  handleChange,
  toggleListMember,
}: {
  values: ApiProfileFormValues;
  mode: 'create' | 'edit';
  handleChange: <K extends keyof ApiProfileFormValues>(
    key: K,
    val: ApiProfileFormValues[K],
  ) => void;
  toggleListMember: (
    key: 'objectTypeIds' | 'includedAttributes' | 'webhookEvents',
    id: string,
  ) => void;
}) {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <p className="text-xs text-muted-foreground">{t('api_profiles.form.webhook_help')}</p>

      <div className="space-y-2">
        <label htmlFor="api-profile-webhook-url" className="block text-sm font-medium">
          {t('api_profiles.form.webhook_url')}
        </label>
        <Input
          id="api-profile-webhook-url"
          type="url"
          value={values.webhookUrl}
          onChange={(e) => handleChange('webhookUrl', e.currentTarget.value)}
          placeholder="https://partner.example.com/pim-webhook"
        />
        <p className="text-xs text-muted-foreground">{t('api_profiles.form.webhook_url_help')}</p>
      </div>

      {mode === 'edit' && values.webhookUrl !== '' ? (
        <div className="rounded-md border bg-muted/30 p-4 text-sm space-y-2">
          <p className="font-medium">{t('api_profiles.form.webhook_secret_title')}</p>
          <p className="text-xs text-muted-foreground">
            {t('api_profiles.form.webhook_secret_note')}
          </p>
        </div>
      ) : null}

      <fieldset className="space-y-3">
        <legend className="block text-sm font-medium">
          {t('api_profiles.form.webhook_events_legend')}
        </legend>
        <p className="text-xs text-muted-foreground">
          {t('api_profiles.form.webhook_events_help')}
        </p>
        <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
          {WEBHOOK_EVENTS.map((event) => {
            const checked = values.webhookEvents.includes(event);
            return (
              <label
                key={event}
                className="flex items-center gap-2 rounded border bg-card px-3 py-2 text-sm"
              >
                <input
                  type="checkbox"
                  checked={checked}
                  onChange={() => toggleListMember('webhookEvents', event)}
                />
                <span className="font-mono text-xs">{event}</span>
              </label>
            );
          })}
        </div>
      </fieldset>
    </div>
  );
}

function PreviewTab({
  values,
  attributes,
}: {
  values: ApiProfileFormValues;
  attributes: AttributeRow[];
}) {
  const { t } = useTranslation();

  const sampleAttributes: Record<string, unknown> = {};
  for (const code of values.includedAttributes) {
    const attr = attributes.find((a) => a.code === code);
    if (attr === undefined) continue;
    sampleAttributes[code] = sampleValueForType(attr.type);
  }

  const preview =
    values.outputFormat === 'json_ld'
      ? {
          '@context': '/api/contexts/CatalogObject',
          '@id': '/api/products/018f1234-1234-7000-8000-000000000001',
          '@type': 'CatalogObject',
          id: '018f1234-1234-7000-8000-000000000001',
          code: 'SKU-DEMO',
          kind: 'product',
          attributes: sampleAttributes,
        }
      : {
          id: '018f1234-1234-7000-8000-000000000001',
          code: 'SKU-DEMO',
          kind: 'product',
          attributes: sampleAttributes,
        };

  return (
    <div className="space-y-3">
      <p className="text-xs text-muted-foreground">{t('api_profiles.form.preview_help')}</p>
      <pre className="overflow-x-auto rounded-md border bg-muted p-4 font-mono text-xs">
        {JSON.stringify(preview, null, 2)}
      </pre>
      <p className="text-xs text-muted-foreground">{t('api_profiles.form.preview_live_note')}</p>
    </div>
  );
}

function sampleValueForType(type: string): unknown {
  switch (type) {
    case 'text':
    case 'textarea':
      return 'sample text';
    case 'number':
      return 42;
    case 'boolean':
      return true;
    case 'date':
      return '2026-04-30';
    case 'select':
      return 'option_a';
    case 'multi_select':
      return ['option_a', 'option_b'];
    case 'price':
      return { amount: 99.99, currency: 'PLN' };
    case 'measurement':
      return { value: 10, unit: 'kg' };
    case 'asset':
    case 'reference':
      return null;
    default:
      return null;
  }
}

function resolveLabel(
  label: Record<string, string> | string | null | undefined,
  locale: string,
): string {
  if (label === null || label === undefined) return '';
  if (typeof label === 'string') return label;
  if (locale in label) return label[locale];
  if ('en' in label) return label.en;
  if ('pl' in label) return label.pl;
  const first = Object.values(label)[0];
  return first ?? '';
}
