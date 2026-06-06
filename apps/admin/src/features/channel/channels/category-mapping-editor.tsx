import { useList } from '@refinedev/core';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { FolderTree, Link2, Loader2, Trash2 } from 'lucide-react';
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
import { MultiSelect } from '@/components/ui/multi-select';
import { useToast } from '@/components/ui/toast';
import { resolveLabel } from '@/features/catalog/attributes/list';
import { jsonFetch } from '@/lib/http';

interface MasterCategory {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  path?: string | null;
}

interface ObjectTypeMappingRow {
  objectType?: { id: string; code: string; label?: Record<string, string> | null };
}

interface ChannelNode {
  id: string;
  parentId: string | null;
  code: string;
  label?: Record<string, string> | null;
  path?: string | null;
}

interface NodeMappingRow {
  masterCategoryId: string;
  channelNodeIds: string[];
}

interface PlacementCountRow {
  nodeId: string;
  productCount: number;
}

interface Collection<T> {
  member: T[];
}

interface PublishedObjectType {
  code: string;
  label: string;
}

interface Props {
  channelId: string;
}

/**
 * CHC-08 (#1291) — split-view editor wiring master categories (left) to a
 * channel's navigation nodes (right). Saving a mapping triggers CHC-07
 * auto-assignment so products inherit the placement.
 *
 * The ObjectType tabs reflect the ObjectTypes the channel actually publishes
 * (DISTINCT objectType from `channel_object_type_mappings`). The master
 * category list is shared across tabs: a `ChannelCategoryNodeMapping` is
 * ObjectType-agnostic (master → channel nodes), so every published type maps
 * against the same master tree. The tabs scope the operator's mental model,
 * not the mappable set.
 */
