/**
 * RBAC-P5-019 (#709) — wire shape for `/api/admin/tenants`.
 * Mirrors {@link SuperAdminTenantResponseBuilder} on the API side.
 *
 * **Privacy boundary:** this contract carries metadata only — never
 * per-tenant domain rows. Adding a field that exposes products /
 * attributes / values would breach PRD §11.
 */

export interface AdminTenantSummary {
  id: string;
  code: string;
  name: string;
  domain: string | null;
  plan: string;
  primary_locale: string;
  enabled_locales: string[];
  active_users: number;
  created_at: string;
}
