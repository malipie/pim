import { useList, useOne } from '@refinedev/core';
import { ChevronLeft, KeyRound, Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';

import type { ApiProfileRow } from './list';

interface ApiKeyRow {
  id: string;
  keyPrefix: string;
  name: string;
  scopes: string[];
  expiresAt: string | null;
  revokedAt: string | null;
  lastUsedAt: string | null;
  rateLimitPerHour: number;
  createdAt: string;
}

export function ApiProfileShowPage() {
  const { t, i18n } = useTranslation();
  const { id } = useParams<{ id: string }>();

  const { result, query } = useOne<ApiProfileRow>({
    resource: 'api_profiles',
    id: id ?? '',
    queryOptions: { enabled: id !== undefined && id !== '' },
  });
  const profile = result?.data;

  const keysQuery = useList<ApiKeyRow>({
    resource: 'api_keys',
    pagination: { mode: 'off' },
    queryOptions: { enabled: profile !== undefined },
  });
  const allKeys = keysQuery.result.data ?? [];
  const linkedKeys =
    profile !== undefined ? allKeys.filter((k) => k.scopes.includes(profile.code)) : [];

  if (query.isLoading || profile === undefined) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  return (
    <div className="space-y-6">
      <div>
        <Button asChild variant="ghost" size="sm" className="-ml-2 mb-2">
          <Link to="/api-profiles">
            <ChevronLeft className="size-4" />
            {t('api_profiles.actions.back_to_list')}
          </Link>
        </Button>
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">{profile.name}</h1>
            <p className="font-mono text-xs text-muted-foreground">{profile.code}</p>
          </div>
          <Button asChild variant="outline">
            <Link to={`/api-profiles/${profile.id}/edit`}>
              <Pencil className="mr-1 size-4" />
              {t('api_profiles.actions.edit')}
            </Link>
          </Button>
        </div>
      </div>

      <section className="space-y-3 rounded-xl border bg-card p-6">
        <h2 className="text-lg font-medium">{t('api_profiles.show.basic_info')}</h2>
        <dl className="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
          <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">
              {t('api_profiles.fields.output_format')}
            </dt>
            <dd className="font-mono">{profile.outputFormat}</dd>
          </div>
          <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">
              {t('api_profiles.fields.rate_limit')}
            </dt>
            <dd>{profile.rateLimitPerHour}/h</dd>
          </div>
          <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">
              {t('api_profiles.fields.object_types_count')}
            </dt>
            <dd>{(profile.objectTypeIds ?? []).length}</dd>
          </div>
          <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">
              {t('api_profiles.fields.included_attributes_count')}
            </dt>
            <dd>{(profile.includedAttributes ?? []).length}</dd>
          </div>
          {profile.description !== null && profile.description !== '' && (
            <div className="md:col-span-2">
              <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                {t('api_profiles.fields.description')}
              </dt>
              <dd className="whitespace-pre-line">{profile.description}</dd>
            </div>
          )}
        </dl>
      </section>

      <section className="space-y-3 rounded-xl border bg-card p-6">
        <div className="flex items-center justify-between">
          <h2 className="flex items-center gap-2 text-lg font-medium">
            <KeyRound className="size-4" />
            {t('api_profiles.show.linked_keys', { count: linkedKeys.length })}
          </h2>
        </div>
        {linkedKeys.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('api_profiles.show.no_keys')}</p>
        ) : (
          <ul className="space-y-2 text-sm">
            {linkedKeys.map((key) => (
              <li
                key={key.id}
                className="flex items-center justify-between gap-3 rounded border bg-muted/50 px-3 py-2"
              >
                <div className="space-y-0.5">
                  <p className="font-mono text-xs">{key.keyPrefix}…</p>
                  <p className="font-medium">{key.name}</p>
                </div>
                <div className="text-right text-xs text-muted-foreground">
                  {key.lastUsedAt !== null ? (
                    <span>
                      {t('api_profiles.show.last_used')}{' '}
                      {new Date(key.lastUsedAt).toLocaleDateString(i18n.language)}
                    </span>
                  ) : (
                    <span>{t('api_profiles.show.never_used')}</span>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
        <p className="text-xs text-muted-foreground">
          {t('api_profiles.show.keys_management_note')}
        </p>
      </section>
    </div>
  );
}
