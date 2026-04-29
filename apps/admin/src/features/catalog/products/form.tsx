import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowLeft } from 'lucide-react';
import { type FieldErrors, type SubmitHandler, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export const productCreateSchema = z.object({
  sku: z.string().min(1).max(64),
  name: z.string().min(1).max(255),
  description: z.string().max(4000).optional().or(z.literal('')),
  brand: z.string().max(128).optional().or(z.literal('')),
});

export const productPatchSchema = productCreateSchema.omit({ sku: true });

export type ProductCreateValues = z.infer<typeof productCreateSchema>;
export type ProductPatchValues = z.infer<typeof productPatchSchema>;

export type ProductFormValues = ProductCreateValues;

export interface ProductFormProps {
  mode: 'create' | 'edit';
  defaultValues?: Partial<ProductFormValues>;
  onSubmit: (values: ProductFormValues) => Promise<void> | void;
  isSubmitting: boolean;
  apiError?: string | null;
}

export function ProductForm({
  mode,
  defaultValues,
  onSubmit,
  isSubmitting,
  apiError,
}: ProductFormProps) {
  const { t } = useTranslation();
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ProductFormValues>({
    resolver: zodResolver(productCreateSchema),
    defaultValues: {
      sku: defaultValues?.sku ?? '',
      name: defaultValues?.name ?? '',
      description: defaultValues?.description ?? '',
      brand: defaultValues?.brand ?? '',
    },
  });

  const submit: SubmitHandler<ProductFormValues> = async (values) => {
    await onSubmit(values);
  };

  return (
    <div className="space-y-6 max-w-3xl">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            {mode === 'create' ? t('products.create_title') : t('products.edit_title')}
          </h1>
        </div>
        <Button asChild variant="ghost" size="sm">
          <Link to="/products">
            <ArrowLeft className="size-4" />
            {t('products.back')}
          </Link>
        </Button>
      </div>

      <Card>
        <CardContent className="pt-6">
          <form onSubmit={handleSubmit(submit)} className="space-y-4" noValidate>
            <FieldRow
              id="sku"
              label={t('products.fields.sku')}
              error={resolveErrorKey(errors, 'sku', mode)}
            >
              <Input
                id="sku"
                disabled={mode === 'edit'}
                aria-invalid={errors.sku ? 'true' : 'false'}
                {...register('sku')}
              />
            </FieldRow>
            <FieldRow
              id="name"
              label={t('products.fields.name')}
              error={resolveErrorKey(errors, 'name', mode)}
            >
              <Input
                id="name"
                aria-invalid={errors.name ? 'true' : 'false'}
                {...register('name')}
              />
            </FieldRow>
            <FieldRow id="brand" label={t('products.fields.brand')}>
              <Input id="brand" {...register('brand')} />
            </FieldRow>
            <FieldRow id="description" label={t('products.fields.description')}>
              <Textarea id="description" rows={4} {...register('description')} />
            </FieldRow>

            {apiError ? (
              <p className="text-sm text-destructive" role="alert">
                {apiError}
              </p>
            ) : null}

            <div className="flex justify-end">
              <Button type="submit" disabled={isSubmitting}>
                {isSubmitting ? t('products.saving') : t('products.save')}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

interface FieldRowProps {
  id: string;
  label: string;
  error?: string;
  children: React.ReactNode;
}

function FieldRow({ id, label, error, children }: FieldRowProps) {
  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      {children}
      {error ? (
        <p className="text-sm text-destructive" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}

function resolveErrorKey(
  errors: FieldErrors<ProductFormValues>,
  field: 'sku' | 'name',
  mode: 'create' | 'edit',
): string | undefined {
  // Skip the sku error when editing — the field is disabled and SKU is
  // immutable on PATCH at the API layer (ticket #3).
  if (field === 'sku' && mode === 'edit') return undefined;
  const issue = errors[field];
  if (!issue) return undefined;
  // The Refine + react-hook-form pipeline carries the message we set in i18n
  // when present; otherwise fall back to a stable translation key.
  return issue.message;
}
