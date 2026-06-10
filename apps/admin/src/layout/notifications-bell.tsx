import { Bell } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import { useNotificationsInboxOptional } from './notifications-context';
import { useNotifications } from './use-notifications';

/**
 * Notifications bell wrapping a Radix dropdown over the SSE feed (#54).
 *
 * The bell turns into a count badge once unread events accumulate.
 * Clicking the trigger marks everything read so the badge is a "since
 * last open" counter — closer to the Slack/Linear model than a
 * persistent inbox (we deliberately keep the surface light in MVP).
 */
export function NotificationsBell() {
  const { t, i18n } = useTranslation();
  const { entries, unreadCount, markAllRead } = useNotifications();
  // EXR-15 — exports in-app inbox merges into the bell (badge + list).
  const inbox = useNotificationsInboxOptional();
  const totalUnread = unreadCount + (inbox?.unread ?? 0);

  return (
    <DropdownMenu
      onOpenChange={(open) => {
        if (open) {
          markAllRead();
          inbox?.markAllRead();
        }
      }}
    >
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          aria-label={t('notifications.aria_label', { defaultValue: 'Notifications' })}
          className="relative"
        >
          <Bell className="size-4" />
          {totalUnread > 0 ? (
            <span
              className="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground"
              aria-live="polite"
            >
              {totalUnread > 9 ? '9+' : totalUnread}
            </span>
          ) : null}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <DropdownMenuLabel>
          {t('notifications.title', { defaultValue: 'Recent activity' })}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        {(inbox?.entries.length ?? 0) > 0 && (
          <>
            {inbox?.entries.slice(0, 6).map((entry) => (
              <DropdownMenuItem
                key={entry.id}
                asChild
                className="flex flex-col items-start gap-0.5 whitespace-normal"
              >
                <Link to={entry.href ?? '/integrations/exports/sessions'}>
                  <span
                    className={
                      entry.level === 'error'
                        ? 'text-sm font-medium text-brick-600'
                        : entry.level === 'warning'
                          ? 'text-sm font-medium text-orange-700'
                          : 'text-sm font-medium text-emerald-700'
                    }
                  >
                    {entry.title}
                  </span>
                  {entry.body !== undefined && (
                    <span className="text-xs text-muted-foreground">{entry.body}</span>
                  )}
                </Link>
              </DropdownMenuItem>
            ))}
            <DropdownMenuSeparator />
          </>
        )}
        {entries.length === 0 && (inbox?.entries.length ?? 0) === 0 ? (
          <div className="px-3 py-6 text-center text-xs text-muted-foreground">
            {t('notifications.empty', { defaultValue: 'No recent events.' })}
          </div>
        ) : (
          entries.slice(0, 8).map((entry) => (
            <DropdownMenuItem
              key={entry.id}
              className="flex flex-col items-start gap-0.5 whitespace-normal"
            >
              <span className="text-sm font-medium">{entry.type}</span>
              <span className="text-xs text-muted-foreground">
                {formatTime(entry.occurredOn, i18n.language)}
              </span>
            </DropdownMenuItem>
          ))
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

function formatTime(value: string, locale: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(locale, { timeStyle: 'short', dateStyle: 'short' }).format(date);
}
