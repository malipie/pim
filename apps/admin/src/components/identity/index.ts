/**
 * RBAC Phase 4 — public surface for identity-aware components.
 */
export {
  GatedAction,
  type GatedActionProps,
  GatedButton,
  type GatedButtonProps,
} from './GatedAction';
export { LastAdminProtectionModal } from './LastAdminProtectionModal';
export { OwnerUniquenessModal } from './OwnerUniquenessModal';
export { PermissionGate, type PermissionGateProps } from './PermissionGate';
export {
  Forbidden403Page,
  type Forbidden403PageProps,
  PermissionRoute,
  type PermissionRouteProps,
} from './PermissionRoute';
