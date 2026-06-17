#!/usr/bin/env bash
# AUD-017 / AUD-021 — guard-rail keeping the pgBackRest backup cron alive.
#
# The PIM database image (docker/postgres) schedules backups with dcron
# (dillon's cron 4.5). Two regressions silently killed the backup cron for
# ~49 days and would do so again, neither caught by hadolint/yamllint:
#
#   (1) dcron only loads a PER-USER crontab once it has been registered
#       through `crontab -u <user>`. A file dropped straight into
#       /etc/crontabs by the Dockerfile is synchronised for `root` but
#       SILENTLY IGNORED for `postgres` — so the schedule never fired
#       (cronstamps/postgres never created, no cron.log, repo stuck on the
#       2026-04-28 full). The fix lives in start-pim.sh: it MUST run
#       `crontab -u postgres …` before launching crond.
#
#   (2) The crontab baked into the Dockerfile drifted from the production
#       schedule (a single arg-less `pim-cron.sh` line → TYPE=incr, and the
#       weekly restore-test was missing entirely). The schedule MUST carry a
#       full backup, a differential backup, AND the weekly restore-test.
#
# This script fails the build when EITHER hole reopens. It is purely static
# (greps the repo tree, no Docker needed) so it runs in seconds in CI.
# Run from the repo root.

set -eu

ROOT="${ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "$ROOT"

DOCKERFILE="docker/postgres/Dockerfile"
ENTRYPOINT="docker/postgres/start-pim.sh"
CRON_SCRIPT="docker/postgres/pim-cron.sh"
RESTORE_SCRIPT="docker/postgres/pim-restore-test.sh"

status=0
fail() { echo "lint-backup-cron: $*" >&2; status=1; }

# ── Pre-flight: the files this guard reasons about must exist. ────────────
for f in "$DOCKERFILE" "$ENTRYPOINT" "$CRON_SCRIPT" "$RESTORE_SCRIPT"; do
    [ -f "$f" ] || fail "expected file missing: $f"
done
[ "$status" -eq 0 ] || exit 1

# ── Rule 1: the entrypoint must register the postgres crontab via
#    `crontab -u postgres` (dcron ignores an unregistered per-user file). ──
if ! grep -Eq 'crontab[[:space:]]+-u[[:space:]]+postgres' "$ENTRYPOINT"; then
    fail "root cause guard — $ENTRYPOINT does not register the postgres crontab.
  dcron silently ignores /etc/crontabs/postgres unless start-pim.sh runs
  'crontab -u postgres <file>' BEFORE launching crond. Without it the backup
  cron is dead (AUD-017). Re-add the registration step."
fi

# The registration must precede `crond` — installing after the daemon starts
# leaves a window (and historically was simply absent).
if grep -Eq 'crontab[[:space:]]+-u[[:space:]]+postgres' "$ENTRYPOINT" \
   && grep -nq '^[[:space:]]*crond\b' "$ENTRYPOINT"; then
    reg_line=$(grep -nE 'crontab[[:space:]]+-u[[:space:]]+postgres' "$ENTRYPOINT" | head -1 | cut -d: -f1)
    crond_line=$(grep -nE '^[[:space:]]*crond\b' "$ENTRYPOINT" | head -1 | cut -d: -f1)
    if [ "$reg_line" -ge "$crond_line" ]; then
        fail "ordering — $ENTRYPOINT registers the crontab (line $reg_line) at or
  after starting crond (line $crond_line). Register the postgres crontab
  BEFORE 'crond' so the daemon loads it on the first synchronisation pass."
    fi
fi

# ── Rule 2: the Dockerfile crontab must carry full + diff + weekly
#    restore-test, written for the postgres user. ──────────────────────────
if ! grep -Eq 'pim-cron\.sh[[:space:]]+full' "$DOCKERFILE"; then
    fail "schedule — $DOCKERFILE crontab missing a full backup ('pim-cron.sh full')."
fi
if ! grep -Eq 'pim-cron\.sh[[:space:]]+diff' "$DOCKERFILE"; then
    fail "schedule — $DOCKERFILE crontab missing a differential backup ('pim-cron.sh diff')."
fi
if ! grep -Eq 'pim-restore-test\.sh' "$DOCKERFILE"; then
    fail "schedule — $DOCKERFILE crontab missing the weekly restore-test
  ('pim-restore-test.sh'). This is the early-warning probe for silent backup
  corruption (AUD-021); it must stay scheduled."
fi
# The crontab must be installed for the postgres user (the role pgBackRest and
# the schedule run as), not left only for root.
if ! grep -Eq '/etc/crontabs/postgres' "$DOCKERFILE"; then
    fail "schedule — $DOCKERFILE does not write the schedule to /etc/crontabs/postgres."
fi

if [ "$status" -eq 0 ]; then
    echo "lint-backup-cron: backup cron wiring intact (crontab registered for postgres, full/diff/restore-test scheduled). Clean."
fi

exit "$status"
