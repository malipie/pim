#!/usr/bin/env bash
# HARD-07 — guard-rail for raw SQL bypassing TenantFilter.
#
# Doctrine TenantFilter only kicks in for ORM-mediated queries; calls to
# Connection::executeQuery / executeStatement / createNativeQuery skip
# it entirely. Without an explicit `tenant_id` predicate (or a documented
# infrastructure exemption) such a call is a cross-tenant data leak
# waiting to happen. This script:
#
#   1. lists every PHP file under apps/api/src/ that uses one of the
#      escape hatches above (`grep -rln`),
#   2. fails the build if any of those files lacks a `tenant-safe:`
#      comment marker. Authors must justify each escape inline so the
#      next reader understands why the absence of TenantFilter is okay.
#
# Marker format (free-form text after the colon — checked verbatim):
#
#     // tenant-safe: <one-line reason>
#
# Reasons in current use:
#   - "infrastructure (admin-only ...)" — DDL / cross-tenant tooling
#   - "junction inherits tenant via FK chain" — junction table queried
#     by tenant-scoped parent ids
#   - "per-row UPDATE keyed by primary key" — id resolved through
#     TenantFilter-aware repository
#   - "explicit tenant_id filter" — WHERE clause includes tenant_id
#
# Exit non-zero with the offender list so CI surfaces every file at
# once instead of bisecting one PR at a time.

set -eu

ROOT="${ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
SRC="${ROOT}/apps/api/src"
PATTERN='executeQuery|executeStatement|createNativeQuery'

if [ ! -d "$SRC" ]; then
    echo "lint-raw-sql: source dir $SRC not found" >&2
    exit 1
fi

# `grep -rln` returns 0 (matches found), 1 (no matches), or 2 (error).
# We treat "no matches" as a green run.
files=$(grep -rln -E "$PATTERN" "$SRC" --include='*.php' || true)

if [ -z "$files" ]; then
    echo "lint-raw-sql: no raw SQL escape hatches found — clean."
    exit 0
fi

offenders=""
total=0
for f in $files; do
    total=$((total + 1))
    if ! grep -q 'tenant-safe:' "$f"; then
        offenders="$offenders\n  $f"
    fi
done

if [ -n "$offenders" ]; then
    echo "lint-raw-sql: $total file(s) use raw SQL; the following lack a tenant-safe: marker:" >&2
    printf "$offenders\n" >&2
    cat >&2 <<'TXT'

Each offender must add an inline comment justifying why TenantFilter is
not needed:

  // tenant-safe: <reason>

Reasons in current use:
  - infrastructure (admin-only DDL / cron / benchmark CLI)
  - junction inherits tenant via FK chain
  - per-row UPDATE keyed by primary key (id from tenant-scoped repo)
  - explicit tenant_id filter in WHERE
TXT
    exit 1
fi

echo "lint-raw-sql: $total file(s) use raw SQL — every one carries a tenant-safe: marker. Clean."
exit 0
