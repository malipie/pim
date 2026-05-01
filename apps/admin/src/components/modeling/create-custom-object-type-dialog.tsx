import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { jsonFetch } from '@/lib/http';

interface CreatedObjectType {
  id: string;
  code: string;
  kind: string;
  label: Record<string, string>;
  builtIn: boolean;
}

/**
 * UI-02 follow-up — Create-custom-ObjectType form over the new
 * `POST /api/object_types` endpoint. The DB schema and service
 * layer have supported `kind=custom` from day 1 (R-29 mitigation);
 * this dialog finally exposes the path to operators today instead
 * of waiting for the Faza 2 schema-add agent.
 */
export function CreateCustomObjectTypeDialog({
  onClose,
  onCreated,
}: {
  onClose: () => void;
  onCreated: (created: CreatedObjectType) => void;
}) {
  const { t } = useTranslation();
  const [code, setCode] = useState('');
  const [labelPl, setLabelPl] = useState('');
  const [labelEn, setLabelEn] = useState('');
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setError(null);
    const trimmedCode = code.trim();
    const label: Record<string, string> = {};
    if (labelPl.trim() !== '') label.pl = labelPl.trim();
    if (labelEn.trim() !== '') label.en = labelEn.trim();
    if (trimmedCode === '' || Object.keys(label).length === 0) {
      setError(
        t('object_types.create_custom.validation', {
          defaultValue: 'Code and at least one label entry are required.',
        }),
      );
      return;
    }
    setIsPending(true);
    try {
      const response = await jsonFetch<CreatedObjectType>('/api/object_types', {
        method: 'POST',
        body: { code: trimmedCode, label },
      });
      onCreated(response);
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'unknown');
    } finally {
      setIsPending(false);
    }
  };

  return (
    <Sheet
      open
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <SheetContent side="right" className="w-[420px] p-6">
        <SheetTitle>
          {t('object_types.create_custom.title', { defaultValue: 'Create custom ObjectType' })}
        </SheetTitle>
        <p className="mt-2 text-sm text-muted-foreground">
          {t('object_types.create_custom.subtitle', {
            defaultValue:
              'Custom kinds (e.g. service, kit, bundle) ship with no built-in business logic — only the schema. Attach AttributeGroups and start adding rows from the modeling UI.',
          })}
        </p>
        <form onSubmit={(e) => void handleSubmit(e)} className="mt-4 space-y-4">
          <div className="space-y-2">
            <label htmlFor="custom-ot-code" className="text-sm font-medium">
              {t('object_types.create_custom.code_label', { defaultValue: 'Code' })}
            </label>
            <Input
              id="custom-ot-code"
              value={code}
              onChange={(e) => setCode(e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))}
              placeholder="service"
              autoFocus
            />
            <p className="text-xs text-muted-foreground">
              {t('object_types.create_custom.code_hint', {
                defaultValue: 'Lowercase letters, digits, underscore. Must be unique per tenant.',
              })}
            </p>
          </div>

          <div className="space-y-2">
            <label htmlFor="custom-ot-label-pl" className="text-sm font-medium">
              {t('object_types.create_custom.label_pl', { defaultValue: 'Label (PL)' })}
            </label>
            <Input
              id="custom-ot-label-pl"
              value={labelPl}
              onChange={(e) => setLabelPl(e.target.value)}
              placeholder="Usługa"
            />
          </div>

          <div className="space-y-2">
            <label htmlFor="custom-ot-label-en" className="text-sm font-medium">
              {t('object_types.create_custom.label_en', { defaultValue: 'Label (EN)' })}
            </label>
            <Input
              id="custom-ot-label-en"
              value={labelEn}
              onChange={(e) => setLabelEn(e.target.value)}
              placeholder="Service"
            />
          </div>

          {error !== null ? <p className="text-sm text-rose-600">{error}</p> : null}

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" type="button" onClick={onClose} disabled={isPending}>
              {t('app.cancel', { defaultValue: 'Cancel' })}
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending
                ? t('object_types.create_custom.submitting', { defaultValue: 'Creating…' })
                : t('object_types.create_custom.submit', { defaultValue: 'Create' })}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
