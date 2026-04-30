import { useList } from '@refinedev/core';
import { useTranslation } from 'react-i18next';

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { resolveLabel } from '@/features/catalog/attributes/list';

interface AttributeGroupRow {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  position?: number;
  attributesCount?: number;
}

export function AttributeGroupsListPage() {
  const { t, i18n } = useTranslation();
  const { result, query } = useList<AttributeGroupRow>({
    resource: 'attribute_groups',
    pagination: { mode: 'off' },
  });

  const groups = result.data;
  const isLoading = query.isLoading;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('attribute_groups.list_title')}
        </h1>
        <p className="text-sm text-muted-foreground">{t('attribute_groups.list_subtitle')}</p>
      </div>

      <div className="rounded-xl border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[200px]">{t('attribute_groups.fields.code')}</TableHead>
              <TableHead>{t('attribute_groups.fields.label')}</TableHead>
              <TableHead className="w-[100px] text-right">
                {t('attribute_groups.fields.position')}
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={3} className="py-10 text-center text-muted-foreground">
                  {t('app.loading')}
                </TableCell>
              </TableRow>
            ) : groups.length === 0 ? (
              <TableRow>
                <TableCell colSpan={3} className="py-10 text-center text-muted-foreground">
                  {t('attribute_groups.empty')}
                </TableCell>
              </TableRow>
            ) : (
              groups
                .slice()
                .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                .map((group) => (
                  <TableRow key={group.id}>
                    <TableCell className="font-mono text-xs">{group.code}</TableCell>
                    <TableCell className="font-medium">
                      {resolveLabel(group.label, i18n.language)}
                    </TableCell>
                    <TableCell className="text-right tabular-nums text-muted-foreground">
                      {group.position ?? '—'}
                    </TableCell>
                  </TableRow>
                ))
            )}
          </TableBody>
        </Table>
      </div>

      <p className="text-xs text-muted-foreground">{t('attribute_groups.write_deferred_note')}</p>
    </div>
  );
}
