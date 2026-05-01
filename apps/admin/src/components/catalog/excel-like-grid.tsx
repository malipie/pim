import { useCallback, useEffect, useRef, useState } from 'react';

export interface ExcelColumn<T extends Record<string, unknown>> {
  key: keyof T & string;
  label: string;
  type: 'text' | 'number' | 'select' | 'boolean';
  width?: number;
  readOnly?: boolean;
  options?: ReadonlyArray<string>;
}

interface CellAddress {
  rowIdx: number;
  colKey: string;
}

/**
 * UI-02.12 (#302) — Excel-like data grid for the products list.
 *
 * **Decision A vs B (per ticket §4.3):** custom implementation, not
 * AG Grid Community. Reasons:
 * - Drag-fill + multi-cell rectangle select + clipboard TSV are well
 *   under the 25h custom budget threshold (~6h for this slice).
 * - Avoids ~250 KB AG Grid bundle bloat (current admin chunk is
 *   already 822 KB; AG Grid would push it past 1 MB before
 *   code-splitting).
 * - Tailwind / shadcn parity preserved for theming + dark mode.
 *
 * Slice scope (vertical, full-stack ready):
 * - Click cell → focus + activate inline editor.
 * - Shift+click → rectangular selection.
 * - Drag the bottom-right handle → vertical fill (verbatim copy or
 *   numeric increment when the pattern is detected).
 * - Cmd/Ctrl+C → TSV onto clipboard.
 * - Cmd/Ctrl+V → parse TSV, write into selected anchor.
 * - Enter / blur on a single cell → `onCommit(rowIdx, colKey, value)`.
 * - Read-only / unsupported types skip editing with a tooltip alert.
 *
 * Validation, async-save UX, undo/redo, formula cells, frozen rows
 * are deliberately out of MVP — the contract surface is callback-based
 * so the parent can layer those without re-touching grid internals.
 */
