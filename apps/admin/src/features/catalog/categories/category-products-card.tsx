import { useQuery } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight, FolderTree, Star } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Card } from '@/components/ui/card';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface ProductRow {
  id: string;
  code: string;
  enabled: boolean;
  status: string;
  attributesIndexed: Record<string, unknown> | null;
  isPrimary: boolean;
  position: number;
}

interface ListResponse {
  'hydra:totalItems'?: number;
  'hydra:member'?: ProductRow[];
}

interface Props {
  categoryId: string;
}

const PAGE_SIZE = 20;

/**
 * PCAT-06 (#479) — "Produkty (N)" section in the modeling category
 * detail panel. Lists products assigned to the selected category with
 * basic pagination. Each row links to `/products/{id}` for fast hop
 * to the picker (PCAT-05) when the operator wants to change the
 * assignment.
 *
 * Lives below the Effective preview card so the killer-feature reads
 * "here's what an object would see, and here are the actual N objects
 * already there".
 */
export function CategoryProductsCard({ categoryId }: Props) {
  const { t, i18n } = useTranslation();
  const lang = i18n.language === 'pl' ? 'pl' : 'en';
  const [page, setPage] = useState(1);

  const { data, isLoading } = useQuery({
    queryKey: ['categories', categoryId, 'products', page],
    queryFn: async () =>
      jsonFetch<ListResponse>(
        `/api/categories/${categoryId}/products?page=${page}&itemsPerPage=${PAGE_SIZE}`,
        { accept: 'application/json' },
      ),
    enabled: categoryId !== '',
    staleTime: 30_000,
  });

  const total = data?.['hydra:totalItems'] ?? 0;
  const members = data?.['hydra:member'] ?? [];
  const lastPage = Math.max(1, Math.ceil(total / PAGE_SIZE));

  return (
    <Card className="border border-zinc-200 p-6">
      <header className="mb-3 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span className="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
            {t('categories.products_card.title', { defaultValue: 'Produkty w tej kategorii' })}
          </span>
          <span className="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[10.5px] text-zinc-600">
            {total}
          </span>
        </div>
      </header>

      {isLoading ? (
        <p className="px-1 py-3 text-[12.5px] text-zinc-500">
          {t('app.loading', { defaultValue: 'Ładowanie…' })}
        </p>
      ) : total === 0 ? (
        <div className="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 p-6 text-center">
          <FolderTree className="mx-auto size-6 text-zinc-400" />
          <p className="mt-2 text-[12.5px] text-zinc-600">
            {t('categories.products_card.empty', {
              defaultValue: 'Brak produktów w tej kategorii.',
            })}
          </p>
          <p className="mt-1 text-[11px] text-zinc-500">
            {t('categories.products_card.empty_hint', {
              defaultValue:
                'Otwórz kartę produktu i przypisz go do kategorii w zakładce „Kategorie".',
            })}
          </p>
        </div>
      ) : (
        <>
          <ul className="divide-y divide-zinc-100 rounded-xl border border-zinc-100 bg-white">
            {members.map((row) => (
              <li key={row.id} className="flex items-center gap-3 px-3 py-2">
                <Link
                  to={`/products/${row.id}`}
                  className="flex flex-1 items-center gap-3 text-left hover:underline"
                >
                  {row.isPrimary ? (
                    <span
                      className="grid size-5 place-items-center rounded-full bg-amber-100 text-amber-700"
                      title={t('categories.products_card.is_primary', {
                        defaultValue: 'Kategoria główna',
                      })}
                    >
                      <Star className="size-3 fill-amber-500" />
                    </span>
                  ) : (
                    <span className="size-5" aria-hidden />
                  )}
                  <span className="font-mono text-[12px] text-zinc-500">{row.code}</span>
                  <span className="flex-1 text-[12.5px] text-ink">
                    {productName(row.attributesIndexed, lang) ?? row.code}
                  </span>
                  <span
                    className={cn(
                      'rounded px-2 py-0.5 text-[10.5px] font-medium',
                      row.enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-500',
                    )}
                  >
                    {row.enabled
                      ? t('categories.products_card.enabled', { defaultValue: 'aktywny' })
                      : t('categories.products_card.disabled', { defaultValue: 'nieaktywny' })}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
          {lastPage > 1 ? (
            <footer className="mt-3 flex items-center justify-between text-[11.5px] text-zinc-500">
              <span>
                {t('categories.products_card.page_indicator', {
                  defaultValue: 'Strona {{page}} z {{total}}',
                  page,
                  total: lastPage,
                })}
              </span>
              <div className="flex items-center gap-1">
                <button
                  type="button"
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="grid size-6 place-items-center rounded-md text-zinc-500 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-30"
                  aria-label={t('app.previous', { defaultValue: 'Poprzednia' })}
                >
                  <ChevronLeft className="size-3.5" />
                </button>
                <button
                  type="button"
                  onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                  disabled={page === lastPage}
                  className="grid size-6 place-items-center rounded-md text-zinc-500 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-30"
                  aria-label={t('app.next', { defaultValue: 'Następna' })}
                >
                  <ChevronRight className="size-3.5" />
                </button>
              </div>
            </footer>
          ) : null}
        </>
      )}
    </Card>
  );
}

function productName(
  attrs: Record<string, unknown> | null | undefined,
  lang: 'pl' | 'en',
): string | null {
  if (!attrs) return null;
  const name = attrs.name;
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    const map = name as Record<string, unknown>;
    const direct = map[lang];
    if (typeof direct === 'string') return direct;
    if (typeof map.value === 'object' && map.value !== null) {
      const v = (map.value as Record<string, unknown>)[lang];
      if (typeof v === 'string') return v;
    }
    if (typeof map.pl === 'string') return map.pl;
    if (typeof map.en === 'string') return map.en;
  }
  return null;
}
