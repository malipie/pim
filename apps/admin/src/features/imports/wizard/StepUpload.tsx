import { useList } from '@refinedev/core';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { FileDropzone } from '@/features/imports/components/FileDropzone';
import type { useImportWizard, WizardState } from '@/features/imports/hooks/useImportWizard';

const MAX_CSV_BYTES = 50 * 1024 * 1024;
const MAX_ZIP_BYTES = 500 * 1024 * 1024;

interface ObjectTypeOption {
  id: string;
  code: string;
  kind: string;
  label?: Record<string, string>;
}

interface ImportProfileOption {
  id: string;
  name: string;
  targetObjectType?: { id: string };
}

interface StepUploadProps {
  wizard: ReturnType<typeof useImportWizard>;
}

/**
 * Spec §5.2 — wizard's first screen. Picks the source file +
 * locale/encoding/delimiter, decides ZIP image source, and chooses
 * between a saved profile or a fresh one. Auto-detect fields stay
 * neutral defaults — the parser on the backend resolves them at
 * upload time (IMP-02 EncodingDetector / DelimiterDetector).
 */
export function StepUpload({ wizard }: StepUploadProps): React.ReactElement {
  const { t } = useTranslation();
  const { state, setField, next } = wizard;

  const { result: typesResult } = useList<ObjectTypeOption>({
    resource: 'object_types',
    pagination: { pageSize: 50 },
  });
  const { result: profilesResult } = useList<ImportProfileOption>({
    resource: 'import-profiles',
    pagination: { pageSize: 100 },
  });

  const objectTypes = typesResult.data ?? [];
  const profiles = profilesResult.data ?? [];

  const productType = objectTypes.find((option) => option.kind === 'product') ?? objectTypes[0];

  // Default to the built-in product ObjectType if the wizard hasn't
  // picked one yet. Imports MVP UI is locked to `kind=product`
  // (spec §3 decision).
  if (state.targetObjectTypeId === null && productType !== undefined) {
    setField('targetObjectTypeId', productType.id);
  }

  const canProceed = state.file !== null && state.targetObjectTypeId !== null;

  return (
    <div className="space-y-6 rounded-md border bg-card p-6">
      <section className="space-y-3">
        <h2 className="text-lg font-semibold">
          {t('imports.upload.drop_csv', { defaultValue: 'Plik produktów (CSV / Excel)' })}
        </h2>
        <FileDropzone
          kind="csv-xlsx"
          maxBytes={MAX_CSV_BYTES}
          selected={state.file}
          label={t('imports.upload.drop_csv', {
            defaultValue: 'Przeciągnij plik tutaj lub wybierz',
          })}
          hint={t('imports.upload.drop_csv_hint', {
            defaultValue: 'Akceptowane: .xlsx, .csv (max 50 MB)',
          })}
          onFile={(file) => setField('file', file)}
        />
      </section>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <Field label={t('imports.upload.locale', { defaultValue: 'Locale pliku' })}>
          <select
            value={state.locale ?? ''}
            onChange={(event) => setField('locale', event.target.value || null)}
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          >
            <option value="">auto</option>
            <option value="pl_PL">Polski (pl_PL)</option>
            <option value="en_US">English (en_US)</option>
            <option value="de_DE">Deutsch (de_DE)</option>
          </select>
        </Field>
        <Field label={t('imports.upload.encoding', { defaultValue: 'Kodowanie' })}>
          <select
            value={state.encoding}
            onChange={(event) => setField('encoding', event.target.value)}
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          >
            <option value="auto">auto</option>
            <option value="utf-8">UTF-8</option>
            <option value="utf-8-bom">UTF-8 + BOM</option>
            <option value="windows-1250">Windows-1250</option>
            <option value="iso-8859-2">ISO-8859-2</option>
          </select>
        </Field>
        <Field label={t('imports.upload.delimiter', { defaultValue: 'Separator' })}>
          <select
            value={state.delimiter}
            onChange={(event) => setField('delimiter', event.target.value)}
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          >
            <option value="auto">auto</option>
            <option value=";">; (semicolon)</option>
            <option value=",">, (comma)</option>
            <option value="tab">↹ (tab)</option>
            <option value="|">| (pipe)</option>
          </select>
        </Field>
      </section>

      <section className="space-y-3">
        <h3 className="text-sm font-medium">
          {t('imports.upload.image_source.label', { defaultValue: 'Źródło zdjęć' })}
        </h3>
        <div className="space-y-2">
          {(['http', 'zip', 'none'] as const).map((option) => (
            <label key={option} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="image-source"
                value={option}
                checked={state.imageSource === option}
                onChange={() => setField('imageSource', option)}
              />
              <span>
                {t(`imports.upload.image_source.${option}`, {
                  defaultValue: option,
                })}
              </span>
            </label>
          ))}
        </div>
        {state.imageSource === 'zip' && (
          <FileDropzone
            kind="zip"
            maxBytes={MAX_ZIP_BYTES}
            selected={state.zipFile}
            label={t('imports.upload.drop_zip', { defaultValue: 'Plik ZIP' })}
            hint={t('imports.upload.drop_zip_hint', { defaultValue: 'max 500 MB' })}
            onFile={(file) => setField('zipFile', file)}
          />
        )}
      </section>

      <section className="space-y-3">
        <h3 className="text-sm font-medium">
          {t('imports.upload.profile.label', { defaultValue: 'Profil importu' })}
        </h3>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="radio"
            name="profile-mode"
            checked={state.profileId === null}
            onChange={() => setField('profileId', null)}
          />
          <span>
            {t('imports.upload.profile.new', {
              defaultValue: 'Nowy profil (skonfiguruj poniżej)',
            })}
          </span>
        </label>
        {profiles.length > 0 && (
          <label className="flex items-center gap-2 text-sm">
            <input
              type="radio"
              name="profile-mode"
              checked={state.profileId !== null}
              onChange={() => setField('profileId', profiles[0].id)}
            />
            <span>
              {t('imports.upload.profile.use_saved', { defaultValue: 'Użyj zapisanego' })}
            </span>
            {state.profileId !== null && (
              <select
                value={state.profileId}
                onChange={(event) => setField('profileId', event.target.value)}
                className="ml-2 rounded-md border bg-background px-2 py-1 text-xs"
              >
                {profiles.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.name}
                  </option>
                ))}
              </select>
            )}
          </label>
        )}
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={state.saveAsProfileName !== null}
            onChange={(event) =>
              setField(
                'saveAsProfileName',
                event.target.checked ? (state.saveAsProfileName ?? '') : null,
              )
            }
          />
          <span>{t('imports.upload.profile.save_as', { defaultValue: 'Zapisz jako profil' })}</span>
          {state.saveAsProfileName !== null && (
            <input
              type="text"
              value={state.saveAsProfileName}
              onChange={(event) => setField('saveAsProfileName', event.target.value)}
              placeholder="Festo Q2 2026"
              className="ml-2 rounded-md border bg-background px-2 py-1 text-xs"
            />
          )}
        </label>
      </section>

      <div className="flex justify-end gap-2">
        <Button variant="ghost" disabled>
          {t('imports.wizard.cancel', { defaultValue: 'Anuluj' })}
        </Button>
        <Button onClick={() => next()} disabled={!canProceed}>
          {t('imports.wizard.next', { defaultValue: 'Dalej →' })}
        </Button>
      </div>
    </div>
  );
}

function Field({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}): React.ReactElement {
  return (
    <div className="flex flex-col gap-1 text-sm">
      <span className="font-medium">{label}</span>
      {children}
    </div>
  );
}

// Re-export to satisfy WizardState typing in case downstream files import it.
export type { WizardState };