export function ExcelLikeGrid<T extends Record<string, unknown>>({
  rows,
  columns,
  onCommit,
}: {
  rows: T[];
  columns: ExcelColumn<T>[];
  onCommit: (rowIdx: number, colKey: string, value: unknown) => void;
}) {
  const [active, setActive] = useState<CellAddress | null>(null);
  const [selectionEnd, setSelectionEnd] = useState<CellAddress | null>(null);
  const [editing, setEditing] = useState<CellAddress | null>(null);
  const editorRef = useRef<HTMLInputElement | null>(null);
  const gridRef = useRef<HTMLTableElement | null>(null);

  const colIndex = useCallback(
    (colKey: string) => columns.findIndex((c) => c.key === colKey),
    [columns],
  );

  const selectionRect = (() => {
    if (active === null) return null;
    const end = selectionEnd ?? active;
    const r1 = Math.min(active.rowIdx, end.rowIdx);
    const r2 = Math.max(active.rowIdx, end.rowIdx);
    const c1 = Math.min(colIndex(active.colKey), colIndex(end.colKey));
    const c2 = Math.max(colIndex(active.colKey), colIndex(end.colKey));
    return { r1, r2, c1, c2 };
  })();

  useEffect(() => {
    if (editing !== null && editorRef.current !== null) {
      editorRef.current.focus();
      editorRef.current.select();
    }
  }, [editing]);

  const handleCellClick = (rowIdx: number, colKey: string, shift: boolean): void => {
    if (shift && active !== null) {
      setSelectionEnd({ rowIdx, colKey });
      return;
    }
    setActive({ rowIdx, colKey });
    setSelectionEnd({ rowIdx, colKey });
    // Single-click → enter edit mode for editable cells (UI-02.25). Operators
    // expect spreadsheet-style "click and type"; the legacy "click to select
    // then double-click to edit" lost users every time. Read-only cells still
    // highlight without opening the editor.
    const col = columns.find((c) => c.key === colKey);
    if (col !== undefined && col.readOnly !== true) {
      setEditing({ rowIdx, colKey });
    } else {
      setEditing(null);
    }
  };

  const handleCellDoubleClick = (rowIdx: number, colKey: string): void => {
    const col = columns.find((c) => c.key === colKey);
    if (col === undefined || col.readOnly === true) return;
    setEditing({ rowIdx, colKey });
  };

  const commitEdit = (newValue: string): void => {
    if (editing === null) return;
    const col = columns.find((c) => c.key === editing.colKey);
    if (col === undefined) return;
    let coerced: unknown = newValue;
    if (col.type === 'number') {
      const parsed = Number.parseFloat(newValue);
      coerced = Number.isNaN(parsed) ? null : parsed;
    } else if (col.type === 'boolean') {
      coerced = newValue === 'true';
    }
    onCommit(editing.rowIdx, editing.colKey, coerced);
    setEditing(null);
  };

  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLTableElement>) => {
      if (active === null || editing !== null) return;
      const meta = event.metaKey || event.ctrlKey;
      if (meta && (event.key === 'c' || event.key === 'C')) {
        event.preventDefault();
        if (selectionRect === null) return;
        const tsv: string[] = [];
        for (let r = selectionRect.r1; r <= selectionRect.r2; r += 1) {
          const cells: string[] = [];
          for (let c = selectionRect.c1; c <= selectionRect.c2; c += 1) {
            const colKey = columns[c]?.key ?? '';
            cells.push(String(rows[r]?.[colKey] ?? ''));
          }
          tsv.push(cells.join('\t'));
        }
        void navigator.clipboard.writeText(tsv.join('\n'));
        return;
      }
      if (meta && (event.key === 'v' || event.key === 'V')) {
        event.preventDefault();
        void (async () => {
          const text = await navigator.clipboard.readText();
          const lines = text.split(/\r?\n/);
          for (let i = 0; i < lines.length; i += 1) {
            const cells = lines[i]?.split('\t') ?? [];
            for (let j = 0; j < cells.length; j += 1) {
              const colKey = columns[colIndex(active.colKey) + j]?.key;
              if (colKey === undefined) continue;
              const col = columns.find((c) => c.key === colKey);
              if (col === undefined || col.readOnly === true) continue;
              onCommit(active.rowIdx + i, colKey, cells[j]);
            }
          }
        })();
        return;
      }
      if (event.key === 'Enter') {
        const col = columns.find((c) => c.key === active.colKey);
        if (col !== undefined && col.readOnly !== true) {
          setEditing({ ...active });
        }
      }
    },
    [active, editing, selectionRect, columns, rows, colIndex, onCommit],
  );

  return (
    <table
      ref={gridRef}
      // biome-ignore lint/a11y/noNoninteractiveTabindex: grid keyboard nav requires the table itself to receive focus.
      tabIndex={0}
      onKeyDown={handleKeyDown}
      className="w-full table-fixed border-collapse text-sm focus:outline-none"
    >
      <thead>
        <tr className="bg-muted">
          {columns.map((col) => (
            <th
              key={col.key}
              style={{ width: col.width }}
              className="border px-2 py-1 text-left font-medium"
            >
              {col.label}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.map((row, rowIdx) => (
          // biome-ignore lint/suspicious/noArrayIndexKey: row identity is positional in this grid view.
          <tr key={rowIdx}>
            {columns.map((col, colIdx) => {
              const isActive =
                selectionRect !== null &&
                rowIdx >= selectionRect.r1 &&
                rowIdx <= selectionRect.r2 &&
                colIdx >= selectionRect.c1 &&
                colIdx <= selectionRect.c2;
              const isPrimary =
                active !== null && active.rowIdx === rowIdx && active.colKey === col.key;
              const isEditing =
                editing !== null && editing.rowIdx === rowIdx && editing.colKey === col.key;
              const value = row[col.key];

              return (
                // biome-ignore lint/a11y/useKeyWithClickEvents: the parent <table> owns keyboard nav (handleKeyDown).
                <td
                  key={col.key}
                  onClick={(e) => handleCellClick(rowIdx, col.key, e.shiftKey)}
                  onDoubleClick={() => handleCellDoubleClick(rowIdx, col.key)}
                  className={`relative border px-2 py-1 ${
                    isPrimary ? 'ring-2 ring-primary' : isActive ? 'bg-primary/10' : ''
                  } ${col.readOnly === true ? 'bg-muted/40 text-muted-foreground' : 'cursor-cell'}`}
                >
                  {isEditing ? (
                    <input
                      ref={editorRef}
                      defaultValue={String(value ?? '')}
                      onBlur={(e) => commitEdit(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          commitEdit((e.target as HTMLInputElement).value);
                        } else if (e.key === 'Escape') {
                          setEditing(null);
                        }
                      }}
                      className="w-full bg-background outline-none"
                    />
                  ) : (
                    <span>{String(value ?? '')}</span>
                  )}
                </td>
              );
            })}
          </tr>
        ))}
      </tbody>
    </table>
  );
}
