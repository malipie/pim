import { useCreate, useDelete, useList } from '@refinedev/core';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import {
  type EndpointRole,
  type HttpMethod,
  MethodPill,
  type PaginationKind,
  PaginationPill,
  RolePill,
  Segmented,
} from '../../../components/primitives';

export interface RemoteEndpointRow {
  id: string;
  connectionId: string;
  role: EndpointRole;
  httpMethod: HttpMethod;
  pathTemplate: string;
  pagination: { strategy?: PaginationKind } & Record<string, unknown>;
  recordSelector: string | null;
}

const ROLES: EndpointRole[] = ['read_list', 'read_one', 'write_create', 'write_update'];
const METHODS: HttpMethod[] = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
const STRATEGIES: PaginationKind[] = ['none', 'offset', 'page', 'cursor', 'link_header'];

/**
 * APIC-P2-06 — wizard step 3: the endpoint (operation descriptor) builder. Lists
 * the connection's RemoteEndpoints and adds/removes them against the P2-05 CRUD
 * API. The schema-discovery step (4) samples one of these read endpoints.
 */
export function StepEndpoints({ connectionId }: { connectionId: string | null }) {
  const { t } = useTranslation();
  const { result, query } = useList<RemoteEndpointRow>({
    resource: 'remote_endpoints',
    filters:
      connectionId === null ? [] : [{ field: 'connection', operator: 'eq', value: connectionId }],
    queryOptions: { enabled: connectionId !== null },
    pagination: { mode: 'off' },
  });
  const { mutate: create, mutation: createMutation } = useCreate();
  const { mutate: remove } = useDelete();

  const [role, setRole] = useState<EndpointRole>('read_list');
  const [method, setMethod] = useState<HttpMethod>('GET');
  const [path, setPath] = useState('/');
  const [strategy, setStrategy] = useState<PaginationKind>('none');
  const [selector, setSelector] = useState('$');

  const endpoints = result.data;

  function add(): void {
    if (connectionId === null || path.trim() === '') {
      return;
    }
    create(
      {
        resource: 'remote_endpoints',
        values: {
          connection: connectionId,
          role,
          httpMethod: method,
          pathTemplate: path.trim(),
          pagination: { strategy },
          recordSelector: selector.trim() === '' ? null : selector.trim(),
        },
        successNotification: false,
      },
      { onSuccess: () => query.refetch() },
    );
  }

  return (
    <div className="soft-shadow space-y-4 rounded-2xl border border-zinc-200 bg-white p-6">
      <table className="w-full text-[12.5px]">
        <thead>
          <tr className="text-[10px] uppercase tracking-wider text-zinc-500">
            <th className="pb-1 text-left font-medium">{t('api_configurator.wizard.ep.role')}</th>
            <th className="pb-1 text-left font-medium">{t('api_configurator.wizard.ep.method')}</th>
            <th className="pb-1 text-left font-medium">{t('api_configurator.wizard.ep.path')}</th>
            <th className="pb-1 text-left font-medium">
              {t('api_configurator.wizard.ep.pagination')}
            </th>
            <th className="pb-1 text-left font-medium">
              {t('api_configurator.wizard.ep.selector')}
            </th>
            <th className="pb-1" />
          </tr>
        </thead>
        <tbody>
          {endpoints.map((endpoint) => (
            <tr key={endpoint.id} className="border-t border-zinc-100">
              <td className="py-2">
                <RolePill value={endpoint.role} />
              </td>
              <td className="py-2">
                <MethodPill method={endpoint.httpMethod} />
              </td>
              <td className="py-2 font-mono text-zinc-800">{endpoint.pathTemplate}</td>
              <td className="py-2">
                <PaginationPill kind={endpoint.pagination?.strategy ?? 'none'} />
              </td>
              <td className="py-2 font-mono text-zinc-500">{endpoint.recordSelector ?? '—'}</td>
              <td className="py-2 text-right">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  onClick={() =>
                    remove(
                      { resource: 'remote_endpoints', id: endpoint.id },
                      { onSuccess: () => query.refetch() },
                    )
                  }
                  aria-label={t('api_configurator.wizard.ep.remove', {
                    path: endpoint.pathTemplate,
                  })}
                  className="text-zinc-400 hover:text-rose-600"
                >
                  <Trash2 className="size-4" aria-hidden="true" />
                </Button>
              </td>
            </tr>
          ))}
          {endpoints.length === 0 && !query.isLoading ? (
            <tr>
              <td colSpan={6} className="py-4 text-center text-zinc-500">
                {t('api_configurator.wizard.ep.empty')}
              </td>
            </tr>
          ) : null}
        </tbody>
      </table>

      <div className="space-y-3 rounded-xl border border-dashed border-zinc-200 p-4">
        <div className="flex flex-wrap items-center gap-2">
          <Segmented
            ariaLabel={t('api_configurator.wizard.ep.role')}
            size="sm"
            value={role}
            onChange={setRole}
            options={ROLES.map((r) => ({ value: r, label: r }))}
          />
          <Segmented
            ariaLabel={t('api_configurator.wizard.ep.method')}
            size="sm"
            value={method}
            onChange={setMethod}
            options={METHODS.map((m) => ({ value: m, label: m }))}
          />
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Input
            value={path}
            onChange={(e) => setPath(e.target.value)}
            placeholder="/products"
            aria-label={t('api_configurator.wizard.ep.path')}
            className="h-9 max-w-xs font-mono"
          />
          <Segmented
            ariaLabel={t('api_configurator.wizard.ep.pagination')}
            size="sm"
            value={strategy}
            onChange={setStrategy}
            options={STRATEGIES.map((s) => ({ value: s, label: s }))}
          />
          <Input
            value={selector}
            onChange={(e) => setSelector(e.target.value)}
            placeholder="$.results"
            aria-label={t('api_configurator.wizard.ep.selector')}
            className="h-9 w-32 font-mono"
          />
          <Button
            type="button"
            onClick={add}
            disabled={connectionId === null || createMutation.isPending}
          >
            <Plus className="mr-1 size-4" aria-hidden="true" />
            {t('api_configurator.wizard.ep.add')}
          </Button>
        </div>
      </div>
    </div>
  );
}
