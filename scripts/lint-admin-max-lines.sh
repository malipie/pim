#!/usr/bin/env bash
# AUD-057 — guard-rail against new admin source monoliths.
#
# The audit flagged 17+ admin files over 500 lines (product-detail-page
# 1499, universal-list-page 1141, attributes/show 1137, …). Long files
# hurt readability + review and tend to grow unbounded. Biome 2.x has no
# per-file max-lines rule (its complexity rules are per-function), so this
# script fills the gap.
#
# Like lint-jsonfetch-useeffect.sh it does the affordable thing rather than
# a big-bang split of every monolith (out of scope — ADR-0021 sibling):
# it counts admin .ts/.tsx files whose length exceeds THRESHOLD lines and
# fails the build if that count *exceeds* a frozen baseline. A new file
# over the line, or an existing borderline file crossing it, pushes the
# count up → red CI. Splitting a monolith below the line → lower the
# baseline. The number may only shrink.
#
# This is a WARN-style policy for the existing offenders (they stay, the
# baseline tolerates them) and a hard stop for regressions.

set -eu

ROOT="${ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
SRC="${ROOT}/apps/admin/src"

THRESHOLD="${ADMIN_MAX_LINES_THRESHOLD:-500}"
# Frozen baseline = number of files currently over THRESHOLD. Lower it
# (never raise without justification) whenever a monolith is split below
# the line. product-detail-page.tsx was split under AUD-057, dropping the
# count from 22 to 21.
BASELINE="${ADMIN_MAX_LINES_BASELINE:-21}"

if [ ! -d "$SRC" ]; then
    echo "lint-admin-max-lines: source dir $SRC not found" >&2
    exit 1
fi

count=0
offenders=""
while IFS= read -r f; do
    lines=$(wc -l < "$f")
    if [ "$lines" -gt "$THRESHOLD" ]; then
        count=$((count + 1))
        offenders="$offenders\n  $lines  ${f#"$ROOT"/}"
    fi
done <<EOF
$(find "$SRC" \( -name '*.ts' -o -name '*.tsx' \) -type f)
EOF

if [ "$count" -gt "$BASELINE" ]; then
    echo "lint-admin-max-lines: FAIL — $count files exceed ${THRESHOLD} lines (baseline $BASELINE)." >&2
    cat >&2 <<TXT

A file grew past ${THRESHOLD} lines (or a new one shipped over it). Split it
into focused sibling modules — extract pure helpers, sub-components, or a
data hook — so the page only composes them. See product-detail-page.tsx /
product-detail-helpers.ts / product-detail-other-tabs.tsx for the pattern.
TXT
    printf 'Files over %s lines:%b\n' "$THRESHOLD" "$offenders" | sort -rn >&2
    exit 1
fi

if [ "$count" -lt "$BASELINE" ]; then
    echo "lint-admin-max-lines: $count files exceed ${THRESHOLD} lines — below baseline $BASELINE."
    echo "  Nice — lower BASELINE in this script to $count to lock in the win."
    exit 0
fi

echo "lint-admin-max-lines: $count files exceed ${THRESHOLD} lines — at baseline $BASELINE. No regression."
exit 0
