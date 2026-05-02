import { ObjectTypeWizard } from '@/components/modeling/object-type-wizard';

/**
 * VIEW-01 (#372) — route-level page for `/modeling/object-types/new`.
 * Renders the wizard inline within the modeling shell (sidebar + topbar
 * stay visible). Replaces the legacy `<CreateCustomObjectTypeDialog>`
 * Sheet — see ticket section 3.1.
 */
export function ObjectTypeWizardPage() {
  return <ObjectTypeWizard />;
}
