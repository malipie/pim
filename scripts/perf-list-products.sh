#!/usr/bin/env bash
#
# Perf profile orchestrator for ticket 0.0.14 (Sprint 0).
#
# Pipeline:
#   1. Mint a JWT through /api/auth/login (demo tenant by default).
#   2. Seed N synthetic products via `pim:benchmark:bulk-import --keep` so the
#      target collection has at least 1 000 rows. Existing rows are kept.
#   3. Run the k6 load test against /api/products?page=1 (single-origin Caddy).
#   4. Delete the seeded rows and report the run summary.
#
# The benchmark CLI runs in APP_ENV=prod APP_DEBUG=0 because Sprint 0 ticket
# 0.0.13 documented that the dev-mode profiler middleware leaks into long
# imports. The HTTP serving worker (FrankenPHP) keeps whatever APP_ENV the
# stack was brought up with — typically dev — but the load test only reads,
# so its memory profile is bounded by the response size.
#
# Usage:
#   scripts/perf-list-products.sh [--vus 100] [--duration 60s] [--seed 1000]
#                                 [--tenant demo] [--keep-seed]

set -euo pipefail

VUS="${VUS:-100}"
DURATION="${DURATION:-60s}"
SEED_COUNT="${SEED_COUNT:-1000}"
TENANT="${TENANT:-demo}"
KEEP_SEED=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    --vus) VUS="$2"; shift 2 ;;
    --duration) DURATION="$2"; shift 2 ;;
    --seed) SEED_COUNT="$2"; shift 2 ;;
    --tenant) TENANT="$2"; shift 2 ;;
    --keep-seed) KEEP_SEED=true; shift ;;
    -h|--help)
      sed -n '2,/^set -euo/p' "$0" | sed 's/^# \?//'
      exit 0
      ;;
    *) echo "Unknown flag: $1" >&2; exit 2 ;;
  esac
done

ADMIN_EMAIL="admin@${TENANT}.localhost"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-changeme}"
BASE_URL="${BASE_URL:-https://pim.localhost}"

echo "==> Logging in as ${ADMIN_EMAIL}"
TOKEN=$(curl --silent --insecure --fail \
  --request POST "${BASE_URL}/api/auth/login" \
  --header 'content-type: application/json' \
  --data "{\"email\":\"${ADMIN_EMAIL}\",\"password\":\"${ADMIN_PASSWORD}\"}" \
  | python3 -c "import sys, json; print(json.load(sys.stdin)['token'])")

if [[ -z "${TOKEN}" ]]; then
  echo "Failed to obtain JWT — check fixtures and credentials." >&2
  exit 1
fi

SKU_PREFIX=""
echo "==> Seeding ${SEED_COUNT} products for tenant '${TENANT}' (APP_ENV=prod)"
SEED_OUTPUT=$(docker compose exec -T -e APP_ENV=prod -e APP_DEBUG=0 api \
  php bin/console pim:benchmark:bulk-import \
    --count "${SEED_COUNT}" \
    --batch-size 200 \
    --tenant "${TENANT}" \
    --keep)
echo "${SEED_OUTPUT}" | tail -20
SKU_PREFIX=$(echo "${SEED_OUTPUT}" | grep -oE 'SKU prefix "bench-[0-9a-f]+-' | head -1 | sed 's/SKU prefix "//; s/"$//')

if [[ -z "${SKU_PREFIX}" ]]; then
  echo "Could not detect benchmark SKU prefix — cleanup will be skipped." >&2
fi

cleanup_seed() {
  if ! ${KEEP_SEED} && [[ -n "${SKU_PREFIX}" ]]; then
    echo "==> Cleaning up seeded products with prefix '${SKU_PREFIX}'"
    docker compose exec -T -e APP_ENV=prod -e APP_DEBUG=0 api \
      php bin/console doctrine:query:sql \
      "DELETE FROM products WHERE sku LIKE '${SKU_PREFIX}%'" \
      | tail -3 || true
  elif ${KEEP_SEED}; then
    echo "==> --keep-seed set; leaving ${SEED_COUNT} rows behind (prefix '${SKU_PREFIX}')."
  fi
}
trap cleanup_seed EXIT

echo "==> Running k6 load test (vus=${VUS}, duration=${DURATION})"
docker compose --profile perf run --rm \
  -e API_TOKEN="${TOKEN}" \
  -e K6_BASE_URL="${BASE_URL}" \
  -e K6_VUS="${VUS}" \
  -e K6_DURATION="${DURATION}" \
  k6 run /scripts/products-list.js
