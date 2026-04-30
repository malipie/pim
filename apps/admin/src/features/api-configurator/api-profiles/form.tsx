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
}

interface ApiProfileFormProps {
  mode: 'create' | 'edit';
  initialValues?: Partial<ApiProfileFormValues>;
  isSubmitting?: boolean;
  apiError?: string | null;
  onSubmit: (values: ApiProfileFormValues) => Promise<void> | void;
}

export function ApiProfileForm({
  mode,
  initialValues,
  isSubmitting = false,
  apiError = null,
  onSubmit,
}: ApiProfileFormProps) {
  const { t } = useTranslation();
  const [values, setValues] = useState<ApiProfileFormValues>({
    code: initialValues?.code ?? '',
    name: initialValues?.name ?? '',
    description: initialValues?.description ?? '',
    outputFormat: initialValues?.outputFormat ?? 'json_ld',
    rateLimitPerHour: initialValues?.rateLimitPerHour ?? 1000,
  });
  const [validationError, setValidationError] = useState<string | null>(null);

  function handleChange<K extends keyof ApiProfileFormValues>(
    key: K,
    val: ApiProfileFormValues[K],
  ): void {
    setValues((prev) => ({ ...prev, [key]: val }));
  }

  async function handleSubmit(event: React.FormEvent): Promise<void> {
    event.preventDefault();
    setValidationError(null);

    if (mode === 'create' && !/^[a-z0-9_-]+$/.test(values.code)) {
      setValidationError(t('api_profiles.validation.code_format'));
      return;
    }
    if (values.name.trim() === '') {
      setValidationError(t('api_profiles.validation.name_required'));
      return;
    }
    if (values.rateLimitPerHour <= 0 || values.rateLimitPerHour > 100_000) {
      setValidationError(t('api_profiles.validation.rate_limit_range'));
      return;
    }

    await onSubmit(values);
  }

  return (
    <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
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

      <p className="text-xs text-muted-foreground">{t('api_profiles.tabs_deferred_note')}</p>
    </form>
  );
}
