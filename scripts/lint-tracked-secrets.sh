#!/usr/bin/env bash
# AUD-005 / AUD-010 — guard-rail against real secrets committed to VCS.
#
# Symfony's dotenv layering commits a handful of `.env*` files on purpose:
#   - apps/api/.env       base defaults (sensitive keys MUST be empty/placeholder)
#   - apps/api/.env.test  test-env template (fake low-entropy values only)
# Everything else (`.env.dev`, `.env.local`, `.env.prod`, …) carries real,
# environment-specific secrets and must never be tracked. AUD-005 found a
# real 32-hex `APP_SECRET` committed in `apps/api/.env.dev`; AUD-010 found
# the `.gitignore` gap that let it slip in (`.env` matched the file `.env`
# by name but never `.env.dev`).
#
# This script fails the build when EITHER hole reopens:
#
#   (A) a non-template `.env.<env>` file is git-tracked at all, or
#   (B) ANY tracked `.env*` file (including the sanctioned `.env` / `.env.test`
#       templates) contains a high-entropy value on a sensitive key —
#       i.e. a real secret rather than a placeholder.
#
# It is intentionally allowed to be noisy: a green run means no real secret
# is reachable through `git ls-files`. Run from the repo root.
#
# Marker for a deliberately-committed template value (must be an obvious
# placeholder, e.g. `__CHANGE_ME__`, `ChangeMe…`, `$ecretf0rt3st`): nothing
# special is required — placeholders are recognised by their low entropy /
# known sentinel words below, so a real key cannot masquerade as one.

set -eu

ROOT="${ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "$ROOT"

# Sensitive keys whose VALUE must never be a real secret in a tracked file.
# `*_DSN` is intentionally NOT here: a DSN is only sensitive when it embeds
# `user:password@` credentials, which is checked separately for every value.
SENSITIVE_KEYS='APP_SECRET|.*PASSPHRASE|.*_KEY|.*_KEY_[A-Z0-9]+|.*SECRET|.*PASSWORD|.*TOKEN'

# Templates that ARE allowed to be tracked (Symfony convention + dist/examples).
# A file matching this list is still scanned for real secrets under rule (B),
# but is exempt from rule (A) (tracked-at-all).
is_template() {
    case "$1" in
        *.dist | *.example | *.template | *.sample) return 0 ;;
        */.env | .env | */.env.test | .env.test) return 0 ;;
        *) return 1 ;;
    esac
}

# A value is a "placeholder" (safe to commit) when it is empty, a Symfony
# container reference (`%env(...)%`, `%kernel...%`), or contains a sentinel
# change-me word. Anything else that is long + high-entropy is a real secret.
PLACEHOLDER_RE='(^$|^%|change[_ -]?me|changethis|please[_ -]?change|__[A-Z_]+__|placeholder|example|secret-for|ecretf0rt3st|!ChangeMe!|!ChangeThis|minioadmin|guest:guest|app:app|app:!Change|null://|ChangeMercureKey|masterKeyPlease|ci-mercure|a{32,})'

# strip one layer of surrounding single/double quotes
unquote() {
    local v="$1"
    v="${v%\"}"; v="${v#\"}"
    v="${v%\'}"; v="${v#\'}"
    printf '%s' "$v"
}

# A DSN/URL value leaks a secret when it embeds non-placeholder
# `scheme://user:password@host` credentials.
dsn_has_credentials() {
    local val; val="$(unquote "$1")"
    # capture the user:pass@ portion if present
    printf '%s' "$val" | grep -Eq '://[^/@[:space:]]+:[^/@[:space:]]+@' || return 1
    # placeholder creds (app:app, app:!ChangeMe!, guest:guest …) are safe
    if printf '%s' "$val" | grep -Eiq "$PLACEHOLDER_RE"; then
        return 1
    fi
    return 0
}

# Heuristic for a real secret value on a sensitive key: a random-looking
# base64/hex token of meaningful length that is not a placeholder. We do
# NOT flag short or whitespace-containing values to avoid false positives
# on human-readable defaults.
looks_like_secret() {
    local val; val="$(unquote "$1")"
    [ -z "$val" ] && return 1
    # placeholder / container ref / sentinel → safe
    if printf '%s' "$val" | grep -Eiq "$PLACEHOLDER_RE"; then
        return 1
    fi
    local len=${#val}
    # base64/hex token of meaningful length → real secret
    if [ "$len" -ge 24 ] && printf '%s' "$val" | grep -Eq '^[A-Za-z0-9+/=_-]+$'; then
        return 0
    fi
    return 1
}

tracked_env_files=$(git ls-files | grep -E '(^|/)\.env([.][^/]*)?$' || true)

if [ -z "$tracked_env_files" ]; then
    echo "lint-tracked-secrets: no tracked .env files — clean."
    exit 0
fi

violations_a=""   # rule A: non-template env file tracked
violations_b=""   # rule B: real secret inside a tracked file

for f in $tracked_env_files; do
    # Rule (A): a non-template environment file must not be tracked at all.
    if ! is_template "$f"; then
        violations_a="$violations_a\n  $f"
    fi

    # Rule (B): scan key=value lines for real secrets (templates included).
    while IFS= read -r line; do
        # skip comments / blanks
        case "$line" in ''|\#*) continue ;; esac
        key=${line%%=*}
        val=${line#*=}
        # (B1) sensitive key carrying a high-entropy token
        if printf '%s' "$key" | grep -Eq "^(${SENSITIVE_KEYS})$" && looks_like_secret "$val"; then
            violations_b="$violations_b\n  $f: ${key}=<real-secret token, ${#val} chars>"
            continue
        fi
        # (B2) any value embedding real user:password@ DSN credentials
        if dsn_has_credentials "$val"; then
            violations_b="$violations_b\n  $f: ${key}=<DSN with embedded credentials>"
        fi
    done < "$f"
done

status=0

if [ -n "$violations_a" ]; then
    echo "lint-tracked-secrets: non-template env file(s) are git-tracked (AUD-010):" >&2
    printf "$violations_a\n" >&2
    cat >&2 <<'TXT'

Only `.env`, `.env.test`, and `*.dist`/`*.example` templates may be tracked.
Untrack environment-specific files (keeps the working copy on disk):

  git rm --cached <file>

and ensure `.gitignore` ignores them (`git check-ignore -v <file>` must match).
TXT
    status=1
fi

if [ -n "$violations_b" ]; then
    echo "lint-tracked-secrets: real secret value(s) found in tracked env file(s) (AUD-005):" >&2
    printf "$violations_b\n" >&2
    cat >&2 <<'TXT'

Tracked env files (including the committed `.env` / `.env.test` templates)
must only contain EMPTY or obvious placeholder values for sensitive keys
(APP_SECRET, *_PASSPHRASE, *_SECRET, *_KEY, *_PASSWORD, *_TOKEN, *_DSN).
Move real values into an untracked `.env.local` / `.env.<env>` and rotate
the leaked secret.
TXT
    status=1
fi

if [ "$status" -eq 0 ]; then
    echo "lint-tracked-secrets: $(printf '%s\n' "$tracked_env_files" | wc -l | tr -d ' ') tracked .env file(s); all template-only, no real secrets. Clean."
fi

exit "$status"
