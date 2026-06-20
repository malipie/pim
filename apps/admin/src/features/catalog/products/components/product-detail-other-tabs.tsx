import { Clock, Link2, Sparkles } from 'lucide-react';
import { lazy } from 'react';
import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';
import type { ProductChannel, ProductLocale } from './types';

/**
 * AUD-057 (#1608) — the bespoke special-tab views (multimedia / categories
 * / variants) + the loading fallback + the history stub, extracted from
 * product-detail-page.tsx to shrink that monolith under the 500-line guard.
 *
 * AUD-071 (#1614) — these tabs render ONLY after the operator clicks them,
 * so each is code-split behind React.lazy; the chunk loads on demand behind
 * the <Suspense> boundary the page wraps around <OtherTabs>. RelationsTab
 * stays in the page itself — it is also reached from the main tab dispatcher
 * (forward-relation + reverse-only paths), not just here.
 */
const CategoriesTab = lazy(() =>
  import('./categories-tab').then((m) => ({ default: m.CategoriesTab })),
);
const ProductMultimediaTab = lazy(() =>
  import('./product-multimedia-tab').then((m) => ({ default: m.ProductMultimediaTab })),
);
const VariantsTabHost = lazy(() =>
  import('./variants-tab-host').then((m) => ({ default: m.VariantsTabHost })),
);

export function OtherTabs({
  activeTab,
  productId,
  objectTypeId,
  kind,
  locale,
  channel,
}: {
  activeTab: 'multimedia' | 'categories' | 'history' | 'variants' | 'attributes';
  productId: string;
  objectTypeId: string | null;
  kind: string;
  locale: ProductLocale;
  channel: ProductChannel | null;
}) {
  // UX bug fix #2 — Multimedia is back as a special tab gated by
  // `ObjectType.hasMultimedia` (UX-02 removed it from the AttributeGroup
  // dispatcher); the assets link table is poly-kind so the tab works
  // for every ObjectType.
  if (activeTab === 'multimedia') return <ProductMultimediaTab productId={productId} />;
  if (activeTab === 'categories')
    return <CategoriesTab productId={productId} objectTypeId={objectTypeId} kind={kind} />;
  if (activeTab === 'history') return <HistoryStub />;
  if (activeTab === 'variants')
    return (
      <VariantsTabHost
        productId={productId}
        basePath="/api/objects"
        locale={locale}
        channel={channel}
      />
    );
  return null;
}

/**
 * AUD-071 (#1614) — discreet fallback while a lazy tab chunk loads. The
 * tab chunks are small (a few tens of KB gzip) so this flashes only on a
 * cold cache; it intentionally mirrors the muted card surface of the real
 * tabs to avoid a jarring layout shift.
 */
export function TabLoadingFallback() {
  const { t } = useTranslation();
  return (
    <div
      className="rounded-2xl border border-line bg-surface p-5 text-[12.5px] text-muted-foreground soft-shadow"
      role="status"
      aria-live="polite"
    >
      {t('products.detail.tabs.loading', { defaultValue: 'Ładowanie…' })}
    </div>
  );
}

function HistoryStub() {
  const events = [
    {
      who: 'Marcin Lipiec',
      when: '5 min temu',
      what: 'Zmieniono description.pl',
      tone: 'bg-orange-500/10 text-orange-700',
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
          {events.map((event) => (
            <li key={`${event.who}-${event.when}`} className="relative">
              <span
                className={`absolute -left-[31px] flex size-5 items-center justify-center rounded-full ring-2 ring-background ${event.tone}`}
              >
                <Clock className="size-2.5" />
              </span>
              <div className="flex items-center gap-2">
                <span className="text-[13px] font-medium text-ink">{event.who}</span>
                <span className="text-[11px] text-muted-foreground">{event.when}</span>
              </div>
              <p className="mt-0.5 text-[12.5px] text-ink-2">{event.what}</p>
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
