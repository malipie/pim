// k6 load test for ticket 0.0.14 (Sprint 0).
//
// Hits GET /api/products?page=1 from N concurrent virtual users for D seconds
// against the same single-origin Caddy that serves the stack. The threshold
// p95 < 200 ms is the Sprint 0 gate criterion.
//
// Required env vars (passed by scripts/perf-list-products.sh):
//   API_TOKEN         — JWT bearer obtained via /api/auth/login
//   K6_BASE_URL       — defaults to https://pim.localhost
//   K6_VUS            — concurrent virtual users (default 100)
//   K6_DURATION       — load duration (default 60s)

import { check, group } from 'k6';
import http from 'k6/http';

const baseUrl = __ENV.K6_BASE_URL || 'https://pim.localhost';
const token = __ENV.API_TOKEN;

if (!token) {
  throw new Error('API_TOKEN env var is required (mint via POST /api/auth/login).');
}

export const options = {
  // Self-signed Caddy local CA cert — accept inside the perf profile only.
  insecureSkipTLSVerify: true,
  vus: parseInt(__ENV.K6_VUS || '100', 10),
  duration: __ENV.K6_DURATION || '60s',
  thresholds: {
    // Sprint 0 ticket 0.0.14 gate: p95 < 200 ms on a 1 000-product collection.
    http_req_duration: ['p(95)<200', 'p(99)<400'],
    http_req_failed: ['rate<0.01'],
  },
};

const headers = {
  Authorization: `Bearer ${token}`,
  Accept: 'application/ld+json',
};

export default function () {
  group('GET /api/products?page=1', () => {
    const res = http.get(`${baseUrl}/api/products?page=1`, { headers });
    check(res, {
      'status is 200': (r) => r.status === 200,
      'body is JSON-LD collection': (r) =>
        typeof r.body === 'string' && r.body.includes('"@type":"Collection"'),
    });
  });
}
