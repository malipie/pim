export interface TenantLocaleListItem {
  id: string;
  code: string;
  label: string;
  language: string;
  region: string | null;
  displayName: Record<string, string>;
  isDefault: boolean;
  isMandatory: boolean;
  fallbackCode: string | null;
  sortOrder: number;
  isActive: boolean;
  createdAt: string;
}

export interface TenantLocaleListResponse {
  items: TenantLocaleListItem[];
}
