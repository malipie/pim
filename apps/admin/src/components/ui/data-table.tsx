import type * as React from 'react';

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export interface DataTableColumn<TRow> {
  /** Stable id used for React keys + ARIA. */
  id: string;
  header: React.ReactNode;
  /** Cell renderer; receives the row + its index. */
  cell: (row: TRow, index: number) => React.ReactNode;
  /** Optional column-level alignment helper. */
  align?: 'left' | 'right' | 'center';
  className?: string;
}

interface DataTableProps<TRow> {
  data: ReadonlyArray<TRow>;
  columns: ReadonlyArray<DataTableColumn<TRow>>;
  /** Renders when `data` is empty — wizard list view "brak importów" copy lives here. */
  emptyState?: React.ReactNode;
  /** Forwarded to each `<tr>` so the imports list can wire row-click handlers. */
  rowKey: (row: TRow, index: number) => string;
  caption?: string;
  className?: string;
}

/**
 * Tiny declarative wrapper around the existing `<Table>` primitive.
 * No TanStack Table dependency — sorting / pagination land in the
 * calling page so we don't pay for the runtime when the imports
 * list view (spec §5.1) only needs basic rendering.
 */
export function DataTable<TRow>({
  data,
  columns,
  emptyState,
  rowKey,
  caption,
  className,
}: DataTableProps<TRow>): React.ReactElement {
  if (data.length === 0 && emptyState !== undefined) {
    return <div className={cn('w-full', className)}>{emptyState}</div>;
  }

  return (
    <div className={cn('w-full overflow-x-auto', className)}>
      <Table>
        {caption !== undefined && <caption className="sr-only">{caption}</caption>}
        <TableHeader>
          <TableRow>
            {columns.map((column) => (
              <TableHead
                key={column.id}
                className={cn(
                  column.align === 'right' && 'text-right',
                  column.align === 'center' && 'text-center',
                  column.className,
                )}
              >
                {column.header}
              </TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {data.map((row, rowIndex) => (
            <TableRow key={rowKey(row, rowIndex)}>
              {columns.map((column) => (
                <TableCell
                  key={column.id}
                  className={cn(
                    column.align === 'right' && 'text-right',
                    column.align === 'center' && 'text-center',
                    column.className,
                  )}
                >
                  {column.cell(row, rowIndex)}
                </TableCell>
              ))}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
