#!/usr/bin/env bash
# AUD-002 (W1-1) — idempotently provision the runtime application role.
#
# The api/worker containers connect as `APP_DB_USER` (default `pim_app`), a
# NON-owner, NOSUPERUSER, NOBYPASSRLS role, so Postgres FORCE ROW LEVEL
# SECURITY is actually enforced on every query they issue — the hard second
# isolation wall behind Doctrine's TenantFilter.
#
# This runs on EVERY database start (backgrounded from start-pim.sh), not just
# first-init, because the standard postgres `docker-entrypoint-initdb.d` hook
# only fires on an empty data directory: a developer who already has a
# `postgres_data` volume would otherwise never get the role. The operations
# here are all idempotent, so re-running on every boot is safe and also keeps
# the password in sync when APP_DB_PASSWORD is rotated.
#
# Table-level GRANTs + RLS policies are NOT done here — they belong to the
# Doctrine migration (run as the owner role), which is the version-controlled
# source of truth and runs after the schema exists. This script only
# guarantees the *role and its password* exist before the api connects.

set -euo pipefail

APP_USER="${APP_DB_USER:-pim_app}"
APP_PASSWORD="${APP_DB_PASSWORD:-}"
DB_NAME="${POSTGRES_DB:-pim}"
# The cluster bootstrap superuser is POSTGRES_USER (`pim`), NOT `postgres` —
# the postgres image only creates the `postgres` ROLE when POSTGRES_USER is
# unset/equal to it. Connect explicitly as POSTGRES_USER over the local socket
# (trust auth inside the container) so the DO block runs with superuser rights.
SUPERUSER="${POSTGRES_USER:-postgres}"
LOG=/var/log/pgbackrest/init-app-role.log
mkdir -p "$(dirname "${LOG}")"
chown postgres:postgres "$(dirname "${LOG}")" || true

log() {
    printf '[%s] pim-init-app-role: %s\n' "$(date -u +%FT%TZ)" "$*" | tee -a "${LOG}"
}

if [ -z "${APP_PASSWORD}" ]; then
    log "APP_DB_PASSWORD is empty — refusing to create a passwordless login role; skipping"
    exit 0
fi

log "waiting for postgres to accept connections"
for _ in $(seq 1 60); do
    if su -s /bin/sh postgres -c "pg_isready -h /var/run/postgresql -p 5432 -d ${DB_NAME}" >/dev/null 2>&1; then
        log "postgres is ready"
        break
    fi
    sleep 2
done

if ! su -s /bin/sh postgres -c "pg_isready -h /var/run/postgresql -p 5432 -d ${DB_NAME}" >/dev/null 2>&1; then
    log "postgres did not become ready within timeout — aborting (api boot retries on its own)"
    exit 0
fi

# Idempotent role provisioning. CREATE ROLE on first run, ALTER ROLE on every
# subsequent run to keep the password + safety attributes in sync.
#
# Injection-safety: the role name and password come from container env. They
# are carried into the server as custom GUCs via set_config(), with the value
# wrapped in $$…$$ dollar-quoting at the heredoc layer, then read back inside
# the DO block with current_setting() and re-escaped through format() %I
# (identifier) / %L (literal) before reaching CREATE/ALTER ROLE — so the value
# can never be parsed as SQL. psql's own `:'var'` substitution is NOT usable
# here because it is not expanded inside `$$…$$` dollar-quoted bodies, which is
# why values travel via GUC instead. (Dev passwords must not themselves contain
# the `$$` sequence; APP_DB_PASSWORD is infra-controlled.) The superuser
# connects over the local unix socket (trust auth in the container) as
# POSTGRES_USER.
log "ensuring role ${APP_USER} exists with NOSUPERUSER/NOBYPASSRLS + synced password"
if su -s /bin/sh postgres -c "psql -v ON_ERROR_STOP=1 --no-psqlrc -U '${SUPERUSER}' -d '${DB_NAME}'" >>"${LOG}" 2>&1 <<SQL
-- Stash the env-provided values as session GUCs via parameterised statements
-- (\$1/\$2 are bound, not interpolated — injection-safe).
SELECT set_config('pim.app_user', \$\$${APP_USER}\$\$, false);
SELECT set_config('pim.app_password', \$\$${APP_PASSWORD}\$\$, false);
SELECT set_config('pim.db_name', \$\$${DB_NAME}\$\$, false);

DO \$do\$
DECLARE
    v_user text := current_setting('pim.app_user');
    v_pass text := current_setting('pim.app_password');
    v_db   text := current_setting('pim.db_name');
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = v_user) THEN
        EXECUTE format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS NOINHERIT PASSWORD %L', v_user, v_pass);
        RAISE NOTICE 'created role %', v_user;
    ELSE
        EXECUTE format('ALTER ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS NOINHERIT PASSWORD %L', v_user, v_pass);
        RAISE NOTICE 'synced role %', v_user;
    END IF;

    -- Allow the role to open a connection to this database (PUBLIC already
    -- carries CONNECT by default, but make it explicit + idempotent). Table
    -- GRANTs + USAGE on sequences + RLS policies are layered by the W1-1
    -- Doctrine migration (owner-run) once the schema exists.
    EXECUTE format('GRANT CONNECT ON DATABASE %I TO %I', v_db, v_user);
END
\$do\$;
SQL
then
    log "role ${APP_USER} provisioned"
else
    log "role provisioning failed — see ${LOG} (api boot will surface the auth error if the role is missing)"
fi
