import { Navigate } from 'react-router';

export function SettingsIndex() {
  // Settings landing redirects to the first tab (Security / Bezpieczeństwo),
  // which is the first nav item and is available to every authenticated user
  // (own MFA + password). Previously pointed at /settings/menu. Refs #1142.
  return <Navigate to="/settings/security" replace />;
}
