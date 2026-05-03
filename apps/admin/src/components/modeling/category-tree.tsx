import { ChevronRight, FolderTree } from 'lucide-react';
import { useState } from 'react';

import { cn } from '@/lib/utils';

export interface CategoryTreeNode {
  id: string;
  code: string;
  label: string;
  path: string;
  depth: number;
  icon?: string | null;
  groupColors?: string[];
  instanceCount?: number;
  children: CategoryTreeNode[];
}

interface Props {
  nodes: CategoryTreeNode[];
  selectedId?: string;
  onSelect: (id: string) => void;
  /** IDs disabled (e.g. when reusing the tree inside a Move dialog to forbid the moving subtree). */
  disabledIds?: Set<string>;
  /** Pre-expanded node ids — derived from URL search param in the list page. */
  initialExpanded?: Set<string>;
}

/**
 * VIEW-04 (#408) — recursive ltree renderer for the modeling Category
 * tree (left panel of `/modeling/categories`). Reused inside
 * {@link MoveCategoryDialog} for the parent-picker, hence the optional
 * `disabledIds` prop to forbid moving a node into its own subtree.
 *
 * Matches the prototype `groups-categories.jsx:425–455` row layout:
 *   - `rounded-xl bg-zinc-900 text-white` for the selected node.
 *   - 4×4 chevron button toggling expand/collapse (rotated 90deg when expanded).
 *   - Optional emoji glyph + bold name + tabular instance count.
 *   - Up to 3 group color dots as a per-row indicator + "+N" overflow.
 */
export function CategoryTree({ nodes, selectedId, onSelect, disabledIds, initialExpanded }: Props) {
  const [expanded, setExpanded] = useState<Set<string>>(() => initialExpanded ?? new Set());

  const toggle = (id: string) => {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  return (
    <ul className="space-y-1">
      {nodes.map((node) => (
        <CategoryTreeRow
          key={node.id}
          node={node}
          selectedId={selectedId}
          onSelect={onSelect}
          disabledIds={disabledIds}
          expanded={expanded}
          onToggle={toggle}
        />
      ))}
    </ul>
  );
}

function CategoryTreeRow({
  node,
  selectedId,
  onSelect,
  disabledIds,
  expanded,
  onToggle,
}: {
  node: CategoryTreeNode;
  selectedId?: string;
  onSelect: (id: string) => void;
  disabledIds?: Set<string>;
  expanded: Set<string>;
  onToggle: (id: string) => void;
}) {
  const hasChildren = node.children.length > 0;
  const isExpanded = expanded.has(node.id);
  const isSelected = selectedId === node.id;
  const isDisabled = disabledIds?.has(node.id) ?? false;
  const groupColors = node.groupColors ?? [];

  return (
    <li>
      <button
        type="button"
        disabled={isDisabled}
        onClick={() => onSelect(node.id)}
        className={cn(
          'flex w-full items-center gap-1.5 rounded-xl px-2 py-1.5 text-left transition-colors',
          isSelected ? 'bg-zinc-900 text-white' : 'hover:bg-zinc-100/70',
          isDisabled && 'cursor-not-allowed opacity-50',
        )}
        style={{ paddingLeft: `${8 + node.depth * 16}px` }}
      >
        {hasChildren ? (
          <ChevronRight
            className={cn(
              'size-3.5 transition-transform',
              isSelected ? 'text-white/70' : 'text-zinc-400',
            )}
            style={{ transform: isExpanded ? 'rotate(90deg)' : 'rotate(0deg)' }}
            aria-hidden
            onClick={(e) => {
              e.stopPropagation();
              onToggle(node.id);
            }}
          />
        ) : (
          <span className="grid size-5 place-items-center text-zinc-300">
            <FolderTree className="size-3" />
          </span>
        )}
        <span className="text-[14px]">{node.icon ?? '📁'}</span>
        <span className="text-[13px] font-medium">{node.label}</span>
        {node.instanceCount !== undefined ? (
          <span
            className={cn(
              'ml-1 font-mono text-[10.5px] tabular-nums',
              isSelected ? 'text-white/50' : 'text-zinc-400',
            )}
          >
            {node.instanceCount}
          </span>
        ) : null}
        {groupColors.length > 0 ? (
          <span className="ml-auto inline-flex items-center gap-0.5">
            {groupColors.slice(0, 3).map((color) => (
              <span
                key={color}
                className="size-2 rounded-full"
                style={{ background: color }}
                aria-hidden
              />
            ))}
            {groupColors.length > 3 ? (
              <span
                className={cn('ml-0.5 text-[10px]', isSelected ? 'text-white/50' : 'text-zinc-400')}
              >
                +{groupColors.length - 3}
              </span>
            ) : null}
          </span>
        ) : null}
      </button>
      {hasChildren && isExpanded ? (
        <ul className="space-y-1">
          {node.children.map((child) => (
            <CategoryTreeRow
              key={child.id}
              node={child}
              selectedId={selectedId}
              onSelect={onSelect}
              disabledIds={disabledIds}
              expanded={expanded}
              onToggle={onToggle}
            />
          ))}
        </ul>
      ) : null}
    </li>
  );
}

/**
 * Convert a flat list of CatalogObject (kind=category) rows into the
 * recursive shape the renderer expects. Matches the algorithm in
 * `apps/admin/src/features/catalog/categories/list.tsx` pre-rebuild —
 * extracted as a util so other consumers (e.g. MoveCategoryDialog) can
 * reuse it.
 */
export function buildCategoryTree(
  rows: Array<{
    id: string;
    code: string;
    path?: string | null;
    icon?: string | null;
    instanceCount?: number;
    groupColors?: string[];
    attributesIndexed?: Record<string, unknown>;
  }>,
): CategoryTreeNode[] {
  const nodes: CategoryTreeNode[] = rows.map((row) => {
    const path = row.path ?? row.code;
    const segments = path.split('.').filter((s) => s.length > 0);
    const label = labelFromAttributes(row.attributesIndexed) ?? row.code;
    return {
      id: row.id,
      code: row.code,
      label,
      path,
      depth: Math.max(0, segments.length - 1),
      icon: row.icon,
      groupColors: row.groupColors,
      instanceCount: row.instanceCount,
      children: [],
    };
  });

  nodes.sort((a, b) => a.path.localeCompare(b.path));

  const roots: CategoryTreeNode[] = [];
  for (const node of nodes) {
    if (node.depth === 0) {
      roots.push(node);
      continue;
    }
    const parentPath = node.path.split('.').slice(0, -1).join('.');
    const parent = nodes.find((c) => c.path === parentPath);
    if (parent) {
      parent.children.push(node);
    } else {
      roots.push(node);
    }
  }

  return roots;
}

function labelFromAttributes(attrs: Record<string, unknown> | null | undefined): string | null {
  if (!attrs) return null;
  const name = attrs.name;
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    const map = name as Record<string, string>;
    return map.pl ?? map.en ?? Object.values(map)[0] ?? null;
  }
  return null;
}
