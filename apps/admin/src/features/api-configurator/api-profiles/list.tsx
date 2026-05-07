import { useList } from '@refinedev/core';
import { Eye, KeyRound, Plus } from 'lucide-react';
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

export interface ApiProfileRow {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  outputFormat: string;
  objectTypeIds?: string[];
  includedAttributes?: string[];
  filters?: Record<string, unknown>;
  webhookUrl?: string | null;
  webhookEvents?: string[];
  rateLimitPerHour: number;
  createdAt: string;
  updatedAt: string;
}

export function ApiProfilesListPage() {
  const { t } = useTranslation();
  const { result, query } = useList<ApiProfileRow>({
    resource: 'api_profiles',
    pagination: { mode: 'off' },
  });

  const profiles = result.data;
  const isLoading = query.isLoading;

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t('api_profiles.list_title')}</h1>
          <p className="text-sm text-muted-foreground">{t('api_profiles.list_subtitle')}</p>
        </div>
        <Button asChild>
          <Link to="/integrations/api-configurator/create">
            <Plus className="mr-1 size-4" />
            {t('api_profiles.actions.create')}
          </Link>
        </Button>
      </div>

      <div className="rounded-xl border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[180px]">{t('api_profiles.fields.code')}</TableHead>
              <TableHead>{t('api_profiles.fields.name')}</TableHead>
              <TableHead className="w-[140px]">{t('api_profiles.fields.output_format')}</TableHead>
              <TableHead className="w-[140px]">
                {t('api_profiles.fields.object_types_count')}
              </TableHead>
              <TableHead className="w-[140px]">{t('api_profiles.fields.rate_limit')}</TableHead>
              <TableHead className="w-[80px] text-right">
                <span className="sr-only">{t('api_profiles.fields.actions')}</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                  {t('app.loading')}
                </TableCell>
              </TableRow>
            ) : profiles.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                  <KeyRound className="mx-auto mb-2 size-8 opacity-50" />
                  {t('api_profiles.empty')}
                </TableCell>
              </TableRow>
            ) : (
              profiles.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="font-mono text-xs">{row.code}</TableCell>
                  <TableCell className="font-medium">{row.name}</TableCell>
                  <TableCell>
                    <span className="inline-flex items-center rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] uppercase">
                      {row.outputFormat}
                    </span>
                  </TableCell>
                  <TableCell className="text-sm text-muted-foreground">
                    {(row.objectTypeIds ?? []).length}
                  </TableCell>
                  <TableCell className="text-sm text-muted-foreground">
                    {row.rateLimitPerHour}/h
                  </TableCell>
                  <TableCell className="text-right">
                    <Button asChild variant="ghost" size="sm">
                      <Link to={`/integrations/api-configurator/${row.id}`}>
                        <Eye className="size-4" />
                        <span className="sr-only">{t('api_profiles.actions.view')}</span>
                      </Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
