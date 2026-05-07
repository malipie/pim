import { useOne, useUpdate } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { ApiProfileForm, type ApiProfileFormValues } from './form';
import type { ApiProfileRow } from './list';

export function ApiProfileEditPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();

  const { result, query } = useOne<ApiProfileRow>({
    resource: 'api_profiles',
    id: id ?? '',
    queryOptions: { enabled: id !== undefined && id !== '' },
  });
  const profile = result;

  const { mutateAsync, mutation } = useUpdate();
  const isPending = mutation.isPending;
  const [apiError, setApiError] = useState<string | null>(null);

  if (query.isLoading || profile === undefined) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('api_profiles.edit_title')}</h1>
        <p className="text-sm text-muted-foreground">
          <span className="font-mono text-xs">{profile.code}</span>
          {' — '}
          {profile.name}
        </p>
      </div>

      <ApiProfileForm
        mode="edit"
        initialValues={{
          code: profile.code,
          name: profile.name,
          description: profile.description ?? '',
          outputFormat: profile.outputFormat as 'json_ld' | 'json',
          rateLimitPerHour: profile.rateLimitPerHour,
          objectTypeIds: profile.objectTypeIds ?? [],
          includedAttributes: profile.includedAttributes ?? [],
          filters: profile.filters ?? {},
          webhookUrl: profile.webhookUrl ?? '',
          webhookEvents: profile.webhookEvents ?? [],
        }}
        isSubmitting={isPending}
        apiError={apiError}
        onSubmit={async (values: ApiProfileFormValues) => {
          setApiError(null);
          try {
            await mutateAsync({
              resource: 'api_profiles',
              id: profile.id,
              values: {
                name: values.name,
                description: values.description !== '' ? values.description : null,
                outputFormat: values.outputFormat,
                rateLimitPerHour: values.rateLimitPerHour,
                objectTypeIds: values.objectTypeIds,
                includedAttributes: values.includedAttributes,
                filters: values.filters,
                webhookUrl: values.webhookUrl !== '' ? values.webhookUrl : null,
                webhookEvents: values.webhookEvents,
              },
            });
            navigate(`/integrations/api-configurator/${profile.id}`);
          } catch {
            setApiError(t('api_profiles.errors.update_failed'));
          }
        }}
      />
    </div>
  );
}
