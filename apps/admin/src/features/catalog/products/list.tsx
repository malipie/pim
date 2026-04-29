import { useList } from '@refinedev/core';
import { Pencil, Plus } from 'lucide-react';
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

interface Product {
  id: string;
  sku: string;
  name: string;
  description: string | null;
  brand: string | null;
  createdAt: string;
}

export function ProductListPage() {
  const { t, i18n } = useTranslation();
  const { result, query } = useList<Product>({ resource: 'products' });
  const products = result.data;
  const isLoading = query.isLoading;

  return (
    <div className="space-y-6">
      <div className="flex items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t('products.list_title')}</h1>
          <p className="text-sm text-muted-foreground">{t('products.list_subtitle')}</p>
        </div>
        <Button asChild>
          <Link to="/products/new">
            <Plus className="size-4" />
            {t('products.create')}
          </Link>
        </Button>
      </div>

      <div className="rounded-xl border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[180px]">{t('products.fields.sku')}</TableHead>
              <TableHead>{t('products.fields.name')}</TableHead>
              <TableHead className="w-[160px]">{t('products.fields.brand')}</TableHead>
              <TableHead className="w-[180px]">{t('products.fields.created_at')}</TableHead>
              <TableHead className="w-[80px] text-right">
                <span className="sr-only">{t('products.fields.actions')}</span>
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
            ) : products.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="py-10 text-center text-muted-foreground">
                  {t('products.empty')}
                </TableCell>
              </TableRow>
            ) : (
              products.map((product) => (
                <TableRow key={product.id}>
                  <TableCell className="font-mono text-xs">{product.sku}</TableCell>
                  <TableCell className="font-medium">{product.name}</TableCell>
                  <TableCell>{product.brand ?? '—'}</TableCell>
                  <TableCell className="text-muted-foreground">
                    {formatDateTime(product.createdAt, i18n.language)}
                  </TableCell>
                  <TableCell className="text-right">
                    <Button asChild variant="ghost" size="sm">
                      <Link to={`/products/${product.id}/edit`}>
                        <Pencil className="size-4" />
                        <span className="sr-only">{t('products.actions.edit')}</span>
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

function formatDateTime(value: string, locale: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(date);
}
