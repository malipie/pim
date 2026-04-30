import { useTranslation } from 'react-i18next';

interface CatalogFacetListProps {
  distribution: Record<string, Record<string, number>>;
  active: Record<string, string | string[]>;
  onToggle: (facet: string, value: string) => void;
}

/**
 * Faceted filter sidebar (#53 / 0.5.5).
 *
 * Renders one accordion-like block per facet returned by Meili's
 * `facetDistribution`, with a clickable count next to each value.
 * Active selections are highlighted; clicking again toggles off.
 *
 * The layout uses native `<details>` instead of shadcn `Accordion`
 * to keep the component tree light — facet sidebars often render in
 * scrollable areas and we want minimal JS overhead.
 */
export function CatalogFacetList({ distribution, active, onToggle }: CatalogFacetListProps) {
  const { t } = useTranslation();

  const facetEntries = Object.entries(distribution);
  if (facetEntries.length === 0) {
    return (
      <p className="text-xs text-muted-foreground">
        {t('search.no_facets', { defaultValue: 'No filterable facets returned.' })}
      </p>
    );
  }

  return (
    <div className="space-y-3" data-testid="catalog-facet-list">
      {facetEntries.map(([facet, counts]) => (
        <details key={facet} className="group rounded-md border bg-card" open>
          <summary className="cursor-pointer select-none px-3 py-2 text-sm font-medium">
            {facet}
          </summary>
          <ul className="space-y-1 px-3 pb-3 text-sm">
            {Object.entries(counts)
              .sort(([, a], [, b]) => b - a)
              .map(([value, count]) => {
                const isActive = isFacetActive(active, facet, value);
                return (
                  <li key={value}>
                    <button
                      type="button"
                      className={`flex w-full items-center justify-between rounded px-2 py-1 text-left transition hover:bg-muted ${
                        isActive ? 'bg-muted font-semibold' : ''
                      }`}
                      onClick={() => onToggle(facet, value)}
                      aria-pressed={isActive}
                    >
                      <span className="truncate">{value}</span>
                      <span className="ml-2 text-xs tabular-nums text-muted-foreground">
                        {count}
                      </span>
                    </button>
                  </li>
                );
              })}
          </ul>
        </details>
      ))}
    </div>
  );
}

function isFacetActive(
  active: Record<string, string | string[]>,
  facet: string,
  value: string,
): boolean {
  const current = active[facet];
  if (current === undefined) return false;
  return Array.isArray(current) ? current.includes(value) : current === value;
}
