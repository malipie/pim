import { useList } from '@refinedev/core';
import { Layers } from 'lucide-react';
import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { MockBadge } from '@/components/ui/mock-badge';
import { toast } from '@/components/ui/toast';
import { FileDropzone } from '@/features/imports/components/FileDropzone';
import type { useImportWizard } from '@/features/imports/hooks/useImportWizard';
import { HealthDot, SourceIcon } from '@/features/imports/primitives';
import type { ImportSourceRow } from '@/features/imports/sources/types';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

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
  code?: string;
  targetObjectType?: { id: string };
}

interface TestResponse {
  health: string;
  note: string | null;
  latency_ms: number;
}

interface StepSourceProps {
  wizard: ReturnType<typeof useImportWizard>;
}

/**
 * NUI-10 (#1429) — Step 1 „Źródło" (design `Import-nowy.html` StepSource):
 * upload zone + saved FTP/SFTP sources (WIRE: list + test-connection;
 * MOCK: ad-hoc run from a source — only schedule-attached run-now exists)
 * + the suggested-profiles rail (WIRE: saved profiles list; click selects).
 * File settings (locale/encoding/delimiter/images/save-as-profile) carry
 * over from the 4-step wizard unchanged — payload contract is identical.
 */
export function StepSource({ wizard }: StepSourceProps): React.ReactElement {
  const { t } = useTranslation();
  const { state, setField, next } = wizard;
  const [testingId, setTestingId] = React.useState<string | null>(null);

  const { result: typesResult } = useList<ObjectTypeOption>({
    resource: 'object_types',
    pagination: { pageSize: 50 },
  });
  const { result: profilesResult } = useList<ImportProfileOption>({
    resource: 'import-profiles',
    pagination: { pageSize: 100 },
  });
  const { result: sourcesResult } = useList<ImportSourceRow>({
    resource: 'import-sources',
    pagination: { pageSize: 50 },
  });

  const objectTypes = typesResult.data ?? [];
  const profiles = profilesResult.data ?? [];
  const sources = sourcesResult.data ?? [];

  const productType = objectTypes.find((option) => option.kind === 'product') ?? objectTypes[0];
  if (state.targetObjectTypeId === null && productType !== undefined) {
    setField('targetObjectTypeId', productType.id);
  }

  const canProceed = state.file !== null && state.targetObjectTypeId !== null;

  const testConnection = async (source: ImportSourceRow): Promise<void> => {
    setTestingId(source.id);
    try {
      const response = await jsonFetch<TestResponse>(
        `/api/import-sources/${source.id}/test-connection`,
        { method: 'POST', accept: 'application/json' },
      );
      toast.success(
        t('imports.source.test_result', {
          defaultValue: '{{name}}: {{health}} ({{ms}} ms)',
          name: source.name,
          health: response.health,
          ms: response.latency_ms,
        }),
      );
    } catch {
      toast.error(
        t('imports.source.test_failed', { defaultValue: 'Test połączenia nie powiódł się' }),
      );
    } finally {
      setTestingId(null);
    }
  };

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.4fr_1fr]">
        {/* Left — upload + saved sources */}
        <div className="space-y-5 rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm">
          <section className="space-y-3">
            <h2 className="text-[13.5px] font-semibold">
              {t('imports.source.upload_title', { defaultValue: 'Wgraj plik z dysku' })}
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
              onFile={(file) => {
                setField('file', file);
                // New file invalidates the previous parse snapshot + staged upload.
                setField('parsed', null);
                setField('stagedFileId', null);
                setField('suggestions', []);
              }}
            />
          </section>

          <section className="space-y-2">
            <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
              {t('imports.source.saved_sources', {
                defaultValue: 'Lub wybierz zapisane źródło (FTP/SFTP)',
              })}
            </div>
            {sources.length === 0 ? (
              <p className="text-[12px] text-zinc-500">
                {t('imports.source.no_sources', {
                  defaultValue: 'Brak zapisanych źródeł — skonfiguruj je w zakładce Źródła.',
                })}
              </p>
            ) : (
              <div className="space-y-2">
                {sources.map((source) => (
                  <div
                    key={source.id}
                    className="flex w-full items-center gap-3 rounded-xl border border-zinc-200 px-3 py-2.5 text-left"
                  >
                    <SourceIcon type={source.type} />
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-[13px] font-medium">{source.name}</div>
                      <div className="truncate font-mono text-[11px] text-zinc-500">
                        {[source.path, source.filePattern].filter(Boolean).join(' · ') ||
                          source.code}
                      </div>
                    </div>
                    <HealthDot health={source.health} />
                    <Button
                      variant="outline"
                      size="sm"
                      className="h-7 rounded-lg text-[11.5px]"
                      disabled={testingId === source.id}
                      onClick={() => void testConnection(source)}
                    >
                      {testingId === source.id
                        ? t('app.loading', { defaultValue: 'Ładowanie…' })
                        : t('imports.source.test', { defaultValue: 'Testuj' })}
                    </Button>
                    <span className="relative">
                      <Button
                        variant="ghost"
                        size="sm"
                        disabled
                        className="h-7 cursor-not-allowed rounded-lg text-[11.5px] opacity-50"
                        title={t('imports.source.run_mock', {
                          defaultValue:
                            'MOCK — uruchomienie ad-hoc z zapisanego źródła wymaga backendu; użyj Harmonogramu (run-now)',
                        })}
                      >
                        {t('imports.source.run', { defaultValue: 'Uruchom' })}
                      </Button>
                      <MockBadge variant="corner" />
                    </span>
                  </div>
                ))}
              </div>
            )}
          </section>
        </div>

        {/* Right — suggested profiles + file settings */}
        <div className="space-y-4">
          <div className="rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm">
            <div className="text-[13.5px] font-semibold">
              {t('imports.source.profiles_title', { defaultValue: 'Sugerowane profile' })}
            </div>
            <div className="mt-0.5 text-[12px] text-zinc-500">
              {t('imports.source.profiles_subtitle', { defaultValue: 'Zapisane profile mapowań' })}
            </div>
            <div className="mt-4 space-y-2">
              {profiles.slice(0, 4).map((profile) => {
                const active = state.profileId === profile.id;
                return (
                  <button
                    key={profile.id}
                    type="button"
                    onClick={() => setField('profileId', active ? null : profile.id)}
                    className={cn(
                      'flex w-full items-start gap-3 rounded-xl border px-3 py-2.5 text-left transition',
                      active
                        ? 'border-zinc-900 bg-zinc-50'
                        : 'border-zinc-200 hover:border-zinc-900 hover:bg-zinc-50',
                    )}
                  >
                    <div className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-zinc-100 text-zinc-600">
                      <Layers className="size-4" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-[12.5px] font-medium">{profile.name}</div>
                      {profile.code ? (
                        <div className="truncate font-mono text-[10.5px] text-zinc-500">
                          {profile.code}
                        </div>
                      ) : null}
                    </div>
                    {active ? <span className="text-[11px] font-semibold">✓</span> : null}
                  </button>
                );
              })}
              <button
                type="button"
                onClick={() => setField('profileId', null)}
                className={cn(
                  'w-full rounded-xl border border-dashed px-3 py-2.5 text-[12.5px] transition',
                  state.profileId === null
                    ? 'border-zinc-900 text-zinc-900'
                    : 'border-zinc-300 text-zinc-500 hover:border-zinc-900 hover:text-zinc-900',
                )}
              >
                {t('imports.source.new_profile', {
                  defaultValue: 'Nowy profil (skonfiguruj w kreatorze)',
                })}
              </button>
              <label className="flex items-center gap-2 pt-1 text-[12.5px]">
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
                <span>
                  {t('imports.upload.profile.save_as', { defaultValue: 'Zapisz jako profil' })}
                </span>
                {state.saveAsProfileName !== null && (
                  <input
                    type="text"
                    value={state.saveAsProfileName}
                    onChange={(event) => setField('saveAsProfileName', event.target.value)}
                    placeholder="Festo Q2 2026"
                    className="ml-1 rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs"
                  />
                )}
              </label>
            </div>
          </div>

          <div className="rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm">
            <div className="text-[13.5px] font-semibold">
              {t('imports.source.settings_title', { defaultValue: 'Ustawienia pliku' })}
            </div>
            <div className="mt-3 space-y-3 text-sm">
              <SelectField
                label={t('imports.upload.locale', { defaultValue: 'Locale pliku' })}
                value={state.locale ?? ''}
                onChange={(value) => setField('locale', value || null)}
                options={[
                  { value: '', label: 'auto' },
                  { value: 'pl_PL', label: 'Polski (pl_PL)' },
                  { value: 'en_US', label: 'English (en_US)' },
                  { value: 'de_DE', label: 'Deutsch (de_DE)' },
                ]}
              />
              <SelectField
                label={t('imports.upload.encoding', { defaultValue: 'Kodowanie' })}
                value={state.encoding}
                onChange={(value) => {
                  setField('encoding', value);
                  setField('parsed', null);
                  setField('stagedFileId', null);
                }}
                options={[
                  { value: 'auto', label: 'auto' },
                  { value: 'utf-8', label: 'UTF-8' },
                  { value: 'utf-8-bom', label: 'UTF-8 + BOM' },
                  { value: 'windows-1250', label: 'Windows-1250' },
                  { value: 'iso-8859-2', label: 'ISO-8859-2' },
                ]}
              />
              <SelectField
                label={t('imports.upload.delimiter', { defaultValue: 'Separator' })}
                value={state.delimiter}
                onChange={(value) => {
                  setField('delimiter', value);
                  setField('parsed', null);
                  setField('stagedFileId', null);
                }}
                options={[
                  { value: 'auto', label: 'auto' },
                  { value: ';', label: '; (semicolon)' },
                  { value: ',', label: ', (comma)' },
                  { value: 'tab', label: '↹ (tab)' },
                  { value: '|', label: '| (pipe)' },
                ]}
              />
              <div className="space-y-1.5">
                <span className="text-[12.5px] font-medium">
                  {t('imports.upload.image_source.label', { defaultValue: 'Źródło zdjęć' })}
                </span>
                {(['http', 'zip', 'none'] as const).map((option) => (
                  <label key={option} className="flex items-center gap-2 text-[12.5px]">
                    <input
                      type="radio"
                      name="image-source"
                      value={option}
                      checked={state.imageSource === option}
                      onChange={() => setField('imageSource', option)}
                    />
                    <span>
                      {t(`imports.upload.image_source.${option}`, { defaultValue: option })}
                    </span>
                  </label>
                ))}
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
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="flex justify-end gap-2">
        <Button onClick={() => next()} disabled={!canProceed}>
          {t('imports.wizard.next', { defaultValue: 'Dalej →' })}
        </Button>
      </div>
    </div>
  );
}

function SelectField({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: Array<{ value: string; label: string }>;
}): React.ReactElement {
  return (
    <label className="flex flex-col gap-1">
      <span className="text-[12.5px] font-medium">{label}</span>
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}
