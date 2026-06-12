import { useQuery } from '@tanstack/react-query';
import { FolderTree } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface NavNode {
  id: string;
  parentId: string | null;
  code: string;
  label: Record<string, string>;
  path: string;
}

interface TreeNode extends NavNode {
  depth: number;
  children: TreeNode[];
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  channelId: string | null;
  channelName: string;
  onPick: (nodeId: string) => void | Promise<void>;
}

function resolveLabel(label: Record<string, string>, lang: string, code: string): string {
  return label[lang] ?? label.pl ?? label.en ?? Object.values(label)[0] ?? code;
}

function buildTree(nodes: NavNode[]): TreeNode[] {
  const byId = new Map<string, TreeNode>();
  for (const node of nodes) {
    byId.set(node.id, { ...node, depth: 0, children: [] });
  }
  const roots: TreeNode[] = [];
  for (const node of byId.values()) {
    if (node.parentId !== null && byId.has(node.parentId)) {
      const parent = byId.get(node.parentId);
      if (parent) {
        node.depth = parent.depth + 1;
        parent.children.push(node);
      }
    } else {
      roots.push(node);
    }
  }
  return roots;
}

function flatten(nodes: TreeNode[]): TreeNode[] {
  const out: TreeNode[] = [];
  const walk = (list: TreeNode[]): void => {
    for (const node of list) {
      out.push(node);
      walk(node.children);
    }
  };
  walk(nodes);
  return out;
}

/**
 * CHC-03 (#1286) — modal that renders a channel's navigation tree
 * (`GET /api/channels/{id}/navigation-tree`) and lets the operator pick the
 * node a product should land in.
 */
export function ChannelNodePickerDialog({
  open,
  onOpenChange,
  channelId,
  channelName,
  onPick,
}: Props) {
  const { t, i18n } = useTranslation();
  const [selected, setSelected] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ['channels', channelId, 'navigation-tree'],
    queryFn: async () =>
      jsonFetch<NavNode[]>(`/api/channels/${channelId}/navigation-tree`, {
        accept: 'application/json',
      }),
    enabled: open && channelId !== null,
  });

  const rows = data ? flatten(buildTree(data)) : [];

  const handlePick = async (): Promise<void> => {
    if (selected === null || saving) return;
    setSaving(true);
    try {
      await onPick(selected);
      onOpenChange(false);
      setSelected(null);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) setSelected(null);
        onOpenChange(next);
      }}
    >
      <DialogContent className="max-w-xl">
        <header className="mb-3 flex items-center gap-2">
          <FolderTree className="size-4 text-orange-600" />
          <h2 className="text-base font-semibold">
            {t('products.detail.placements.picker_title', {
              defaultValue: 'Wybierz węzeł — {{channel}}',
              channel: channelName,
            })}
          </h2>
        </header>

        <div className="max-h-[55vh] overflow-y-auto rounded-xl border border-zinc-100 bg-white p-2">
          {isLoading ? (
            <p className="p-3 text-[12.5px] text-muted-foreground">
              {t('app.loading', { defaultValue: 'Ładowanie…' })}
            </p>
          ) : rows.length === 0 ? (
            <p className="p-3 text-[12.5px] text-muted-foreground">
              {t('products.detail.placements.empty_tree', {
                defaultValue: 'Ten kanał nie ma jeszcze drzewa nawigacyjnego.',
              })}
            </p>
          ) : (
            <ul>
              {rows.map((node) => (
                <li key={node.id}>
                  <button
                    type="button"
                    onClick={() => setSelected(node.id)}
                    style={{ paddingLeft: `${node.depth * 16 + 8}px` }}
                    className={cn(
                      'flex w-full items-center gap-2 rounded-lg py-1.5 pr-2 text-left text-[12.5px] hover:bg-zinc-50',
                      selected === node.id && 'bg-orange-50 text-orange-900',
                    )}
                    aria-pressed={selected === node.id}
                  >
                    <FolderTree className="size-3.5 shrink-0 text-zinc-500" />
                    <span className="font-medium">
                      {resolveLabel(node.label, i18n.language, node.code)}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        <footer className="mt-4 flex items-center justify-end gap-2">
          <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={saving}>
            {t('app.cancel', { defaultValue: 'Anuluj' })}
          </Button>
          <Button onClick={() => void handlePick()} disabled={selected === null || saving}>
            {t('products.detail.placements.assign', { defaultValue: 'Przypisz' })}
          </Button>
        </footer>
      </DialogContent>
    </Dialog>
  );
}
