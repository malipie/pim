import type { LucideIcon } from 'lucide-react';
import { Box, Globe, Layers, Plug, Shield, Upload, Zap } from 'lucide-react';

import { cn } from '@/lib/utils';

export type SourceType = 'sftp' | 'ftp' | 'http' | 'webhook' | 'folder' | 'upload' | 'api';

const SOURCE_STYLES: Record<SourceType, { icon: LucideIcon; color: string }> = {
  sftp: { icon: Shield, color: 'text-emerald-600 bg-emerald-50' },
  ftp: { icon: Layers, color: 'text-amber-700 bg-amber-50' },
  http: { icon: Globe, color: 'text-sky-700 bg-sky-50' },
  webhook: { icon: Zap, color: 'text-orange-700 bg-orange-50' },
  folder: { icon: Box, color: 'text-sky-700 bg-sky-50' },
  upload: { icon: Upload, color: 'text-zinc-700 bg-zinc-100' },
  api: { icon: Plug, color: 'text-rose-700 bg-rose-50' },
};

export interface SourceIconProps {
  type: SourceType;
  size?: number;
  className?: string;
}

export function SourceIcon({ type, size = 14, className }: SourceIconProps) {
  const style = SOURCE_STYLES[type];
  const Icon = style.icon;
  return (
    <span
      className={cn(
        'inline-flex items-center justify-center h-6 w-6 rounded-md',
        style.color,
        className,
      )}
    >
      <Icon size={size} aria-hidden="true" />
    </span>
  );
}
