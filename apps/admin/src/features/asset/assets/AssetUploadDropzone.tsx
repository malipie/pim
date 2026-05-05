import { CheckCircle2, FileUp, Loader2, UploadCloud, XCircle } from 'lucide-react';
import { useCallback, useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  ACCEPTED_MIME_TYPES,
  type DuplicateAssetError,
  isAcceptedMimeType,
  MAX_IMAGE_BYTES,
  MAX_PDF_BYTES,
  maxBytesFor,
  type UploadAssetError,
  type UploadAssetResult,
  uploadAsset,
} from '@/lib/asset-upload';
import { cn } from '@/lib/utils';

type EntryStatus = 'queued' | 'uploading' | 'succeeded' | 'failed' | 'duplicate';

interface UploadEntry {
  id: string;
  file: File;
  status: EntryStatus;
  progress: number;
  errorMessage?: string;
  duplicate?: DuplicateAssetError;
  result?: UploadAssetResult;
}

export interface AssetUploadDropzoneProps {
  onCompleted: (results: UploadAssetResult[]) => void;
  onDuplicate?: (duplicate: DuplicateAssetError, file: File) => void;
}

export function AssetUploadDropzone({ onCompleted, onDuplicate }: AssetUploadDropzoneProps) {
  const { t } = useTranslation();
  const inputId = useId();
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [isHover, setIsHover] = useState(false);
  const [entries, setEntries] = useState<UploadEntry[]>([]);

  const acceptAttr = ACCEPTED_MIME_TYPES.join(',');

  const handleFiles = useCallback(
    async (files: File[]) => {
      const accepted: UploadEntry[] = [];
      const rejected: UploadEntry[] = [];

      for (const file of files) {
        const id = `${file.name}-${file.size}-${file.lastModified}-${Math.random().toString(36).slice(2, 8)}`;
        if (!isAcceptedMimeType(file.type)) {
          rejected.push({
            id,
            file,
            status: 'failed',
            progress: 0,
            errorMessage: t('assets.upload.failed_mime', { mime: file.type || 'unknown' }),
          });
          continue;
        }
        const limit = maxBytesFor(file.type);
        if (file.size > limit) {
          rejected.push({
            id,
            file,
            status: 'failed',
            progress: 0,
            errorMessage: t('assets.upload.failed_size', {
              limit: Math.round(limit / 1024 / 1024),
            }),
          });
          continue;
        }
        accepted.push({ id, file, status: 'queued', progress: 0 });
      }

      setEntries((prev) => [...prev, ...accepted, ...rejected]);

      const completed: UploadAssetResult[] = [];
      for (const entry of accepted) {
        setEntries((prev) =>
          prev.map((existing) =>
            existing.id === entry.id ? { ...existing, status: 'uploading' } : existing,
          ),
        );

        try {
          const result = await uploadAsset({
            file: entry.file,
            onProgress: (percent) => {
              setEntries((prev) =>
                prev.map((existing) =>
                  existing.id === entry.id ? { ...existing, progress: percent } : existing,
                ),
              );
            },
          });
          completed.push(result);
          setEntries((prev) =>
            prev.map((existing) =>
              existing.id === entry.id
                ? { ...existing, status: 'succeeded', progress: 100, result }
                : existing,
            ),
          );
        } catch (error) {
          const typed = error as UploadAssetError;
          if ('kind' in typed && typed.kind === 'duplicate') {
            setEntries((prev) =>
              prev.map((existing) =>
                existing.id === entry.id
                  ? {
                      ...existing,
                      status: 'duplicate',
                      duplicate: typed,
                      errorMessage: t('assets.upload.failed_duplicate'),
                    }
                  : existing,
              ),
            );
            onDuplicate?.(typed, entry.file);
            continue;
          }

          let message = t('assets.upload.failed_generic');
          if ('kind' in typed) {
            if (typed.kind === 'too_large') {
              message = t('assets.upload.failed_size', {
                limit: Math.round(maxBytesFor(entry.file.type) / 1024 / 1024),
              });
            } else if (typed.kind === 'unsupported_mime') {
              message = t('assets.upload.failed_mime', { mime: entry.file.type });
            } else if (typed.message) {
              message = typed.message;
            }
          }

          setEntries((prev) =>
            prev.map((existing) =>
              existing.id === entry.id
                ? { ...existing, status: 'failed', errorMessage: message }
                : existing,
            ),
          );
        }
      }

      if (completed.length > 0) {
        onCompleted(completed);
      }
    },
    [onCompleted, onDuplicate, t],
  );

  const onDragOver = (event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    setIsHover(true);
  };
  const onDragLeave = () => setIsHover(false);
  const onDrop = (event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    setIsHover(false);
    const files = Array.from(event.dataTransfer.files);
    if (files.length > 0) {
      void handleFiles(files);
    }
  };

  const onInputChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const files = event.target.files ? Array.from(event.target.files) : [];
    if (files.length > 0) {
      void handleFiles(files);
    }
    event.target.value = '';
  };

  return (
    <div className="space-y-3">
      {/* Drop zone wraps a real <button> trigger (the "Browse" CTA) so screen
          readers announce the available action. The wrapping div carries only
          drag-and-drop hooks; clicking the visual surface anywhere defers to
          the inner button via the bubbling pointer event. */}
      {/* biome-ignore lint/a11y/useSemanticElements: surface uses role=button so
          inner controls (button + input) stay valid as descendants */}
      <div
        role="button"
        tabIndex={0}
        aria-label={t('assets.upload.browse')}
        onClick={() => inputRef.current?.click()}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            inputRef.current?.click();
          }
        }}
        onDragOver={onDragOver}
        onDragEnter={onDragOver}
        onDragLeave={onDragLeave}
        onDrop={onDrop}
        className={cn(
          'flex w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed bg-card px-6 py-10 text-center transition-colors',
          isHover
            ? 'border-primary bg-primary/5'
            : 'border-muted-foreground/30 hover:border-primary/60',
        )}
      >
        <UploadCloud className="size-8 text-muted-foreground" />
        <div className="space-y-1">
          <p className="text-sm font-medium">{t('assets.upload.drop_here')}</p>
          <p className="text-xs text-muted-foreground">{t('assets.upload.click_to_select')}</p>
        </div>
        <p className="text-xs text-muted-foreground">{t('assets.upload.allowed_formats')}</p>
        <p className="text-xs text-muted-foreground">
          {t('assets.upload.max_size_image')} · {t('assets.upload.max_size_pdf')}
        </p>
        <input
          ref={inputRef}
          id={inputId}
          type="file"
          accept={acceptAttr}
          multiple
          className="sr-only"
          onChange={onInputChange}
        />
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={(event) => {
            event.stopPropagation();
            inputRef.current?.click();
          }}
        >
          <FileUp className="mr-2 size-4" />
          {t('assets.upload.browse')}
        </Button>
      </div>

      {entries.length > 0 ? (
        <ul className="space-y-1 rounded-md border bg-card p-2">
          {entries.map((entry) => (
            <li key={entry.id} className="flex items-center gap-3 px-2 py-1 text-sm">
              <span className="size-4 shrink-0">{statusIcon(entry.status)}</span>
              <span className="min-w-0 flex-1 truncate" title={entry.file.name}>
                {entry.file.name}
              </span>
              {entry.status === 'uploading' ? (
                <span className="font-mono text-xs text-muted-foreground">{entry.progress}%</span>
              ) : null}
              {entry.errorMessage ? (
                <span className="text-xs text-destructive" role="alert">
                  {entry.errorMessage}
                </span>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}

      <p className="text-xs text-muted-foreground">
        {t('assets.upload.max_size_image')} · {t('assets.upload.max_size_pdf')}
      </p>
      <input type="hidden" data-max-image={MAX_IMAGE_BYTES} data-max-pdf={MAX_PDF_BYTES} />
    </div>
  );
}

function statusIcon(status: EntryStatus): React.ReactNode {
  switch (status) {
    case 'queued':
      return <Loader2 className="size-4 animate-spin text-muted-foreground" aria-hidden="true" />;
    case 'uploading':
      return <Loader2 className="size-4 animate-spin text-primary" aria-hidden="true" />;
    case 'succeeded':
      return <CheckCircle2 className="size-4 text-emerald-600" aria-hidden="true" />;
    case 'failed':
    case 'duplicate':
      return <XCircle className="size-4 text-destructive" aria-hidden="true" />;
    default:
      return null;
  }
}
