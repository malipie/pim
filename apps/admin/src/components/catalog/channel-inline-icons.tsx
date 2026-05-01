import { Radio } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import type { SyncAggregate } from './sync-aggregate-icon';

export interface ChannelStatusEntry {
  code: string;
  status: SyncAggregate;
  liveUrl?: string | null;
}

const DOT_TONE: Record<SyncAggregate, string> = {
  green: 'bg-emerald-500',
  yellow: 'bg-amber-500',
  red: 'bg-rose-500',
  gray: 'bg-muted-foreground/40',
};

/**
 * UI-02.10 follow-up — per-channel inline icon strip with click-through
 * to the live store URL ("View on X"). Per
 * `Project Plan/UI/epik-02-produkty.md` §4.5.
 *
 * Renders up to `maxVisible` icons; the overflow collapses into a
 * "+N more" pill that lists the remaining channel codes in a tooltip.
 */
export function ChannelInlineIcons({
  channels,
  maxVisible = 5,
}: {
  channels: ChannelStatusEntry[];
  maxVisible?: number;
}) {
  if (channels.length === 0) {
    return <span className="text-xs text-muted-foreground">—</span>;
  }
  const visible = channels.slice(0, maxVisible);
  const overflow = channels.slice(maxVisible);

  return (
    <div className="flex items-center gap-1">
      {visible.map((channel) => (
        <ChannelIcon key={channel.code} channel={channel} />
      ))}
      {overflow.length > 0 ? (
        <span
          className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
          title={overflow.map((c) => c.code).join(', ')}
        >
          +{overflow.length}
        </span>
      ) : null}
    </div>
  );
}

function ChannelIcon({ channel }: { channel: ChannelStatusEntry }) {
  const { t } = useTranslation();
  const dotTone = DOT_TONE[channel.status];
  const isClickable = typeof channel.liveUrl === 'string' && channel.liveUrl !== '';
  const inner = (
    <span className="relative inline-flex items-center justify-center">
      <Radio className="size-3 text-muted-foreground" />
      <span
        className={`absolute -bottom-0.5 -right-0.5 size-1.5 rounded-full ring-1 ring-background ${dotTone}`}
      />
    </span>
  );
  if (isClickable) {
    return (
      <a
        href={channel.liveUrl ?? '#'}
        target="_blank"
        rel="noopener noreferrer"
        title={t('products.channels.view_on', {
          channel: channel.code,
          defaultValue: 'View on {{channel}}',
        })}
        className="rounded p-0.5 hover:bg-accent"
      >
        {inner}
      </a>
    );
  }
  return (
    <span
      title={t('products.channels.pending', {
        channel: channel.code,
        defaultValue: '{{channel}} — pending publication',
      })}
      className="rounded p-0.5 opacity-60"
    >
      {inner}
    </span>
  );
}