export function ChannelCategoryMappingEditor({ channelId }: Props) {
  const { t, i18n } = useTranslation();
  const toast = useToast();
  const queryClient = useQueryClient();
  const lang = i18n.language;

  const otMappingList = useList<ObjectTypeMappingRow>({
    resource: 'channel_object_type_mappings',
    filters: [{ field: 'channel', operator: 'eq', value: channelId }],
    pagination: { mode: 'off' },
  });

  const categoryList = useList<MasterCategory>({
    resource: 'categories',
    pagination: { mode: 'off' },
  });

  const nodesQuery = useQuery({
    queryKey: ['channel', channelId, 'navigation-tree'],
    queryFn: () =>
      jsonFetch<ChannelNode[]>(`/api/channels/${channelId}/navigation-tree`, {
        accept: 'application/json',
      }),
  });

  const mappingsQuery = useQuery({
    queryKey: ['channel', channelId, 'node-mappings'],
    queryFn: () =>
      jsonFetch<Collection<NodeMappingRow>>(`/api/channels/${channelId}/node-mappings`, {
        accept: 'application/json',
      }),
  });

  const countsQuery = useQuery({
    queryKey: ['channel', channelId, 'node-placement-counts'],
    queryFn: () =>
      jsonFetch<Collection<PlacementCountRow>>(`/api/channels/${channelId}/node-placement-counts`, {
        accept: 'application/json',
      }),
  });

  const [activeOt, setActiveOt] = useState<string | null>(null);
  const [dialogMaster, setDialogMaster] = useState<MasterCategory | null>(null);
  const [dialogSelection, setDialogSelection] = useState<string[]>([]);
  const [clearOpen, setClearOpen] = useState(false);

  const publishedTypes = useMemo<PublishedObjectType[]>(() => {
    const seen = new Map<string, PublishedObjectType>();
    for (const row of otMappingList.result.data) {
      const ot = row.objectType;
      if (!ot || seen.has(ot.code)) continue;
      seen.set(ot.code, { code: ot.code, label: resolveLabel(ot.label ?? null, lang) ?? ot.code });
    }
    return [...seen.values()];
  }, [otMappingList.result.data, lang]);

  const nodes = nodesQuery.data ?? [];
  const nodeLabelById = useMemo(() => buildNodePathLabels(nodes, lang), [nodes, lang]);

  const mappingByMaster = useMemo(() => {
    const map = new Map<string, string[]>();
    for (const row of mappingsQuery.data?.member ?? []) {
      map.set(row.masterCategoryId, row.channelNodeIds);
    }
    return map;
  }, [mappingsQuery.data]);

  const countByNode = useMemo(() => {
    const map = new Map<string, number>();
    for (const row of countsQuery.data?.member ?? []) {
      map.set(row.nodeId, row.productCount);
    }
    return map;
  }, [countsQuery.data]);

  const saveMapping = useMutation({
    mutationFn: (input: { masterId: string; nodeIds: string[] }) =>
      jsonFetch(`/api/channels/${channelId}/node-mappings/${input.masterId}`, {
        method: 'PUT',
        accept: 'application/json',
        body: { nodeIds: input.nodeIds },
      }),
    onSuccess: () => {
      toast.success(t('channels.category_mapping.save_success'));
      void queryClient.invalidateQueries({ queryKey: ['channel', channelId, 'node-mappings'] });
      void queryClient.invalidateQueries({
        queryKey: ['channel', channelId, 'node-placement-counts'],
      });
      setDialogMaster(null);
    },
    onError: () => toast.error(t('channels.category_mapping.save_error')),
  });

  const clearAll = useMutation({
    mutationFn: () =>
      jsonFetch(`/api/channels/${channelId}/node-mappings`, {
        method: 'DELETE',
        accept: 'application/json',
      }),
    onSuccess: () => {
      toast.success(t('channels.category_mapping.clear_success'));
      void queryClient.invalidateQueries({ queryKey: ['channel', channelId, 'node-mappings'] });
      void queryClient.invalidateQueries({
        queryKey: ['channel', channelId, 'node-placement-counts'],
      });
      setClearOpen(false);
    },
    onError: () => toast.error(t('channels.category_mapping.clear_error')),
  });

  if (otMappingList.query.isLoading || categoryList.query.isLoading || nodesQuery.isLoading) {
    return (
      <p className="text-sm text-muted-foreground">
        <Loader2 className="mr-2 inline size-3 animate-spin" />
        {t('channels.category_mapping.loading')}
      </p>
    );
  }

  const masters = categoryList.result.data;

  const openDialog = (master: MasterCategory) => {
    setDialogMaster(master);
    setDialogSelection(mappingByMaster.get(master.id) ?? []);
  };

  const nodeOptions = nodes.map((n) => ({
    value: n.id,
    label: nodeLabelById.get(n.id) ?? n.code,
  }));

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h3 className="text-base font-semibold">{t('channels.category_mapping.title')}</h3>
          <p className="text-sm text-muted-foreground">{t('channels.category_mapping.subtitle')}</p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => setClearOpen(true)}
          disabled={mappingByMaster.size === 0}
        >
          <Trash2 className="size-4" />
          {t('channels.category_mapping.clear_all')}
        </Button>
      </div>

      {publishedTypes.length > 0 ? (
        <div
          className="flex flex-wrap gap-1"
          role="tablist"
          aria-label={t('channels.category_mapping.ot_tabs_aria')}
        >
          {publishedTypes.map((ot) => {
            const active = activeOt === null ? ot === publishedTypes[0] : activeOt === ot.code;
            return (
              <button
                key={ot.code}
                type="button"
                role="tab"
                aria-selected={active}
                onClick={() => setActiveOt(ot.code)}
                className={
                  active
                    ? 'rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground'
                    : 'rounded-md border px-3 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground'
                }
              >
                {ot.label}
              </button>
            );
          })}
        </div>
      ) : (
        <p className="rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground">
          {t('channels.category_mapping.no_published_types')}
        </p>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <section className="rounded-xl border bg-card">
          <header className="border-b px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {t('channels.category_mapping.master_panel')}
          </header>
          <ul className="divide-y" data-testid="chc-master-list">
            {masters.length === 0 ? (
              <li className="px-4 py-3 text-sm text-muted-foreground">
                {t('channels.category_mapping.no_categories')}
              </li>
            ) : (
              masters.map((master) => {
                const mapped = mappingByMaster.get(master.id) ?? [];
                return (
                  <li
                    key={master.id}
                    className="flex items-center justify-between gap-3 px-4 py-2.5"
                  >
                    <div className="min-w-0 space-y-0.5">
                      <div className="flex items-center gap-2 text-sm font-medium">
                        <FolderTree className="size-4 shrink-0 text-muted-foreground" />
                        <span className="truncate">
                          {resolveLabel(master.label ?? null, lang) ?? master.code}
                        </span>
                      </div>
                      {mapped.length > 0 ? (
                        <p className="pl-6 text-xs text-muted-foreground">
                          {t('channels.category_mapping.mapped_count', { count: mapped.length })}
                        </p>
                      ) : null}
                    </div>
                    <Button
                      variant={mapped.length > 0 ? 'outline' : 'default'}
                      size="sm"
                      onClick={() => openDialog(master)}
                      data-testid={`chc-map-${master.code}`}
                    >
                      <Link2 className="size-4" />
                      {mapped.length > 0
                        ? t('channels.category_mapping.edit_mapping')
                        : t('channels.category_mapping.map')}
                    </Button>
                  </li>
                );
              })
            )}
          </ul>
        </section>

        <section className="rounded-xl border bg-card">
          <header className="border-b px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {t('channels.category_mapping.channel_panel')}
          </header>
          {nodes.length === 0 ? (
            <p className="px-4 py-3 text-sm text-muted-foreground">
              {t('channels.category_mapping.no_nodes')}
            </p>
          ) : (
            <ChannelNodeTree nodes={nodes} lang={lang} countByNode={countByNode} t={t} />
          )}
        </section>
      </div>

      <Dialog open={dialogMaster !== null} onOpenChange={(open) => !open && setDialogMaster(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {t('channels.category_mapping.dialog_title', {
                category: dialogMaster
                  ? (resolveLabel(dialogMaster.label ?? null, lang) ?? dialogMaster.code)
                  : '',
              })}
            </DialogTitle>
            <DialogDescription>{t('channels.category_mapping.dialog_hint')}</DialogDescription>
          </DialogHeader>
          <MultiSelect
            options={nodeOptions}
            value={dialogSelection}
            onChange={setDialogSelection}
            placeholder={t('channels.category_mapping.dialog_placeholder')}
          />
          <DialogFooter>
            <Button variant="ghost" onClick={() => setDialogMaster(null)}>
              {t('app.cancel')}
            </Button>
            <Button
              onClick={() =>
                dialogMaster &&
                saveMapping.mutate({ masterId: dialogMaster.id, nodeIds: dialogSelection })
              }
              disabled={saveMapping.isPending}
            >
              {saveMapping.isPending ? <Loader2 className="size-4 animate-spin" /> : null}
              {t('app.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={clearOpen} onOpenChange={setClearOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('channels.category_mapping.clear_confirm_title')}</DialogTitle>
            <DialogDescription>
              {t('channels.category_mapping.clear_confirm_body')}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setClearOpen(false)}>
              {t('app.cancel')}
            </Button>
            <Button
              variant="destructive"
              onClick={() => clearAll.mutate()}
              disabled={clearAll.isPending}
            >
              {clearAll.isPending ? <Loader2 className="size-4 animate-spin" /> : null}
              {t('channels.category_mapping.clear_all')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function ChannelNodeTree({
  nodes,
  lang,
  countByNode,
  t,
}: {
  nodes: ChannelNode[];
  lang: string;
  countByNode: Map<string, number>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const childrenByParent = new Map<string | null, ChannelNode[]>();
  for (const node of nodes) {
    const key = node.parentId;
    const bucket = childrenByParent.get(key) ?? [];
    bucket.push(node);
    childrenByParent.set(key, bucket);
  }

  const render = (parentId: string | null, depth: number): React.ReactNode =>
    (childrenByParent.get(parentId) ?? []).map((node) => {
      const count = countByNode.get(node.id) ?? 0;
      return (
        <div key={node.id}>
          <div
            className="flex items-center justify-between gap-2 px-4 py-1.5 text-sm"
            style={{ paddingLeft: `${depth * 16 + 16}px` }}
          >
            <span className="truncate">{resolveLabel(node.label ?? null, lang) ?? node.code}</span>
            {count > 0 ? (
              <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                {t('channels.category_mapping.product_count', { count })}
              </span>
            ) : null}
          </div>
          {render(node.id, depth + 1)}
        </div>
      );
    });

  return (
    <div className="py-1" data-testid="chc-channel-tree">
      {render(null, 0)}
    </div>
  );
}

/**
 * Breadcrumb-style label for each node ("Root > RTV > TV"), walking parentId.
 */
function buildNodePathLabels(nodes: ChannelNode[], lang: string): Map<string, string> {
  const byId = new Map<string, ChannelNode>();
  for (const node of nodes) byId.set(node.id, node);

  const labelOf = (node: ChannelNode): string =>
    resolveLabel(node.label ?? null, lang) ?? node.code;

  const result = new Map<string, string>();
  for (const node of nodes) {
    const parts: string[] = [];
    let current: ChannelNode | undefined = node;
    const guard = new Set<string>();
    while (current && !guard.has(current.id)) {
      guard.add(current.id);
      parts.unshift(labelOf(current));
      current = current.parentId ? byId.get(current.parentId) : undefined;
    }
    result.set(node.id, parts.join(' › '));
  }
  return result;
}
