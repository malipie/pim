/**
 * #1150 / #1155 — build the `?locale=&channel=` scope query string for
 * locale/channel-aware object reads + writes. A null channel = "all
 * channels" (global), so it is omitted.
 */
export function scopeQuery(locale: string, channel: string | null): string {
  const params = new URLSearchParams({ locale });
  if (channel !== null) {
    params.set('channel', channel);
  }
  return `?${params.toString()}`;
}
