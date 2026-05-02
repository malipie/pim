import { useOne } from '@refinedev/core';
import { ArrowLeft, Wand2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { AuditLogIndicator } from '@/components/modeling/audit-log-indicator';
import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { WhereUsedList } from '@/components/modeling/where-used-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

import { resolveLabel } from './list';

interface AttributeDetail {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  help?: Record<string, string> | string | null;
  type: string;
  required?: boolean;
  localizable?: boolean;
  scopable?: boolean;
  system?: boolean;
  validationRules?: Record<string, unknown> | null;
  position?: number;
  group?: { id: string; code?: string; label?: Record<string, string> | string } | string | null;
  createdAt?: string;
}

export function AttributeShowPage() {
  const { t, i18n } = useTranslation();
  const params = useParams<{ id: string }>();
  const id = params.id ?? '';
  const { result, query } = useOne<AttributeDetail>({
    resource: 'attributes',
    id,
    queryOptions: { enabled: id.length > 0 },
  });

  if (query.isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const attribute = result;
  const label = resolveLabel(attribute.label, i18n.language);
  const help = resolveLabel(attribute.help, i18n.language);

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <div className="flex items-center justify-between">
          <Button asChild variant="ghost" size="sm" className="-ml-3">
            <Link to="/modeling/attributes">
              <ArrowLeft className="size-4" />
              {t('attributes.back')}
            </Link>
          </Button>
          <AuditLogIndicator />
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <h1 className="text-2xl font-semibold tracking-tight">{label}</h1>
          {attribute.system ? <BuiltInLockBadge /> : null}
          {!attribute.system ? (
            <Button asChild variant="outline" size="sm" className="ml-auto">
              <Link to={`/modeling/attributes/${attribute.id}/migrate-type`}>
                <Wand2 className="size-4" />
                {t('modeling.attributes.migration.action_label')}
              </Link>
            </Button>
          ) : null}
        </div>
        <p className="font-mono text-xs text-muted-foreground">{attribute.code}</p>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <Card>
          <CardContent className="grid gap-3 pt-6 sm:grid-cols-2">
            <DetailRow label={t('attributes.fields.type')}>
              <span className="rounded bg-muted px-2 py-0.5 text-xs uppercase tracking-wide">
                {attribute.type}
              </span>
            </DetailRow>
            {help !== '—' ? (
              <DetailRow label={t('attributes.fields.help')}>
                <span className="text-sm">{help}</span>
              </DetailRow>
            ) : null}
            <DetailRow label={t('attributes.fields.required')}>
              <FlagBadge value={attribute.required ?? false} />
            </DetailRow>
            <DetailRow label={t('attributes.fields.localizable')}>
              <FlagBadge value={attribute.localizable ?? false} />
            </DetailRow>
            <DetailRow label={t('attributes.fields.scopable')}>
              <FlagBadge value={attribute.scopable ?? false} />
            </DetailRow>
            {attribute.validationRules && Object.keys(attribute.validationRules).length > 0 ? (
              <DetailRow label={t('attributes.fields.validation')}>
                <pre className="rounded bg-muted px-2 py-1 text-xs">
                  {JSON.stringify(attribute.validationRules, null, 2)}
                </pre>
              </DetailRow>
            ) : null}
            <DetailRow label={t('modeling.attributes.preview_title')}>
              <AttributePreview type={attribute.type} />
            </DetailRow>
          </CardContent>
        </Card>
        <WhereUsedList resource="attributes" id={attribute.id} />
      </div>

      <p className="text-xs text-muted-foreground">
        {attribute.system
          ? t('modeling.attributes.system_immutable_note')
          : t('attributes.write_deferred_note')}
      </p>
    </div>
  );
}

/**
 * UI-08.11 (#266) — minimal mock-data preview per AttributeType.
 * Renders the kind of widget the user would see for that type (input,
 * select-like chip, asset placeholder). Live data preview lands in
 * Phase 2 once the form-renderer ships.
 */
function AttributePreview({ type }: { type: string }) {
  switch (type) {
    case 'number':
    case 'price':
    case 'metric':
      return <span className="font-mono text-xs">42</span>;
    case 'boolean':
      return (
        <span className="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900">
          true
        </span>
      );
    case 'select':
    case 'multiselect':
    case 'multi_select':
      return (
        <span className="rounded bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">
          option
        </span>
      );
    case 'date':
    case 'datetime':
      return <span className="font-mono text-xs">2026-01-01</span>;
    case 'asset':
      return <span className="font-mono text-xs text-muted-foreground">[asset]</span>;
    case 'reference':
    case 'relation':
      return <span className="font-mono text-xs text-muted-foreground">[ref]</span>;
    default:
      return <span className="text-xs text-muted-foreground">sample text</span>;
  }
}

function DetailRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1">
      <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
      <dd className="text-sm font-medium">{children}</dd>
    </div>
  );
}

function FlagBadge({ value }: { value: boolean }) {
  const tone = value ? 'bg-emerald-100 text-emerald-900' : 'bg-muted text-muted-foreground';
  return (
    <span className={`rounded px-2 py-0.5 text-xs font-medium ${tone}`}>{value ? '✓' : '—'}</span>
  );
}
