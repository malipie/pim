import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export interface ChannelFormValues {
  code: string;
  name: string;
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
  name: '',
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
    name: defaultValues?.name ?? '',
  });

  const errors: Partial<Record<keyof ChannelFormValues, string>> = {};
  if (mode === 'create') {
    if (values.code.trim() === '') {
      errors.code = t('channels.form.validation.required');
    } else if (!CODE_REGEX.test(values.code)) {
      errors.code = t('channels.form.validation.code_format');
    }
  }
  if (values.name.trim() === '') {
    errors.name = t('channels.form.validation.required');
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

          <div>
            <Label htmlFor="channel-name">{t('channels.form.fields.name')}</Label>
            <Input
              id="channel-name"
              value={values.name}
              onChange={(e) => setValues({ ...values, name: e.target.value })}
              aria-invalid={errors.name ? 'true' : 'false'}
              aria-describedby="channel-name-help"
              className="mt-1"
            />
            <p id="channel-name-help" className="mt-1 text-xs text-muted-foreground">
              {t('channels.form.fields.name_help')}
            </p>
            {errors.name ? (
              <p role="alert" className="mt-1 text-xs text-destructive">
                {errors.name}
              </p>
            ) : null}
          </div>
        </div>
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
