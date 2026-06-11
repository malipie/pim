import { Outlet } from 'react-router';

/**
 * NUI-01 (#1420) — settings navigation moved into the main sidebar
 * (`sidebar-nav.tsx` renders the subtree from `settings-nav-data.ts`).
 * This layout is now a thin content shell; the route tree in App.tsx is
 * unchanged, so every /settings/* deep link keeps working.
 */
export function SettingsLayout() {
  return (
    <div className="min-w-0">
      <Outlet />
    </div>
  );
}
