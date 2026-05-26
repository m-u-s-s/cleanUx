import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 20 },   // ramp up
    { duration: '1m', target: 50 },    // sustained load
    { duration: '30s', target: 100 },  // peak
    { duration: '30s', target: 0 },    // ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],  // 95% of requests under 500ms
    http_req_failed: ['rate<0.01'],     // less than 1% failures
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000/api';

export default function () {
  // Health check
  const health = http.get(`${BASE_URL}/health`);
  check(health, { 'health 200': (r) => r.status === 200 });

  // Search providers (public, most common)
  const search = http.get(`${BASE_URL}/search/providers?trade=cleaning&postal_code=1000`);
  check(search, { 'search 200': (r) => r.status === 200 });

  // Service catalog (public)
  const services = http.get(`${BASE_URL}/v2/pricing/services`);
  check(services, { 'services 200': (r) => r.status === 200 });

  sleep(1);
}
