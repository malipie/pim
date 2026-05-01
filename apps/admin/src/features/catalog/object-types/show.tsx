import { useOne } from '@refinedev/core';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { WhereUsedList } from '@/components/modeling/where-used-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { resolveLabel } from '@/features/catalog/attributes/list';

interface ObjectTypeDetail {
  id: string;
  code: string;
  kind: string;
  label?: Record<string, string> | string | null;
  builtIn?: boolean;
  codeImmutable?: boolean;
  deletable?: boolean;
  icon?: string | null;
  color?: string | null;
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
          {objectType.color ? (
            <span
              aria-hidden
              className="inline-block size-4 rounded-full border"
              style={{ backgroundColor: objectType.color }}
            />
          ) : null}
          <h1 className="text-2xl font-semibold tracking-tight">{label}</h1>
          {isBuiltIn ? <BuiltInLockBadge /> : null}
        </div>
        <p className="font-mono text-xs text-muted-foreground">{objectType.code}</p>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <Card>
          <CardContent className="grid gap-3 pt-6 sm:grid-cols-2">
            <DetailRow label={t('object_types.fields.kind')}>{objectType.kind}</DetailRow>
            <DetailRow label={t('object_types.fields.schema_version')}>
              {objectType.schemaVersion ?? 1}
            </DetailRow>
            {objectType.icon ? (
              <DetailRow label={t('object_types.fields.icon')}>
                <span className="font-mono text-xs">{objectType.icon}</span>
              </DetailRow>
            ) : null}
            {objectType.color ? (
              <DetailRow label={t('object_types.fields.color')}>
                <span className="font-mono text-xs">{objectType.color}</span>
              </DetailRow>
            ) : null}
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
            {objectType.completenessRules &&
            Object.keys(objectType.completenessRules).length > 0 ? (
              <DetailRow label={t('object_types.fields.completeness')}>
                <pre className="rounded bg-muted px-2 py-1 text-xs">
                  {JSON.stringify(objectType.completenessRules, null, 2)}
                </pre>
              </DetailRow>
            ) : null}
          </CardContent>
        </Card>
        <WhereUsedList resource="object_types" id={objectType.id} />
      </div>

      {/*
        MOCK: audit log (last 5 changes — who/when/diff) — wymaga
        GET /api/object_types/{id}/audit-log (#TBD).
        Patrz Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md.
      */}
      <Card className="border-dashed border-line bg-surface-2/40">
        <CardContent className="space-y-1 pt-6">
          <h2 className="text-[15px] font-semibold text-ink">
            {t('object_types.audit_log_title', { defaultValue: 'Historia zmian (5 ostatnich)' })}
          </h2>
          <p className="text-[12px] text-muted-foreground">
            {t('object_types.audit_log_mock', {
              defaultValue:
                'Mock — wymaga endpointu /api/object_types/{id}/audit-log. Patrz backlog modelowania.',
            })}
          </p>
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
