import { useOne } from '@refinedev/core';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

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
        <Button asChild variant="ghost" size="sm" className="-ml-3">
          <Link to="/attributes">
            <ArrowLeft className="size-4" />
            {t('attributes.back')}
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">{label}</h1>
        <p className="font-mono text-xs text-muted-foreground">{attribute.code}</p>
      </div>

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
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground">{t('attributes.write_deferred_note')}</p>
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

function FlagBadge({ value }: { value: boolean }) {
  const tone = value ? 'bg-emerald-100 text-emerald-900' : 'bg-muted text-muted-foreground';
  return (
    <span className={`rounded px-2 py-0.5 text-xs font-medium ${tone}`}>{value ? '✓' : '—'}</span>
  );
}
