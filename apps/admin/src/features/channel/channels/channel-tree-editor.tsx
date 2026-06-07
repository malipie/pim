import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { FolderPlus, FolderTree, Loader2, MoveRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/components/ui/toast';
import { resolveLabel } from '@/features/catalog/attributes/list';
import type { TenantLocaleListResponse } from '@/features/settings/locales/types';
import { jsonFetch } from '@/lib/http';

interface ChannelNode {
  id: string;
  parentId: string | null;
  code: string;
  label?: Record<string, string> | null;
  path?: string | null;
  externalCode?: string | null;
}

type FormState =
  | { mode: 'addRoot' }
  | { mode: 'addChild'; parentId: string }
  | { mode: 'edit'; node: ChannelNode };

interface Props {
  channelId: string;
}

/**
 * CHC-09 (#1302) — manual editor for a channel's navigation tree: create the
 * tree (root), add sub-nodes, edit (name + target-channel node id), move a
 * node under a different parent, delete (cascades to descendants).
 *
 * Separate from the CHC-08 "Kategorie kanału" tab (which MAPS master
 * categories onto these nodes). A node carries only a name and an external id;
 * the name is stored in the multilingual `label` under the tenant's locale and
 * read back via {@link resolveLabel} (its first-value fallback covers any UI
 * language). The internal `code` slug is auto-defaulted server-side.
 */
export function ChannelTreeEditor({ channelId }: Props) {
  const { t, i18n } = useTranslation();
  const toast = useToast();
  const queryClient = useQueryClient();
  const lang = i18n.language;

  const treeQuery = useQuery({
    queryKey: ['channel', channelId, 'navigation-tree'],
    queryFn: () =>
      jsonFetch<ChannelNode[]>(`/api/channels/${channelId}/navigation-tree`, {
        accept: 'application/json',
      }),
  });

  const localesQuery = useQuery({
    queryKey: ['channel-tree', 'tenant-locales'],
    queryFn: () =>
      jsonFetch<TenantLocaleListResponse>('/api/tenant-locales', { accept: 'application/json' }),
  });

  const nodes = useMemo(() => treeQuery.data ?? [], [treeQuery.data]);
  const storageLocale = (localesQuery.data?.items ?? []).find((l) => l.isActive)?.code ?? 'pl';

  const childrenByParent = useMemo(() => {
    const map = new Map<string | null, ChannelNode[]>();
    for (const node of nodes) {
      const bucket = map.get(node.parentId) ?? [];
      bucket.push(node);
      map.set(node.parentId, bucket);
    }
    return map;
  }, [nodes]);

  const roots = childrenByParent.get(null) ?? [];

  const [form, setForm] = useState<FormState | null>(null);
  const [formName, setFormName] = useState('');
  const [formExternal, setFormExternal] = useState('');
  const [moveTarget, setMoveTarget] = useState<ChannelNode | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ChannelNode | null>(null);

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: ['channel', channelId, 'navigation-tree'] });

  const saveMutation = useMutation({
    mutationFn: () => {
      const body = {
        label: { [storageLocale]: formName.trim() },
        externalCode: formExternal.trim() === '' ? null : formExternal.trim(),
      };
      if (form?.mode === 'addRoot') {
        return jsonFetch(`/api/channels/${channelId}/navigation-tree`, {
          method: 'POST',
          accept: 'application/json',
          body,
        });
      }
      if (form?.mode === 'addChild') {
        return jsonFetch(`/api/channels/${channelId}/navigation-tree/nodes`, {
          method: 'POST',
          accept: 'application/json',
          body: { ...body, parentId: form.parentId },
        });
      }
      const nodeId = form?.mode === 'edit' ? form.node.id : '';
      return jsonFetch(`/api/channels/${channelId}/navigation-tree/nodes/${nodeId}`, {
        method: 'PATCH',
        accept: 'application/json',
        body,
      });
    },
    onSuccess: () => {
      toast.success(t('channels.channel_tree.saved'));
      void invalidate();
      setForm(null);
    },
    onError: () => toast.error(t('channels.channel_tree.save_error')),
  });

  const moveMutation = useMutation({
    mutationFn: (input: { nodeId: string; newParentId: string }) =>
      jsonFetch(`/api/channels/${channelId}/navigation-tree/nodes/${input.nodeId}/move`, {
        method: 'PATCH',
        accept: 'application/json',
        body: { newParentId: input.newParentId },
      }),
    onSuccess: () => {
      toast.success(t('channels.channel_tree.moved'));
      void invalidate();
      setMoveTarget(null);
    },
    onError: () => toast.error(t('channels.channel_tree.move_error')),
  });

  const deleteMutation = useMutation({
    mutationFn: (nodeId: string) =>
      jsonFetch(`/api/channels/${channelId}/navigation-tree/nodes/${nodeId}`, {
        method: 'DELETE',
        accept: 'application/json',
      }),
    onSuccess: () => {
      toast.success(t('channels.channel_tree.deleted'));
      void invalidate();
      setDeleteTarget(null);
    },
    onError: () => toast.error(t('channels.channel_tree.delete_error')),
  });

  const openAddRoot = () => {
    setForm({ mode: 'addRoot' });
    setFormName('');
    setFormExternal('');
  };
  const openAddChild = (parentId: string) => {
    setForm({ mode: 'addChild', parentId });
    setFormName('');
    setFormExternal('');
  };
  const openEdit = (node: ChannelNode) => {
    setForm({ mode: 'edit', node });
    setFormName(resolveLabel(node.label ?? null, lang).replace('—', ''));
    setFormExternal(node.externalCode ?? '');
  };

  const subtreeIds = (nodeId: string): Set<string> => {
    const ids = new Set<string>([nodeId]);
    const stack = [nodeId];
    for (let cur = stack.pop(); cur !== undefined; cur = stack.pop()) {
      for (const child of childrenByParent.get(cur) ?? []) {
        ids.add(child.id);
        stack.push(child.id);
      }
    }
    return ids;
  };

  if (treeQuery.isLoading) {
    return (
      <p className="text-sm text-muted-foreground">
        <Loader2 className="mr-2 inline size-3 animate-spin" />
        {t('channels.channel_tree.loading')}
      </p>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h3 className="text-base font-semibold">{t('channels.channel_tree.title')}</h3>
          <p className="text-sm text-muted-foreground">{t('channels.channel_tree.subtitle')}</p>
        </div>
        {roots.length > 0 ? (
          <Button variant="outline" size="sm" onClick={openAddRoot}>
            <FolderPlus className="size-4" />
            {t('channels.channel_tree.add_root')}
          </Button>
        ) : null}
      </div>

      {roots.length === 0 ? (
        <div className="rounded-xl border border-dashed px-4 py-8 text-center">
          <p className="mb-3 text-sm text-muted-foreground">{t('channels.channel_tree.empty')}</p>
          <Button onClick={openAddRoot}>
            <FolderPlus className="size-4" />
            {t('channels.channel_tree.add_root')}
          </Button>
        </div>
      ) : (
        <div className="rounded-xl border bg-card py-1" data-testid="chc-tree-editor">
          {roots.map((rootNode) => (
            <TreeRows
              key={rootNode.id}
              node={rootNode}
              depth={0}
              childrenByParent={childrenByParent}
              lang={lang}
              t={t}
              onAddChild={openAddChild}
              onEdit={openEdit}
              onMove={setMoveTarget}
              onDelete={setDeleteTarget}
            />
          ))}
        </div>
      )}

      {/* Add / edit dialog — 2 fields only: name + external id. */}
      <Dialog open={form !== null} onOpenChange={(open) => !open && setForm(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {form?.mode === 'edit'
                ? t('channels.channel_tree.edit_title')
                : form?.mode === 'addChild'
                  ? t('channels.channel_tree.add_child_title')
                  : t('channels.channel_tree.add_root')}
            </DialogTitle>
            <DialogDescription>{t('channels.channel_tree.form_hint')}</DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-1">
              <Label htmlFor="chc-node-name">{t('channels.channel_tree.name_label')}</Label>
              <Input
                id="chc-node-name"
                value={formName}
                onChange={(e) => setFormName(e.target.value)}
                placeholder={t('channels.channel_tree.name_placeholder')}
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor="chc-node-external">{t('channels.channel_tree.external_label')}</Label>
              <Input
                id="chc-node-external"
                value={formExternal}
                onChange={(e) => setFormExternal(e.target.value)}
                placeholder={t('channels.channel_tree.external_placeholder')}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setForm(null)}>
              {t('app.cancel')}
            </Button>
            <Button
              onClick={() => saveMutation.mutate()}
              disabled={saveMutation.isPending || formName.trim() === ''}
            >
              {saveMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : null}
              {t('app.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Move dialog — pick a new parent (excluding self + descendants). */}
      <Dialog open={moveTarget !== null} onOpenChange={(open) => !open && setMoveTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {t('channels.channel_tree.move_title', {
                name: moveTarget ? resolveLabel(moveTarget.label ?? null, lang) : '',
              })}
            </DialogTitle>
            <DialogDescription>{t('channels.channel_tree.move_hint')}</DialogDescription>
          </DialogHeader>
          <div className="max-h-72 space-y-1 overflow-auto">
            {moveTarget
              ? moveCandidates(roots, childrenByParent, subtreeIds(moveTarget.id)).map((c) => (
                  <button
                    key={c.node.id}
                    type="button"
                    onClick={() =>
                      moveTarget &&
                      moveMutation.mutate({ nodeId: moveTarget.id, newParentId: c.node.id })
                    }
                    disabled={moveMutation.isPending || c.node.id === moveTarget.parentId}
                    className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent disabled:opacity-40"
                    style={{ paddingLeft: `${c.depth * 16 + 8}px` }}
                  >
                    <FolderTree className="size-4 shrink-0 text-muted-foreground" />
                    {resolveLabel(c.node.label ?? null, lang)}
                  </button>
                ))
              : null}
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setMoveTarget(null)}>
              {t('app.cancel')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete confirm — warns about descendant cascade. */}
      <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('channels.channel_tree.delete_title')}</DialogTitle>
            <DialogDescription>
              {deleteTarget?.parentId === null
                ? t('channels.channel_tree.delete_root_body')
                : t('channels.channel_tree.delete_body')}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setDeleteTarget(null)}>
              {t('app.cancel')}
            </Button>
            <Button
              variant="destructive"
              onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? <Loader2 className="size-4 animate-spin" /> : null}
              {t('channels.channel_tree.delete_confirm')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function TreeRows({
  node,
  depth,
  childrenByParent,
  lang,
  t,
  onAddChild,
  onEdit,
  onMove,
  onDelete,
}: {
  node: ChannelNode;
  depth: number;
  childrenByParent: Map<string | null, ChannelNode[]>;
  lang: string;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onAddChild: (parentId: string) => void;
  onEdit: (node: ChannelNode) => void;
  onMove: (node: ChannelNode) => void;
  onDelete: (node: ChannelNode) => void;
}) {
  const children = childrenByParent.get(node.id) ?? [];
  const isRoot = node.parentId === null;
  return (
    <div>
      <div
        className="group flex items-center justify-between gap-2 px-3 py-1.5 hover:bg-muted/40"
        style={{ paddingLeft: `${depth * 20 + 12}px` }}
      >
        <div className="flex min-w-0 items-center gap-2">
          <FolderTree className="size-4 shrink-0 text-muted-foreground" />
          <span className="truncate text-sm font-medium">
            {resolveLabel(node.label ?? null, lang)}
          </span>
          {node.externalCode ? (
            <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">
              #{node.externalCode}
            </span>
          ) : null}
        </div>
        <div className="flex shrink-0 items-center gap-0.5 opacity-60 group-hover:opacity-100">
          <Button
            size="sm"
            variant="ghost"
            onClick={() => onAddChild(node.id)}
            title={t('channels.channel_tree.add_child')}
          >
            <Plus className="size-4" />
          </Button>
          <Button
            size="sm"
            variant="ghost"
            onClick={() => onEdit(node)}
            title={t('channels.channel_tree.edit')}
          >
            <Pencil className="size-4" />
          </Button>
          {isRoot ? null : (
            <Button
              size="sm"
              variant="ghost"
              onClick={() => onMove(node)}
              title={t('channels.channel_tree.move')}
            >
              <MoveRight className="size-4" />
            </Button>
          )}
          <Button
            size="sm"
            variant="ghost"
            onClick={() => onDelete(node)}
            title={t('channels.channel_tree.delete')}
          >
            <Trash2 className="size-4" />
          </Button>
        </div>
      </div>
      {children.map((child) => (
        <TreeRows
          key={child.id}
          node={child}
          depth={depth + 1}
          childrenByParent={childrenByParent}
          lang={lang}
          t={t}
          onAddChild={onAddChild}
          onEdit={onEdit}
          onMove={onMove}
          onDelete={onDelete}
        />
      ))}
    </div>
  );
}

/**
 * Flattened candidate parents for the move dialog, in tree order, excluding the
 * moving node and its whole subtree.
 */
function moveCandidates(
  roots: ChannelNode[],
  childrenByParent: Map<string | null, ChannelNode[]>,
  excluded: Set<string>,
): Array<{ node: ChannelNode; depth: number }> {
  const out: Array<{ node: ChannelNode; depth: number }> = [];
  const walk = (node: ChannelNode, depth: number) => {
    if (excluded.has(node.id)) return;
    out.push({ node, depth });
    for (const child of childrenByParent.get(node.id) ?? []) {
      walk(child, depth + 1);
    }
  };
  for (const root of roots) {
    walk(root, 0);
  }
  return out;
}
