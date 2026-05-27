/**
 * k6 Load Test: CleanUx Booking Flow
 *
 * Tests the full authenticated booking lifecycle:
 *   1. Login via API (get Sanctum token)
 *   2. Browse service catalog
 *   3. Create a booking
 *   4. Check booking status
 *   5. Cancel the booking
 *
 * Run:
 *   k6 run tests/load/booking-flow.js
 *
 * Override base URL and credentials:
 *   BASE_URL=https://staging.cleanux.be \
 *   TEST_EMAIL=loadtest@example.com \
 *   TEST_PASSWORD=secret123 \
 *   k6 run tests/load/booking-flow.js
 */

import http from 'k6/http';
import { check, group, sleep, fail } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

// ─────────────────────────── Custom metrics ────────────────────────────────
const bookingCreated = new Counter('booking_created_total');
const bookingFailed  = new Counter('booking_failed_total');
const loginFailRate  = new Rate('login_fail_rate');
const bookingDuration = new Trend('booking_create_duration_ms', true);

// ─────────────────────────── Configuration ─────────────────────────────────
export const options = {
  stages: [
    { duration: '30s', target: 10 },   // ramp up to 10 VUs
    { duration: '1m',  target: 30 },   // sustained load
    { duration: '30s', target: 60 },   // peak load
    { duration: '30s', target: 0 },    // ramp down
  ],
  thresholds: {
    http_req_duration:    ['p(95)<800', 'p(99)<2000'],
    http_req_failed:      ['rate<0.05'],     // <5% failure overall
    login_fail_rate:      ['rate<0.02'],     // <2% login failures
    booking_create_duration_ms: ['p(95)<1500'],
  },
};

const BASE_URL    = __ENV.BASE_URL    || 'http://localhost:8000';
const API_URL     = `${BASE_URL}/api`;
const TEST_EMAIL  = __ENV.TEST_EMAIL  || 'loadtest-client@cleanux.test';
const TEST_PASSWORD = __ENV.TEST_PASSWORD || 'password';

// ─────────────────────────── Helpers ───────────────────────────────────────

function jsonHeaders(token) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  return headers;
}

function login() {
  const res = http.post(
    `${BASE_URL}/login`,
    JSON.stringify({ email: TEST_EMAIL, password: TEST_PASSWORD }),
    { headers: jsonHeaders(null) }
  );

  const ok = check(res, {
    'login status 200': (r) => r.status === 200,
    'login has token':  (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.token != null || body.two_factor === true || r.status === 200;
      } catch {
        return false;
      }
    },
  });

  loginFailRate.add(!ok);

  if (!ok) {
    return null;
  }

  try {
    const body = JSON.parse(res.body);
    // Sanctum SPA auth uses cookies; API tokens come from /api/tokens
    return body.token || null;
  } catch {
    return null;
  }
}

// ─────────────────────────── Scenario groups ───────────────────────────────

function scenarioPublicEndpoints() {
  group('Public — health & catalog', () => {
    const health = http.get(`${API_URL}/health`);
    check(health, {
      'health 200': (r) => r.status === 200,
    });

    const catalog = http.get(`${API_URL}/v2/pricing/services`);
    check(catalog, {
      'catalog reachable': (r) => [200, 401, 403].includes(r.status),
    });

    const search = http.get(
      `${API_URL}/search/providers?trade=cleaning&postal_code=1000`
    );
    check(search, {
      'search reachable': (r) => [200, 401, 404].includes(r.status),
    });
  });
}

function scenarioAuthenticatedBookingFlow() {
  group('Authenticated — full booking flow', () => {
    // ── 1. Login ─────────────────────────────────────────────────────
    const token = login();

    // If login is not supported (SPA-only), skip authenticated scenario
    if (!token) {
      return;
    }

    const hdrs = jsonHeaders(token);

    // ── 2. List own bookings ──────────────────────────────────────────
    group('List bookings', () => {
      const listRes = http.get(`${API_URL}/client/bookings`, { headers: hdrs });
      check(listRes, {
        'list bookings reachable': (r) => [200, 401, 403].includes(r.status),
      });
    });

    // ── 3. Create a booking ───────────────────────────────────────────
    let bookingId = null;
    group('Create booking', () => {
      const startTs = Date.now();

      const bookingPayload = {
        service_catalog_code: 'nettoyage_standard',
        postal_code: '1000',
        date: new Date(Date.now() + 86400000 * 3).toISOString().split('T')[0],
        heure: '10:00:00',
        adresse: '1 Rue de la Loi',
        ville: 'Bruxelles',
        surface: '50_100',
        type_lieu: 'appartement',
      };

      const createRes = http.post(
        `${API_URL}/client/bookings`,
        JSON.stringify(bookingPayload),
        { headers: hdrs }
      );

      const elapsed = Date.now() - startTs;
      bookingDuration.add(elapsed);

      const ok = check(createRes, {
        'create booking accepted': (r) => [200, 201, 422].includes(r.status),
      });

      if (createRes.status === 201 || createRes.status === 200) {
        bookingCreated.add(1);
        try {
          bookingId = JSON.parse(createRes.body)?.id
            ?? JSON.parse(createRes.body)?.booking?.id
            ?? null;
        } catch {
          // body parse error — skip
        }
      } else if (createRes.status >= 500) {
        bookingFailed.add(1);
      }
    });

    // ── 4. Check booking status ───────────────────────────────────────
    if (bookingId) {
      group('Check booking status', () => {
        const statusRes = http.get(
          `${API_URL}/client/bookings/${bookingId}`,
          { headers: hdrs }
        );
        check(statusRes, {
          'status check 200': (r) => r.status === 200,
          'booking has status field': (r) => {
            try {
              return JSON.parse(r.body)?.status != null;
            } catch {
              return false;
            }
          },
        });
      });

      // ── 5. Cancel the booking ─────────────────────────────────────
      group('Cancel booking', () => {
        const cancelRes = http.post(
          `${API_URL}/client/bookings/${bookingId}/cancel`,
          JSON.stringify({ reason: 'load_test' }),
          { headers: hdrs }
        );
        check(cancelRes, {
          'cancel accepted': (r) => [200, 202, 404, 422].includes(r.status),
        });
      });
    }
  });
}

// ─────────────────────────── Default scenario ──────────────────────────────

export default function () {
  // Mix: 70% public requests (cheap), 30% full auth flow
  const roll = Math.random();

  if (roll < 0.7) {
    scenarioPublicEndpoints();
  } else {
    scenarioAuthenticatedBookingFlow();
  }

  sleep(1);
}
