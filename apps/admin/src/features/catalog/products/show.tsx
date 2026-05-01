import { useOne } from '@refinedev/core';
import { ArrowLeft, Pencil } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { CompletenessBadge } from '@/components/catalog/completeness-badge';
import { DetailDynamicForm } from '@/components/catalog/detail-dynamic-form';
import { DetailGroupNav } from '@/components/catalog/detail-group-nav';
import { DetailSidebar } from '@/components/catalog/detail-sidebar';
import { SyncAggregateIcon } from '@/components/catalog/sync-aggregate-icon';
import { VariantsTab } from '@/components/catalog/variants-tab';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface CatalogObject {
  id: string;
  code: string;
  enabled?: boolean;
  status?: string;
  completeness?: { pct?: number; missing?: string[] } | null;
  completenessPct?: number;
  syncStatusAggregate?: string;
  attributesIndexed?: Record<string, unknown>;
  createdAt?: string;
  updatedAt?: string;
}

type TabKey = 'attributes' | 'variants';

const TABS: TabKey[] = ['attributes', 'variants'];

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
  const sync = normaliseSync(product.syncStatusAggregate);

  return (
    <div className="space-y-4">
      {/* Sticky header */}
      <div className="sticky top-0 z-20 -mx-6 -mt-6 border-b bg-background/95 px-6 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="space-y-1">
            <Button asChild variant="ghost" size="sm" className="-ml-3">
              <Link to="/products">
                <ArrowLeft className="size-4" />
                {t('products.back')}
              </Link>
            </Button>
            <div className="flex items-center gap-3">
              <h1 className="text-xl font-semibold tracking-tight">{name}</h1>
              <span className="font-mono text-xs text-muted-foreground">{product.code}</span>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <CompletenessBadge pct={product.completenessPct ?? 0} />
            <SyncAggregateIcon status={sync} />
            <Button asChild>
              <Link to={`/products/${product.id}/edit`}>
                <Pencil className="size-4" />
                {t('products.actions.edit')}
              </Link>
            </Button>
          </div>
        </div>
        <nav
          className="-mb-px mt-3 flex gap-1"
          aria-label={t('products.show.tabs_aria', { defaultValue: 'Detail tabs' })}
        >
          {TABS.map((tab) => (
            <button
              key={tab}
              type="button"
              onClick={() => setActiveTab(tab)}
              className={cn(
                'border-b-2 px-3 py-1.5 text-sm font-medium transition-colors',
                activeTab === tab
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground',
              )}
              aria-pressed={activeTab === tab}
            >
              {t(`products.show.tabs.${tab}`, {
                defaultValue: tab,
              })}
            </button>
          ))}
        </nav>
      </div>

      {/* 3-column grid */}
      <div className="grid gap-6 lg:grid-cols-[200px_1fr_320px]">
        <aside className="lg:sticky lg:top-32 lg:h-fit">
          {activeTab === 'attributes' ? <DetailGroupNav productId={productId} /> : null}
        </aside>

        <div className="min-w-0 space-y-4">
          {activeTab === 'attributes' ? (
            <DetailDynamicForm productId={productId} initialValues={attrs} />
          ) : null}
          {activeTab === 'variants' ? <VariantsTab masterProductId={productId} /> : null}
        </div>

        <DetailSidebar productId={productId} />
      </div>

      <p className="text-xs text-muted-foreground">
        {t('products.show.updated_at', { defaultValue: 'Last updated' })}:{' '}
        {formatDateTime(product.updatedAt ?? product.createdAt ?? '', i18n.language)}
      </p>
    </div>
  );
}

function normaliseSync(raw: string | undefined): 'green' | 'yellow' | 'red' | 'gray' {
  if (raw === 'green' || raw === 'yellow' || raw === 'red' || raw === 'gray') return raw;
  return 'gray';
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
