import { useList } from '@refinedev/core';
import { ChevronDown, ChevronRight, Eye, FolderTree, Move } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { ModelingPageHeader } from '@/components/modeling/modeling-page-header';
import { Button } from '@/components/ui/button';
import { MockBadge } from '@/components/ui/mock-badge';
import { cn } from '@/lib/utils';

interface CategoryEntry {
  id: string;
  code: string;
  path?: string | null;
  enabled?: boolean;
  attributesIndexed?: Record<string, unknown>;
  parent?: { id: string } | string | null;
}

interface TreeNode {
  id: string;
  code: string;
  label: string;
  path: string;
  depth: number;
  children: TreeNode[];
}

export function CategoriesTreePage() {
  const { t } = useTranslation();
  const { result, query } = useList<CategoryEntry>({
    resource: 'categories',
    pagination: { mode: 'off' },
  });

  const tree = useMemo(() => buildTree(result.data ?? []), [result.data]);

  return (
    <div className="space-y-6">
      <ModelingPageHeader
        caption={t('categories.list_caption', {
          defaultValue: '{{count}} kategorii',
          count: result.data?.length ?? 0,
        })}
        title={t('categories.list_title')}
        description={t('categories.list_description', {
          defaultValue:
            'Hierarchiczna taksonomia oparta o ltree (Postgres). Każdy node może mieć przypisaną grupę atrybutów; wartości atrybutów ustawione na rodzicu są dziedziczone w dół drzewa, chyba że dziecko je nadpisze.',
        })}
        ctaLabel={t('categories.create_action', { defaultValue: '+ Nowa kategoria' })}
        trailing={
          <span className="inline-flex items-center gap-1.5">
            <button
              type="button"
              disabled
              aria-disabled="true"
              className="inline-flex cursor-not-allowed items-center gap-1.5 rounded-md border border-line px-2.5 py-1 text-[12px] text-muted-foreground"
            >
              <Move className="size-3.5" />
              {t('categories.move_action', { defaultValue: 'Przenieś gałąź' })}
            </button>
            <MockBadge
              tooltip={t('categories.move_mock_tooltip', {
                defaultValue: 'MOCK · Drag-and-drop wymaga PATCH /api/categories/{id}/move',
              })}
            />
          </span>
        }
      />

      <div className="relative rounded-2xl border border-line bg-surface p-3 soft-shadow">
        {query.isLoading ? (
          <p className="py-6 text-center text-sm text-muted-foreground">{t('app.loading')}</p>
        ) : tree.length === 0 ? (
          <p className="py-6 text-center text-sm text-muted-foreground">{t('categories.empty')}</p>
        ) : (
          <ul className="space-y-1" aria-label={t('categories.tree_aria')}>
            {tree.map((node) => (
              <TreeRow key={node.id} node={node} />
            ))}
          </ul>
        )}
      </div>

      <p className="text-xs text-muted-foreground">{t('categories.write_deferred_note')}</p>
    </div>
  );
}

function TreeRow({ node }: { node: TreeNode }) {
  const { t } = useTranslation();
  const [expanded, setExpanded] = useState(node.depth < 1);
  const hasChildren = node.children.length > 0;

  return (
    <li>
      <div
        className={cn(
          'flex items-center gap-2 rounded px-2 py-1.5 text-sm transition-colors',
          'hover:bg-accent hover:text-accent-foreground',
        )}
        style={{ paddingLeft: `${node.depth * 16 + 8}px` }}
      >
        {hasChildren ? (
          <button
            type="button"
            onClick={() => setExpanded((prev) => !prev)}
            className="rounded p-0.5 hover:bg-muted"
            aria-label={
              expanded
                ? t('categories.collapse', { defaultValue: 'Collapse' })
                : t('categories.expand', { defaultValue: 'Expand' })
            }
          >
            {expanded ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
          </button>
        ) : (
          <span className="inline-block w-5">
            <FolderTree className="size-3.5 text-muted-foreground" />
          </span>
        )}
        <span className="font-mono text-xs text-muted-foreground">{node.code}</span>
        <span className="font-medium">{node.label}</span>
        <Button asChild variant="ghost" size="sm" className="ml-auto">
          <Link to={`/categories/${node.id}`}>
            <Eye className="size-4" />
            <span className="sr-only">{t('categories.actions.view')}</span>
          </Link>
        </Button>
      </div>
      {hasChildren && expanded ? (
        <ul className="space-y-1">
          {node.children.map((child) => (
            <TreeRow key={child.id} node={child} />
          ))}
        </ul>
      ) : null}
    </li>
  );
}

function buildTree(rows: CategoryEntry[]): TreeNode[] {
  // ltree path is "root.parent.code"; depth = label segments.
  const nodes: TreeNode[] = rows.map((row) => {
    const path = row.path ?? row.code;
    const segments = path.split('.').filter((s) => s.length > 0);
    const label = labelFromAttributes(row.attributesIndexed) ?? row.code;
    return {
      id: row.id,
      code: row.code,
      label,
      path,
      depth: Math.max(0, segments.length - 1),
      children: [],
    };
  });

  // Sort by path so parents come before children — buildTree pass uses prefix
  // matching to attach children to the deepest parent in the candidate set.
  nodes.sort((a, b) => a.path.localeCompare(b.path));

  const roots: TreeNode[] = [];
  for (const node of nodes) {
    if (node.depth === 0) {
      roots.push(node);
      continue;
    }
    const parentPath = node.path.split('.').slice(0, -1).join('.');
    const parent = nodes.find((candidate) => candidate.path === parentPath);
    if (parent) {
      parent.children.push(node);
    } else {
      // Orphan — render at root so operator notices.
      roots.push(node);
    }
  }
  return roots;
}

function labelFromAttributes(attrs: Record<string, unknown> | undefined | null): string | null {
  if (!attrs) return null;
  const name = attrs.name;
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    const map = name as Record<string, string>;
    return map.en ?? map.pl ?? Object.values(map)[0] ?? null;
  }
  return null;
}
