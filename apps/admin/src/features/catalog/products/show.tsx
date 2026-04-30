import { useOne } from '@refinedev/core';
import { ArrowLeft, Pencil } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface CatalogObject {
  id: string;
  code: string;
  enabled?: boolean;
  status?: string;
  completeness?: { pct?: number; missing?: string[] } | null;
  attributesIndexed?: Record<string, unknown>;
  createdAt?: string;
  updatedAt?: string;
}

type TabKey = 'attributes' | 'categories' | 'associations' | 'channels' | 'assets' | 'history';

const TABS: TabKey[] = [
  'attributes',
  'categories',
  'associations',
  'channels',
  'assets',
  'history',
];

export function ProductShowPage() {
  const { t, i18n } = useTranslation();
  const params = useParams<{ id: string }>();
  const productId = params.id ?? '';
  const { result, query } = useOne<CatalogObject>({
    resource: 'products',
    id: productId,
    queryOptions: { enabled: productId.length > 0 },
  });
  const [activeTab, setActiveTab] = useState<TabKey>('attributes');

  if (query.isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const product = result;
  const attrs = (product.attributesIndexed ?? {}) as Record<string, unknown>;
  const name = typeof attrs.name === 'string' ? attrs.name : product.code;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="space-y-1">
          <Button asChild variant="ghost" size="sm" className="-ml-3">
            <Link to="/products">
              <ArrowLeft className="size-4" />
              {t('products.back')}
            </Link>
          </Button>
          <h1 className="text-2xl font-semibold tracking-tight">{name}</h1>
          <p className="font-mono text-xs text-muted-foreground">{product.code}</p>
        </div>
        <div className="flex items-center gap-2">
          <CompletenessBadge value={product.completeness?.pct} />
          <Button asChild>
            <Link to={`/products/${product.id}/edit`}>
              <Pencil className="size-4" />
              {t('products.actions.edit')}
            </Link>
          </Button>
        </div>
      </div>

      <div className="border-b">
        <nav className="-mb-px flex flex-wrap gap-1" aria-label={t('products.show.tabs_aria')}>
          {TABS.map((tab) => (
            <button
              key={tab}
              type="button"
              onClick={() => setActiveTab(tab)}
              className={cn(
                'border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                activeTab === tab
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground',
              )}
              aria-pressed={activeTab === tab}
            >
              {t(`products.show.tabs.${tab}`)}
            </button>
          ))}
        </nav>
      </div>

      <Card>
        <CardContent className="pt-6">
          {activeTab === 'attributes' ? <AttributesTab values={attrs} /> : null}
          {activeTab !== 'attributes' ? <PlaceholderTab tab={activeTab} /> : null}
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground">
        {t('products.show.updated_at', {
          defaultValue: 'Last updated',
        })}
        : {formatDateTime(product.updatedAt ?? product.createdAt ?? '', i18n.language)}
      </p>
    </div>
  );
}

function AttributesTab({ values }: { values: Record<string, unknown> }) {
  const { t } = useTranslation();
  const entries = Object.entries(values);
  if (entries.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        {t('products.show.no_attributes', {
          defaultValue: 'This object has no attribute values yet.',
        })}
      </p>
    );
  }
  return (
    <dl className="grid gap-3 sm:grid-cols-2">
      {entries.map(([code, value]) => (
        <div key={code} className="rounded-md border bg-muted/40 px-3 py-2">
          <dt className="text-xs uppercase tracking-wide text-muted-foreground">{code}</dt>
          <dd className="text-sm font-medium">{formatValue(value)}</dd>
          <ProvenanceBadge />
        </div>
      ))}
    </dl>
  );
}

function PlaceholderTab({ tab }: { tab: TabKey }) {
  const { t } = useTranslation();
  return (
    <p className="text-sm text-muted-foreground">
      {t(`products.show.placeholder.${tab}`, {
        defaultValue: 'Content for this tab arrives in a follow-up ticket.',
      })}
    </p>
  );
}

function CompletenessBadge({ value }: { value?: number }) {
  if (typeof value !== 'number') return null;
  const tone =
    value >= 90
      ? 'bg-emerald-100 text-emerald-900'
      : value >= 60
        ? 'bg-amber-100 text-amber-900'
        : 'bg-rose-100 text-rose-900';
  return (
    <span className={`rounded px-2 py-0.5 text-xs font-medium ${tone}`}>{Math.round(value)}%</span>
  );
}

function ProvenanceBadge() {
  // Placeholder per #55 DoD — real provenance lives on `ObjectValue`
  // rows; full surfacing is in #61 (Provenance UI). For now we tag every
  // value as "manual" so the component shape is locked and the tab can
  // upgrade in a follow-up without touching the show page.
  const { t } = useTranslation();
  return (
    <span className="mt-1 inline-block rounded bg-secondary px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-secondary-foreground">
      {t('products.show.provenance.manual', { defaultValue: 'manual' })}
    </span>
  );
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return '—';
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  return JSON.stringify(value);
}

function formatDateTime(value: string, locale: string): string {
  if (value === '') return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(date);
}
