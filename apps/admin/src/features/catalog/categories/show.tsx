import { useOne } from '@refinedev/core';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface CategoryDetail {
  id: string;
  code: string;
  path?: string | null;
  enabled?: boolean;
  status?: string;
  attributesIndexed?: Record<string, unknown>;
  createdAt?: string;
}

export function CategoryShowPage() {
  const { t } = useTranslation();
  const params = useParams<{ id: string }>();
  const id = params.id ?? '';
  const { result, query } = useOne<CategoryDetail>({
    resource: 'categories',
    id,
    queryOptions: { enabled: id.length > 0 },
  });

  if (query.isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const category = result;
  const attrs = (category.attributesIndexed ?? {}) as Record<string, unknown>;
  const name = typeof attrs.name === 'string' ? attrs.name : category.code;

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <Button asChild variant="ghost" size="sm" className="-ml-3">
          <Link to="/categories">
            <ArrowLeft className="size-4" />
            {t('categories.back')}
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">{name}</h1>
        <p className="font-mono text-xs text-muted-foreground">{category.code}</p>
        {category.path ? (
          <p className="text-xs text-muted-foreground">
            {t('categories.fields.path')}: <code className="font-mono">{category.path}</code>
          </p>
        ) : null}
      </div>

      <Card>
        <CardContent className="pt-6">
          <h2 className="mb-3 text-sm font-medium">{t('categories.attributes_title')}</h2>
          {Object.keys(attrs).length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('categories.no_attributes')}</p>
          ) : (
            <dl className="grid gap-3 sm:grid-cols-2">
              {Object.entries(attrs).map(([code, value]) => (
                <div key={code} className="rounded-md border bg-muted/40 px-3 py-2">
                  <dt className="text-xs uppercase tracking-wide text-muted-foreground">{code}</dt>
                  <dd className="text-sm font-medium">{formatValue(value)}</dd>
                </div>
              ))}
            </dl>
          )}
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground">{t('categories.write_deferred_note')}</p>
    </div>
  );
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return '—';
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  return JSON.stringify(value);
}
