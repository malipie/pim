import { useOne } from '@refinedev/core';
import { ArrowLeft, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { resolveLabel } from '@/features/catalog/attributes/list';

interface ObjectTypeDetail {
  id: string;
  code: string;
  kind: string;
  label?: Record<string, string> | string | null;
  builtIn?: boolean;
  schemaVersion?: number;
  completenessRules?: Record<string, unknown> | null;
  labelAttribute?: { id: string; code?: string } | string | null;
  imageAttribute?: { id: string; code?: string } | string | null;
}

export function ObjectTypeShowPage() {
  const { t, i18n } = useTranslation();
  const params = useParams<{ id: string }>();
  const id = params.id ?? '';
  const { result, query } = useOne<ObjectTypeDetail>({
    resource: 'object_types',
    id,
    queryOptions: { enabled: id.length > 0 },
  });

  if (query.isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const objectType = result;
  const label = resolveLabel(objectType.label, i18n.language);
  const isBuiltIn = objectType.builtIn !== false;

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <Button asChild variant="ghost" size="sm" className="-ml-3">
          <Link to="/modeling/object-types">
            <ArrowLeft className="size-4" />
            {t('object_types.back')}
          </Link>
        </Button>
        <div className="flex flex-wrap items-center gap-2">
          <h1 className="text-2xl font-semibold tracking-tight">{label}</h1>
          {isBuiltIn ? (
            <span className="inline-flex items-center gap-1 rounded bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">
              <Lock className="size-3" />
              {t('object_types.locked')}
            </span>
          ) : null}
        </div>
        <p className="font-mono text-xs text-muted-foreground">{objectType.code}</p>
      </div>

      <Card>
        <CardContent className="grid gap-3 pt-6 sm:grid-cols-2">
          <DetailRow label={t('object_types.fields.kind')}>{objectType.kind}</DetailRow>
          <DetailRow label={t('object_types.fields.schema_version')}>
            {objectType.schemaVersion ?? 1}
          </DetailRow>
          {objectType.labelAttribute ? (
            <DetailRow label={t('object_types.fields.label_attribute')}>
              {resolveAttributeRef(objectType.labelAttribute)}
            </DetailRow>
          ) : null}
          {objectType.imageAttribute ? (
            <DetailRow label={t('object_types.fields.image_attribute')}>
              {resolveAttributeRef(objectType.imageAttribute)}
            </DetailRow>
          ) : null}
          {objectType.completenessRules && Object.keys(objectType.completenessRules).length > 0 ? (
            <DetailRow label={t('object_types.fields.completeness')}>
              <pre className="rounded bg-muted px-2 py-1 text-xs">
                {JSON.stringify(objectType.completenessRules, null, 2)}
              </pre>
            </DetailRow>
          ) : null}
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground">{t('object_types.write_deferred_note')}</p>
    </div>
  );
}

function DetailRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1">
      <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
      <dd className="text-sm font-medium">{children}</dd>
    </div>
  );
}

function resolveAttributeRef(value: ObjectTypeDetail['labelAttribute']): string {
  if (!value) return '—';
  if (typeof value === 'string') return value;
  return value.code ?? value.id ?? '—';
}
