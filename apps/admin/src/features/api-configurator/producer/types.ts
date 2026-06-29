/**
 * APIC-P4-06 — row types for the producer hub (Profiles / Keys / Webhooks).
 */
export const PRODUCER_TABS = ['profiles', 'keys', 'webhooks'] as const;
export type ProducerTab = (typeof PRODUCER_TABS)[number];

export function toProducerTab(value: string | null): ProducerTab {
  return PRODUCER_TABS.includes(value as ProducerTab) ? (value as ProducerTab) : 'profiles';
}

export interface ApiProfileRow {
  id: string;
  code: string;
  name: string;
  status?: string;
  outputFormat?: string;
  objectTypeIds?: string[];
  includedAttributes?: string[];
  webhookUrl?: string | null;
  webhookEvents?: string[];
}

export interface ApiKeyRow {
  id: string;
  keyPrefix: string;
  name: string;
  scopes?: string[];
  expiresAt?: string | null;
  revokedAt?: string | null;
  lastUsedAt?: string | null;
}

export interface WebhookDeliveryRow {
  id: string;
  profileId: string;
  eventType: string;
  status: string;
  attempts: number;
  createdAt: string;
}
