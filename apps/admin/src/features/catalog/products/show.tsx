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

type TabKey = 'attributes' | 'variants' | 'media' | 'relationships' | 'history';

const TABS: TabKey[] = ['attributes', 'variants', 'media', 'relationships', 'history'];

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
      {/* Sticky header — handoff glass-strong */}
      <div className="sticky top-0 z-20 -mx-6 -mt-6 border-b border-line glass-strong px-6 py-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="space-y-1">
            <Button asChild variant="ghost" size="sm" className="-ml-3">
              <Link to="/products">
                <ArrowLeft className="size-4" />
                {t('products.back')}
              </Link>
            </Button>
            <div className="flex items-center gap-3">
              <h1 className="display text-[22px] font-semibold leading-tight text-ink">{name}</h1>
              <span className="font-mono text-[12px] text-ink-2">{product.code}</span>
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
          {activeTab === 'media' ? <MediaTabStub /> : null}
          {activeTab === 'relationships' ? <RelationshipsTabStub /> : null}
          {activeTab === 'history' ? <HistoryTabStub productId={productId} /> : null}
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

/**
 * MOCK: Multimedia tab — wymaga DAM + S3/MinIO storage (#TBD).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/produkty-do-oprogramowania.md.
 */
function MediaTabStub() {
  return (
    <div className="rounded-2xl border border-dashed border-line bg-surface-2/40 p-8 text-center soft-shadow">
      <p className="text-[14px] font-medium text-ink-2">Multimedia (mock)</p>
      <p className="mt-1 text-[12px] text-muted-foreground">
        Tab wymaga DAM (S3/MinIO + image transformations). Backlog:{' '}
        <code className="font-mono text-[11px]">POST /api/products/&#123;id&#125;/media</code>
      </p>
    </div>
  );
}

/**
 * MOCK: Powiązania (relationships) tab — wymaga AssociationController CRUD (#TBD).
 * Entity + Repository istnieją, brak HTTP controllera.
 */
function RelationshipsTabStub() {
  return (
    <div className="rounded-2xl border border-dashed border-line bg-surface-2/40 p-8 text-center soft-shadow">
      <p className="text-[14px] font-medium text-ink-2">Powiązania (mock)</p>
      <p className="mt-1 text-[12px] text-muted-foreground">
        3 typy: akcesoria / cross-sell / alternatywa. Wymaga{' '}
        <code className="font-mono text-[11px]">
          GET/POST/DELETE /api/products/&#123;id&#125;/relationships
        </code>
        .
      </p>
    </div>
  );
}

/**
 * MOCK: Historia (audit timeline) tab — wymaga GET /api/products/{id}/audit-log (#TBD).
 * DetailSidebar już używa tego endpointu (last-5), tu pełna historia z timeline UI.
 */
function HistoryTabStub({ productId: _productId }: { productId: string }) {
  return (
    <div className="rounded-2xl border border-dashed border-line bg-surface-2/40 p-8 text-center soft-shadow">
      <p className="text-[14px] font-medium text-ink-2">Historia (mock)</p>
      <p className="mt-1 text-[12px] text-muted-foreground">
        Pełna oś czasu z provenance per zmiana. Sidebar już pokazuje 5 ostatnich (UI-02.5); ten tab
        potrzebuje paginowanego endpointu i timeline UI.
      </p>
    </div>
  );
}
