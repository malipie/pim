// Re-exports populated after `pnpm --filter @pim/shared-types generate`
// (uses openapi-typescript on http://pim.localhost/api/docs.jsonld).
//
// Until the first generation this file is intentionally empty so that
// `tsc --noEmit` passes in CI without a generated artifact in the repo.
//
// After generation, replace with:
//   export type * from './api';

export {};
