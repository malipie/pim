/**
 * RBAC-P5-019 (#709) — wire shape for `/api/admin/tenants`.
 * Mirrors {@link SuperAdminTenantResponseBuilder} on the API side.
 *
 * **Privacy boundary:** this contract carries metadata only — never
 * per-tenant domain rows. Adding a field that exposes products /
 * attributes / values would breach PRD §11.
 */

export type TenantStatus = 'active' | 'suspended' | 'deleted';

export interface AdminTenantSummary {
  id: string;
  code: string;
  name: string;
  domain: string | null;
  plan: string;
  status: TenantStatus;
  primary_locale: string;
  enabled_locales: string[];
  active_users: number;
  suspended_at: string | null;
  deleted_at: string | null;
  created_at: string;
}
