import { useOne } from '@refinedev/core';
import {
  ArrowLeft,
  Clock,
  Image as ImageIcon,
  Link2,
  Pencil,
  ShoppingBag,
  Sparkles,
  Upload,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { DetailDynamicForm } from '@/components/catalog/detail-dynamic-form';
import { DetailGroupNav } from '@/components/catalog/detail-group-nav';
import { DetailSidebar } from '@/components/catalog/detail-sidebar';
import { SyncAggregateIcon } from '@/components/catalog/sync-aggregate-icon';
import { VariantsTab } from '@/components/catalog/variants-tab';
import { Button } from '@/components/ui/button';
import { MockBadge } from '@/components/ui/mock-badge';
import { cn } from '@/lib/utils';

import { AgentSuggestionsCard } from './components/agent-suggestions-card';
import { CompletenessRing } from './components/completeness-ring';

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
            <CompletenessRing pct={product.completenessPct ?? 0} />
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

        <div className="space-y-4">
          <AgentSuggestionsCard />
          <DetailSidebar productId={productId} />
        </div>
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
 * Visualised as a 3x3 placeholder grid with Upload CTA + MockBadge overlay.
 */
function MediaTabStub() {
  return (
    <MockBadge
      variant="overlay"
      tooltip="MOCK · Multimedia tab wymaga DAM (S3/MinIO + image transformations)"
    >
      <div className="rounded-2xl border border-line bg-surface p-6 soft-shadow">
        <header className="flex items-center justify-between">
          <h3 className="text-[14px] font-semibold text-ink">Multimedia</h3>
          <Button type="button" variant="outline" size="sm" disabled>
            <Upload className="size-4" />
            Upload zdjęć
          </Button>
        </header>
        <div className="mt-4 grid grid-cols-3 gap-3">
          {Array.from({ length: 9 }).map((_, i) => (
            <div
              // biome-ignore lint/suspicious/noArrayIndexKey: static placeholder grid
              key={i}
              className="aspect-square rounded-xl bg-surface-2 ring-1 ring-inset ring-line"
              aria-hidden
            >
              <div className="flex size-full items-center justify-center text-muted-foreground/60">
                <ImageIcon className="size-6" />
              </div>
            </div>
          ))}
        </div>
        <p className="mt-3 text-[11px] text-muted-foreground">
          Backlog:{' '}
          <code className="font-mono text-[10px]">POST /api/products/&#123;id&#125;/media</code>
        </p>
      </div>
    </MockBadge>
  );
}

/**
 * MOCK: Powiązania (relationships) tab — wymaga AssociationController CRUD (#TBD).
 * Visualised as 3 mock relationship cards (cross-sell, up-sell, related).
 */
function RelationshipsTabStub() {
  const types: Array<{ kind: string; label: string; count: number }> = [
    { kind: 'cross-sell', label: 'Cross-sell', count: 4 },
    { kind: 'up-sell', label: 'Up-sell', count: 2 },
    { kind: 'related', label: 'Powiązane', count: 6 },
  ];

  return (
    <MockBadge variant="overlay" tooltip="MOCK · Powiązania wymagają AssociationController (#TBD)">
      <div className="space-y-3">
        {types.map((t) => (
          <div
            key={t.kind}
            className="flex items-center justify-between rounded-2xl border border-line bg-surface p-4 soft-shadow"
          >
            <div className="flex items-center gap-3">
              <span className="flex size-8 items-center justify-center rounded-lg bg-accent-blue/10 text-accent-blue">
                <ShoppingBag className="size-4" />
              </span>
              <div>
                <p className="text-[13.5px] font-semibold text-ink">{t.label}</p>
                <p className="text-[11px] text-muted-foreground">{t.count} produktów powiązanych</p>
              </div>
            </div>
            <Button type="button" variant="ghost" size="sm" disabled>
              Edytuj
            </Button>
          </div>
        ))}
      </div>
    </MockBadge>
  );
}

/**
 * MOCK: Historia (audit timeline) tab — wymaga GET /api/products/{id}/audit-log (#TBD).
 * Visualised as a 3-event vertical timeline with Lucide Clock icons.
 */
function HistoryTabStub({ productId: _productId }: { productId: string }) {
  const events = [
    {
      who: 'Marcin Lipiec',
      when: '5 min temu',
      what: 'Zmieniono description.pl',
      tone: 'bg-accent-violet/10 text-accent-violet',
    },
    {
      who: 'agent.sonnet',
      when: '2 godz. temu',
      what: 'Wzbogacono kod HS (taryfa UE 2026)',
      tone: 'bg-accent-emerald/10 text-accent-emerald',
    },
    {
      who: 'Anna Wiśniewska',
      when: 'wczoraj',
      what: 'Dodano 3 zdjęcia produktu',
      tone: 'bg-accent-blue/10 text-accent-blue',
    },
  ];

  return (
    <MockBadge variant="overlay" tooltip="MOCK · Pełna timeline wymaga endpointu audit-log">
      <div className="rounded-2xl border border-line bg-surface p-5 soft-shadow">
        <header className="mb-4 flex items-center gap-2">
          <Sparkles className="size-4 text-muted-foreground" />
          <h3 className="text-[14px] font-semibold text-ink">Historia zmian</h3>
        </header>
        <ol className="relative space-y-4 border-l border-line pl-6">
          {events.map((e) => (
            // biome-ignore lint/suspicious/noArrayIndexKey: static MOCK timeline; replace with real audit ids when /audit-log endpoint ships
            <li key={`${e.who}-${e.when}`} className="relative">
              <span
                className={`absolute -left-[31px] flex size-5 items-center justify-center rounded-full ring-2 ring-background ${e.tone}`}
              >
                <Clock className="size-2.5" />
              </span>
              <div className="flex items-center gap-2">
                <span className="text-[13px] font-medium text-ink">{e.who}</span>
                <span className="text-[11px] text-muted-foreground">{e.when}</span>
              </div>
              <p className="mt-0.5 text-[12.5px] text-ink-2">{e.what}</p>
            </li>
          ))}
        </ol>
        <div className="mt-3 flex items-center gap-2 text-[11px] text-muted-foreground">
          <Link2 className="size-3" />
          Sidebar pokazuje 5 ostatnich; pełna paginowana historia czeka na endpoint.
        </div>
      </div>
    </MockBadge>
  );
}
