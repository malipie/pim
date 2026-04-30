import { useCreate } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { ApiProfileForm, type ApiProfileFormValues } from './form';

export function ApiProfileCreatePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { mutateAsync, mutation } = useCreate();
  const isPending = mutation.isPending;
  const [apiError, setApiError] = useState<string | null>(null);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('api_profiles.create_title')}</h1>
        <p className="text-sm text-muted-foreground">{t('api_profiles.create_subtitle')}</p>
      </div>

      <ApiProfileForm
        mode="create"
        isSubmitting={isPending}
        apiError={apiError}
        onSubmit={async (values: ApiProfileFormValues) => {
          setApiError(null);
          try {
            await mutateAsync({
              resource: 'api_profiles',
              values: {
                code: values.code,
                name: values.name,
                description: values.description !== '' ? values.description : null,
                outputFormat: values.outputFormat,
                objectTypeIds: values.objectTypeIds,
                includedAttributes: values.includedAttributes,
                filters: values.filters,
                webhookEvents: [],
                rateLimitPerHour: values.rateLimitPerHour,
              },
            });
            navigate('/api-profiles');
          } catch {
            setApiError(t('api_profiles.errors.create_failed'));
          }
        }}
      />
    </div>
  );
}
