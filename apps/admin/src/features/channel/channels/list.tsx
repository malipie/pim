import { useList } from '@refinedev/core';
import { Eye, Radio } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { resolveLabel } from '@/features/catalog/attributes/list';

interface ChannelRow {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  locales?: string[];
  currencies?: string[];
  categoryTreeRootId?: string | null;
}

export function ChannelsListPage() {
  const { t, i18n } = useTranslation();
  const { result, query } = useList<ChannelRow>({
    resource: 'channels',
    pagination: { mode: 'off' },
  });

  const channels = result.data;
  const isLoading = query.isLoading;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('channels.list_title')}</h1>
        <p className="text-sm text-muted-foreground">{t('channels.list_subtitle')}</p>
      </div>

      <div className="rounded-xl border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[180px]">{t('channels.fields.code')}</TableHead>
              <TableHead>{t('channels.fields.label')}</TableHead>
              <TableHead className="w-[200px]">{t('channels.fields.locales')}</TableHead>
              <TableHead className="w-[200px]">{t('channels.fields.currencies')}</TableHead>
              <TableHead className="w-[80px] text-right">
                <span className="sr-only">{t('channels.fields.actions')}</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={5} className="py-10 text-center text-muted-foreground">
                  {t('app.loading')}
                </TableCell>
              </TableRow>
            ) : channels.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="py-10 text-center text-muted-foreground">
                  {t('channels.empty')}
                </TableCell>
              </TableRow>
            ) : (
              channels.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="font-mono text-xs">
                    <Radio className="mr-1 inline size-3.5 text-muted-foreground" />
                    {row.code}
                  </TableCell>
                  <TableCell className="font-medium">
                    {resolveLabel(row.label, i18n.language)}
                  </TableCell>
                  <TableCell className="space-x-1 text-xs">
                    {(row.locales ?? []).map((loc) => (
                      <Tag key={loc}>{loc}</Tag>
                    ))}
                  </TableCell>
                  <TableCell className="space-x-1 text-xs">
                    {(row.currencies ?? []).map((cur) => (
                      <Tag key={cur}>{cur}</Tag>
                    ))}
                  </TableCell>
                  <TableCell className="text-right">
                    <Button asChild variant="ghost" size="sm">
                      <Link to={`/channels/${row.id}`}>
                        <Eye className="size-4" />
                        <span className="sr-only">{t('channels.actions.view')}</span>
                      </Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <p className="text-xs text-muted-foreground">{t('channels.write_deferred_note')}</p>
    </div>
  );
}

function Tag({ children }: { children: React.ReactNode }) {
  return (
    <span className="inline-flex items-center rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wide">
      {children}
    </span>
  );
}
