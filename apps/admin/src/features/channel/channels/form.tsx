import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { CategoryRootCombobox } from './category-root-combobox';
import { LocalePicker } from './locale-picker';

export interface ChannelFormValues {
  code: string;
  label: { pl: string; en: string };
  locales: string[];
  categoryTreeRootId: string | null;
}

interface ChannelFormProps {
  mode: 'create' | 'edit';
  defaultValues?: Partial<ChannelFormValues>;
  isSubmitting?: boolean;
  onSubmit: (values: ChannelFormValues) => void;
  onCancel: () => void;
}

const EMPTY: ChannelFormValues = {
  code: '',
  label: { pl: '', en: '' },
  locales: [],
  categoryTreeRootId: null,
};

const CODE_REGEX = /^[a-z0-9_]+$/;

export function ChannelForm({
  mode,
  defaultValues,
  isSubmitting = false,
  onSubmit,
  onCancel,
}: ChannelFormProps) {
  const { t } = useTranslation();
  const [values, setValues] = useState<ChannelFormValues>({
    ...EMPTY,
    ...defaultValues,
    label: {
      pl: defaultValues?.label?.pl ?? '',
      en: defaultValues?.label?.en ?? '',
    },
    locales: defaultValues?.locales ?? [],
    categoryTreeRootId: defaultValues?.categoryTreeRootId ?? null,
  });

  const errors: Partial<Record<keyof ChannelFormValues | 'label_pl' | 'label_en', string>> = {};
  if (mode === 'create') {
    if (values.code.trim() === '') {
      errors.code = t('channels.form.validation.required');
    } else if (!CODE_REGEX.test(values.code)) {
      errors.code = t('channels.form.validation.code_format');
    }
  }
  if (values.label.pl.trim() === '') {
    errors.label_pl = t('channels.form.validation.required');
  }
  if (values.label.en.trim() === '') {
    errors.label_en = t('channels.form.validation.required');
  }
  if (values.locales.length === 0) {
    errors.locales = t('channels.form.validation.locales_min');
  }

  const isValid = Object.keys(errors).length === 0;

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    if (!isValid) return;
    onSubmit(values);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="rounded-xl border bg-card p-6">
        <div className="space-y-4">
          {mode === 'create' ? (
            <div>
              <Label htmlFor="channel-code" id="channel-code-label">
                {t('channels.form.fields.code')}
              </Label>
              <Input
                id="channel-code"
                value={values.code}
                onChange={(e) => setValues({ ...values, code: e.target.value.toLowerCase() })}
                aria-invalid={errors.code ? 'true' : 'false'}
                aria-describedby="channel-code-help channel-code-error"
                className="mt-1 font-mono"
                autoFocus
              />
              <p id="channel-code-help" className="mt-1 text-xs text-muted-foreground">
                {t('channels.form.fields.code_help')}
              </p>
              {errors.code ? (
                <p id="channel-code-error" role="alert" className="mt-1 text-xs text-destructive">
                  {errors.code}
                </p>
              ) : null}
            </div>
          ) : null}

          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <Label htmlFor="channel-label-pl">{t('channels.form.fields.label_pl')}</Label>
              <Input
                id="channel-label-pl"
                value={values.label.pl}
                onChange={(e) =>
                  setValues({ ...values, label: { ...values.label, pl: e.target.value } })
                }
                aria-invalid={errors.label_pl ? 'true' : 'false'}
                className="mt-1"
              />
              {errors.label_pl ? (
                <p role="alert" className="mt-1 text-xs text-destructive">
                  {errors.label_pl}
                </p>
              ) : null}
            </div>
            <div>
              <Label htmlFor="channel-label-en">{t('channels.form.fields.label_en')}</Label>
              <Input
                id="channel-label-en"
                value={values.label.en}
                onChange={(e) =>
                  setValues({ ...values, label: { ...values.label, en: e.target.value } })
                }
                aria-invalid={errors.label_en ? 'true' : 'false'}
                className="mt-1"
              />
              {errors.label_en ? (
                <p role="alert" className="mt-1 text-xs text-destructive">
                  {errors.label_en}
                </p>
              ) : null}
            </div>
          </div>
        </div>
      </div>

      <div className="rounded-xl border bg-card p-6">
        <Label id="channel-locales-label" className="mb-3 block">
          {t('channels.form.fields.locales')}
        </Label>
        <LocalePicker
          value={values.locales}
          onChange={(locales) => setValues({ ...values, locales })}
          ariaLabelledBy="channel-locales-label"
        />
        {errors.locales ? (
          <p role="alert" className="mt-2 text-xs text-destructive">
            {errors.locales}
          </p>
        ) : null}
      </div>

      <div className="rounded-xl border bg-card p-6">
        <Label id="channel-category-root-label" className="mb-3 block">
          {t('channels.form.fields.category_root')}
        </Label>
        <CategoryRootCombobox
          value={values.categoryTreeRootId}
          onChange={(id) => setValues({ ...values, categoryTreeRootId: id })}
          ariaLabelledBy="channel-category-root-label"
        />
      </div>

      <div className="flex items-center justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t('channels.form.cancel')}
        </Button>
        <Button type="submit" disabled={!isValid || isSubmitting}>
          {isSubmitting
            ? mode === 'create'
              ? t('channels.create.submitting')
              : t('channels.edit.submitting')
            : mode === 'create'
              ? t('channels.create.submit')
              : t('channels.edit.submit')}
        </Button>
      </div>
    </form>
  );
}
