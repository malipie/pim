import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

/**
 * VIEW-26 (#557) — pager + page size selector dla listy produktów.
 *
 * Wyświetla:
 *  - dropdown „Wyświetl: [20|50|100|200]"
 *  - tekst „Strona N z M · K produktów"
 *  - buttony « ‹ 1 2 3 4 5 › » (z `aria-current` na aktywnej)
 *
 * Hoist'owany przez parent, który zarządza `page`/`pageSize` w state
 * (URL + localStorage init). Pager NIE robi własnej persystencji.
 */

export const PAGE_SIZE_OPTIONS = [20, 50, 100, 200] as const;
export type PageSize = (typeof PAGE_SIZE_OPTIONS)[number];

interface PaginationBarProps {
  page: number;
  pageSize: PageSize;
  totalItems: number;
  onPageChange: (page: number) => void;
  onPageSizeChange: (pageSize: PageSize) => void;
  className?: string;
}

const WINDOW_SIZE = 5; // ile numerowanych przycisków wokół aktywnej strony

export function PaginationBar({
  page,
  pageSize,
  totalItems,
  onPageChange,
  onPageSizeChange,
  className,
}: PaginationBarProps) {
  const { t } = useTranslation();
  const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
  const current = Math.min(Math.max(1, page), totalPages);

  if (totalItems === 0) return null;

  const windowStart = Math.max(1, current - Math.floor(WINDOW_SIZE / 2));
  const windowEnd = Math.min(totalPages, windowStart + WINDOW_SIZE - 1);
  const adjustedStart = Math.max(1, windowEnd - WINDOW_SIZE + 1);
  const pageNumbers: number[] = [];
  for (let i = adjustedStart; i <= windowEnd; i++) {
    pageNumbers.push(i);
  }

  const goto = (target: number): void => {
    const clamped = Math.min(Math.max(1, target), totalPages);
    if (clamped !== current) onPageChange(clamped);
  };

  return (
    <nav
      aria-label={t('pagination.aria_label', { defaultValue: 'Stronicowanie' })}
      className={cn(
        'flex items-center justify-between gap-3 rounded-2xl border border-zinc-100 bg-white px-4 py-2.5 shadow-sm',
        className,
      )}
    >
      <div className="flex items-center gap-2 text-[12.5px] text-zinc-600">
        <label htmlFor="page-size-select" className="text-zinc-500">
          {t('pagination.page_size_label', { defaultValue: 'Wyświetl:' })}
        </label>
        <select
          id="page-size-select"
          value={pageSize}
          onChange={(e) => onPageSizeChange(Number(e.target.value) as PageSize)}
          className="h-8 px-2 rounded-lg border border-zinc-200 text-[12.5px] font-medium tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900"
        >
          {PAGE_SIZE_OPTIONS.map((size) => (
            <option key={size} value={size}>
              {size}
            </option>
          ))}
        </select>
      </div>

      <div className="text-[12px] text-zinc-500 tabular-nums">
        {t('pagination.page_indicator', {
          page: current,
          totalPages,
          totalItems,
          defaultValue: `Strona ${current} z ${totalPages} · ${totalItems} produktów`,
        })}
      </div>

      <div className="flex items-center gap-1">
        <PageButton
          aria-label={t('pagination.first', { defaultValue: 'Pierwsza strona' })}
          onClick={() => goto(1)}
          disabled={current === 1}
        >
          <ChevronsLeft className="size-3.5" aria-hidden="true" />
        </PageButton>
        <PageButton
          aria-label={t('pagination.previous', { defaultValue: 'Poprzednia strona' })}
          onClick={() => goto(current - 1)}
          disabled={current === 1}
        >
          <ChevronLeft className="size-3.5" aria-hidden="true" />
        </PageButton>
        {pageNumbers.map((num) => (
          <PageButton
            key={num}
            onClick={() => goto(num)}
            aria-current={num === current ? 'page' : undefined}
            active={num === current}
          >
            <span className="tabular-nums text-[12px]">{num}</span>
          </PageButton>
        ))}
        <PageButton
          aria-label={t('pagination.next', { defaultValue: 'Następna strona' })}
          onClick={() => goto(current + 1)}
          disabled={current === totalPages}
        >
          <ChevronRight className="size-3.5" aria-hidden="true" />
        </PageButton>
        <PageButton
          aria-label={t('pagination.last', { defaultValue: 'Ostatnia strona' })}
          onClick={() => goto(totalPages)}
          disabled={current === totalPages}
        >
          <ChevronsRight className="size-3.5" aria-hidden="true" />
        </PageButton>
      </div>
    </nav>
  );
}

interface PageButtonProps {
  children: React.ReactNode;
  onClick: () => void;
  disabled?: boolean;
  active?: boolean;
  'aria-label'?: string;
  'aria-current'?: 'page' | undefined;
}

function PageButton({ children, onClick, disabled, active, ...rest }: PageButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      {...rest}
      className={cn(
        'h-8 min-w-[32px] px-2 rounded-lg text-[12px] font-medium transition grid place-items-center border',
        active
          ? 'bg-zinc-900 text-white border-zinc-900'
          : 'bg-white text-zinc-700 border-zinc-200 hover:border-zinc-400',
        disabled && 'opacity-40 cursor-not-allowed hover:border-zinc-200',
      )}
    >
      {children}
    </button>
  );
}
