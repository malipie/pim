import * as React from 'react';

import { cn } from '@/lib/utils';

export type FileKind = 'csv-xlsx' | 'zip';

interface FileDropzoneProps {
  /** Called when the user drops or selects a file. */
  onFile: (file: File) => void;
  /** Limit to a single MIME group; the wizard's Step 1 accepts CSV/xlsx OR ZIP. */
  kind: FileKind;
  /** Max bytes — rejected before reaching the parent. */
  maxBytes: number;
  /** Already-selected file shown in the body so the user sees what they uploaded. */
  selected?: File | null;
  /** Localised copy. */
  label: string;
  hint?: string;
  rejectMessage?: (file: File) => string;
  disabled?: boolean;
  className?: string;
}

const ACCEPT: Record<FileKind, string> = {
  'csv-xlsx':
    '.csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  zip: '.zip,application/zip,application/x-zip-compressed',
};

const ACCEPT_REGEX: Record<FileKind, RegExp> = {
  'csv-xlsx': /\.(csv|xlsx)$/i,
  zip: /\.zip$/i,
};

/**
 * Drag-and-drop file picker for the import wizard's Step 1
 * (spec §5.2). Fully typed in terms of which file kind the dropzone
 * accepts so the same component drops the source file on one panel
 * and the optional ZIP archive on another.
 */
export function FileDropzone({
  onFile,
  kind,
  maxBytes,
  selected,
  label,
  hint,
  rejectMessage,
  disabled,
  className,
}: FileDropzoneProps): React.ReactElement {
  const [isDragging, setIsDragging] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const inputRef = React.useRef<HTMLInputElement>(null);

  const handleFile = (file: File): void => {
    if (!ACCEPT_REGEX[kind].test(file.name)) {
      setError(rejectMessage?.(file) ?? `Niewłaściwy typ pliku: ${file.name}`);
      return;
    }
    if (file.size > maxBytes) {
      setError(`Plik za duży (${formatBytes(file.size)} > ${formatBytes(maxBytes)})`);
      return;
    }
    setError(null);
    onFile(file);
  };

  return (
    <div className={cn('w-full', className)}>
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        onDragOver={(event) => {
          event.preventDefault();
          if (!disabled) {
            setIsDragging(true);
          }
        }}
        onDragLeave={() => setIsDragging(false)}
        onDrop={(event) => {
          event.preventDefault();
          setIsDragging(false);
          if (disabled) {
            return;
          }
          const file = event.dataTransfer.files.item(0);
          if (file !== null) {
            handleFile(file);
          }
        }}
        disabled={disabled}
        className={cn(
          'flex w-full flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-input bg-background p-8 text-center transition-colors',
          'hover:border-primary hover:bg-accent/40',
          'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
          isDragging && 'border-primary bg-accent/40',
          disabled && 'cursor-not-allowed opacity-50',
        )}
        aria-label={label}
      >
        <span aria-hidden="true" className="text-2xl">
          📥
        </span>
        <span className="text-sm font-medium">{label}</span>
        {hint !== undefined && <span className="text-xs text-muted-foreground">{hint}</span>}
        {selected !== undefined && selected !== null && (
          <span className="mt-2 inline-flex items-center gap-2 rounded-full bg-accent px-3 py-1 text-xs">
            ☑ {selected.name} ({formatBytes(selected.size)})
          </span>
        )}
      </button>
      <input
        ref={inputRef}
        type="file"
        aria-label={label}
        className="sr-only"
        accept={ACCEPT[kind]}
        disabled={disabled}
        onChange={(event) => {
          const file = event.target.files?.item(0);
          if (file != null) {
            handleFile(file);
          }
          event.target.value = '';
        }}
      />
      {error !== null && (
        <p role="alert" className="mt-2 text-xs text-destructive">
          {error}
        </p>
      )}
    </div>
  );
}

function formatBytes(value: number): string {
  if (value < 1024) {
    return `${value} B`;
  }
  if (value < 1024 * 1024) {
    return `${(value / 1024).toFixed(1)} kB`;
  }
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}
