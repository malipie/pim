import { ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import {
  type ChannelOption,
  type LocaleOption,
  PRODUCT_LOCALES,
  type ProductChannel,
  type ProductLocale,
} from './types';

export interface LocaleChannelToolbarProps {
  locale: ProductLocale;
  channel: ProductChannel | null;
  onLocaleChange: (next: ProductLocale) => void;
  onChannelChange: (next: ProductChannel | null) => void;
  /**
   * #1149 — the tenant's enabled locales (from `effective-attribute-groups`).
   * Falls back to the static PRODUCT_LOCALES on first paint / when absent.
   */
  locales?: LocaleOption[];
  /**
   * #1155 — the tenant's channels (from `/api/channels`). #1259 — when the
   * tenant has no channels the picker shows ONLY "Wszystkie kanały"; we no
   * longer fall back to a hardcoded shopify/baselinker/allegro list, which
   * looked like real channels but was a mock.
   */
  channels?: ChannelOption[];
}

/**
 * VIEW-07 (#420) — toolbar replacing the prototype's segmented PL/EN/DE/CS
 * and Shopify/BaseLinker/Allegro pills (`detail-view.jsx` lines 185–197)
 * with two shadcn DropdownMenus per the operator's explicit deviation
 * from the mockup.
 */
export function LocaleChannelToolbar({
  locale,
  channel,
  onLocaleChange,
  onChannelChange,
  locales,
  channels,
}: LocaleChannelToolbarProps) {
  const { t } = useTranslation();
  const localeCodes: readonly string[] =
    locales !== undefined && locales.length > 0 ? locales.map((l) => l.code) : PRODUCT_LOCALES;

  // #1155 / #1259 — channel options come ONLY from the tenant's real
  // channels (`/api/channels`). No tenant channels → empty list, so the
  // picker offers just "Wszystkie kanały". No hardcoded mock fallback.
  const channelList: { code: string; label: string }[] = (channels ?? []).map((c) => ({
    code: c.code,
    label: c.name ?? c.code,
  }));
  const channelLabel = (code: string): string =>
    channelList.find((c) => c.code === code)?.label ?? code;

  return (
    <div className="flex items-center gap-2">
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="ghost"
            size="sm"
            className="h-9 gap-1.5 rounded-xl bg-white px-3 text-[12px] font-mono uppercase soft-shadow"
            aria-label={t('products.detail.locale.label', { defaultValue: 'Język' })}
          >
            {locale}
            <ChevronDown className="size-3" aria-hidden />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-44">
          <DropdownMenuLabel>
            {t('products.detail.locale.label', { defaultValue: 'Język' })}
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          {localeCodes.map((option) => (
            <DropdownMenuItem
              key={option}
              onClick={() => onLocaleChange(option)}
              className={option === locale ? 'bg-secondary' : ''}
            >
              <span className="font-mono text-xs uppercase">{option}</span>
              <span className="ml-2 text-xs text-muted-foreground">
                {t(`products.detail.locale.option.${option}`, {
                  defaultValue: option.toUpperCase(),
                })}
              </span>
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="ghost"
            size="sm"
            className="h-9 gap-1.5 rounded-xl bg-white px-3 text-[12px] font-medium soft-shadow"
            aria-label={t('products.detail.channel.label', { defaultValue: 'Kanał' })}
          >
            {channel === null
              ? t('products.detail.channel.none', { defaultValue: 'Wszystkie kanały' })
              : channelLabel(channel)}
            <ChevronDown className="size-3" aria-hidden />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-52">
          <DropdownMenuLabel>
            {t('products.detail.channel.label', { defaultValue: 'Kanał' })}
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            onClick={() => onChannelChange(null)}
            className={channel === null ? 'bg-secondary' : ''}
          >
            <span className="text-xs">
              {t('products.detail.channel.none', { defaultValue: 'Wszystkie kanały' })}
            </span>
          </DropdownMenuItem>
          {channelList.map((option) => (
            <DropdownMenuItem
              key={option.code}
              onClick={() => onChannelChange(option.code)}
              className={option.code === channel ? 'bg-secondary' : ''}
            >
              <span className="text-xs font-medium">{option.label}</span>
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}
