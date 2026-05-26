# Load Tests

## Prerequisites

Install k6: https://k6.io/docs/get-started/installation/

## Run

```bash
# Against local
k6 run tests/load/booking-flow.js

# Against staging
k6 run -e BASE_URL=https://staging.cleanux.be/api tests/load/booking-flow.js
```

## Thresholds

- p95 response time < 500ms
- Error rate < 1%
