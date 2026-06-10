import { useGetIdentity } from '@refinedev/core';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import { useNotificationsInboxOptional } from '@/layout/notifications-context';
import { useInvalidateExportSessions } from './useExportSessions';
import { useExportSessionsStream } from './useExportSessionsStream';

interface Identity {
  id: string;
}

/**
 * EXR-15 — shell-mounted subscriber: terminal export events land in the
 * in-app inbox (bell badge) and invalidate the sessions cache even when
 * the user is elsewhere in the app. Render-less.
 */
export function ExportsLiveBridge(): null {
  const { t } = useTranslation();
  const { data: identity } = useGetIdentity<Identity>();
  const inbox = useNotificationsInboxOptional();
  const invalidate = useInvalidateExportSessions();
  const { lastEvent } = useExportSessionsStream(identity?.id ?? null);
  const seenRef = useRef<Set<string>>(new Set());

  useEffect(() => {
    if (lastEvent === null || lastEvent.event !== 'status') return;
    const status = lastEvent.status;
    if (status !== 'done' && status !== 'error' && status !== 'cancelled') return;

    invalidate();

    const key = `${lastEvent.session_id}:${status}`;
    if (seenRef.current.has(key) || inbox === null) return;
    seenRef.current.add(key);

    if (status === 'done') {
      inbox.add({
        id: key,
        level: 'success',
        title: t('exports.notifications.done_title'),
        body: t('exports.notifications.done_body', { count: lastEvent.success_count ?? 0 }),
        href: `/integrations/exports/sessions/${lastEvent.session_id}`,
      });
    } else if (status === 'cancelled') {
      inbox.add({
        id: key,
        level: 'warning',
        title: t('exports.notifications.cancelled_title'),
        href: `/integrations/exports/sessions/${lastEvent.session_id}`,
      });
    } else {
      inbox.add({
        id: key,
        level: 'error',
        title: t('exports.notifications.error_title'),
        body: lastEvent.error_message ?? undefined,
        href: `/integrations/exports/sessions/${lastEvent.session_id}`,
      });
    }
  }, [lastEvent, inbox, invalidate, t]);

  return null;
}
