#!/usr/bin/env bash
# AUD-055 — guard-rail against the stale-data anti-pattern in the admin.
#
# Background (ADR-0021 + lesson feedback_useeffect_to_usequery_pattern):
# `jsonFetch` is the single-origin HTTP transport, NOT a cache layer. When
# a component loads server data with `jsonFetch` inside a `useEffect`, that
# read is invisible to TanStack Query's cache — so a mutation elsewhere
# that calls `queryClient.invalidateQueries(...)` does NOT refresh the
# screen. The fix is `useQuery` (queryFn may still call jsonFetch).
#
# Migrating all ~138 jsonFetch sites is an L-sized job and is explicitly
# out of scope (ADR-0021 §4). This script does the affordable thing:
# stop the bleeding. It counts admin source files where `jsonFetch` and
# `useEffect` co-occur (the anti-pattern signature) and fails the build if
# that count *exceeds* a frozen baseline. New offenders push the count up
# → red CI. Migrating an existing file down → lower the baseline below
# (intentionally one-directional: the number may only shrink).
#
# Why a count and not a per-file marker: the offenders pre-date the rule;
# a marker scheme would require touching all 64 today. The threshold lets
# the debt drain over time without a big-bang refactor while making any
# *new* regression a hard CI failure.

set -eu

ROOT="${ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
SRC="${ROOT}/apps/admin/src"

# Frozen baseline. Lower this (never raise it without an ADR-0021 §4
# justification in the PR) every time a mutation-reactive screen is
# migrated jsonFetch+useEffect -> useQuery.
BASELINE="${JSONFETCH_USEEFFECT_BASELINE:-61}"

if [ ! -d "$SRC" ]; then
    echo "lint-jsonfetch-useeffect: source dir $SRC not found" >&2
    exit 1
fi

# grep -rln returns 0 (matches) / 1 (none) / 2 (error); treat "none" green.
jsonfetch_files=$(grep -rln "jsonFetch" "$SRC" --include='*.ts' --include='*.tsx' || true)

# Match the call `useEffect(` rather than the bare word so prose in
# comments / ADR references does not count as an offender.
count=0
offenders=""
for f in $jsonfetch_files; do
    if grep -q 'useEffect(' "$f"; then
        count=$((count + 1))
        offenders="$offenders\n  ${f#"$ROOT"/}"
    fi
done

if [ "$count" -gt "$BASELINE" ]; then
    echo "lint-jsonfetch-useeffect: FAIL — $count files mix jsonFetch + useEffect (baseline $BASELINE)." >&2
    cat >&2 <<'TXT'

A new file pairs jsonFetch with useEffect to read server data. That read
is invisible to TanStack Query's cache, so a mutation elsewhere that calls
queryClient.invalidateQueries(...) will NOT refresh it (stale-data bug —
see ADR-0021 + lesson feedback_useeffect_to_usequery_pattern).

Fix: load the data with useQuery (queryFn may call jsonFetch), give it an
explicit queryKey, and invalidate that key after mutations. jsonFetch is
fine for command actions (POST/PATCH/DELETE) and one-shot non-reactive
reads (file/blob/parse-preview) — those do not count here.
TXT
    printf 'Current offenders:%b\n' "$offenders" >&2
    exit 1
fi

if [ "$count" -lt "$BASELINE" ]; then
    echo "lint-jsonfetch-useeffect: $count files mix jsonFetch + useEffect — below baseline $BASELINE."
    echo "  Nice — lower BASELINE in this script to $count to lock in the win."
    exit 0
fi

echo "lint-jsonfetch-useeffect: $count files mix jsonFetch + useEffect — at baseline $BASELINE. No regression."
exit 0
