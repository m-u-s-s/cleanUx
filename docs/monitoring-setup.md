# Monitoring Setup

## Health Endpoint

The application exposes a health endpoint at `GET /api/health` (no authentication required).

Response shape:
```json
{
  "status": "healthy",
  "checks": {
    "app": true,
    "database": true,
    "cache": true,
    "queue": true,
    "redis": true,
    "stripe": true,
    "reverb": true
  }
}
```

- HTTP 200 → `status: "healthy"` (all hard dependencies pass)
- HTTP 503 → `status: "degraded"` (at least one hard dependency failed)
- `stripe` and `reverb` are soft-fail checks — they do not affect the HTTP status code

The artisan command `php artisan app:production-health-check` runs the same checks from the CLI and is suitable for cron-based monitoring or pre-deploy smoke tests.

---

## UptimeRobot / Pingdom

Configure an HTTP(S) monitor with the following settings:

| Field               | Value                                    |
|---------------------|------------------------------------------|
| URL                 | `https://<your-domain>/api/health`       |
| Method              | GET                                      |
| Check interval      | 1 minute                                 |
| Expected HTTP code  | 200                                      |
| Keyword assert      | `"status":"healthy"` (string match)      |
| Alert contacts      | ops team email + Slack channel           |
| Alert threshold     | 2 consecutive failures before alerting   |

For **Pingdom**, use the "Transaction" check type to assert both the HTTP 200 and the `healthy` keyword in the response body.

---

## Sentry Alert Rules

### 1. Error Rate Spike

Trigger when the per-minute error count increases by more than 200% compared to the previous 24-hour baseline.

```
Conditions:
  - Number of events in 1 hour > 50
  - Percent change in event count > 200% (compared to previous 24h)
Actions:
  - Send email to on-call
  - Post to #alerts Slack channel
  - Priority: High
```

### 2. New Issue Detected

Trigger immediately when Sentry detects an issue that has never been seen before.

```
Conditions:
  - A new issue is created
  - Environment: production
Actions:
  - Send email to dev team
  - Post to #sentry-new Slack channel
  - Priority: Medium
```

### 3. Unresolved Issue Regression

Trigger when a previously resolved issue reappears.

```
Conditions:
  - A resolved issue is seen again
  - Environment: production
Actions:
  - Send email to the original assignee
  - Post to #sentry-regressions Slack channel
  - Priority: High
```

### 4. Performance Degradation — P95 Latency

Trigger when the P95 transaction duration exceeds the threshold.

```
Metric alert (Performance > Transactions):
  - Metric: p95(transaction.duration)
  - Threshold: Warning at 2000ms, Critical at 5000ms
  - Time window: 10 minutes
  - Environment: production
Actions (Critical):
  - PagerDuty or on-call email
  - Post to #alerts Slack channel
Actions (Warning):
  - Post to #perf-degradation Slack channel
```

### 5. Apdex Score Drop

```
Metric alert (Performance > Apdex):
  - Threshold: Warning below 0.85, Critical below 0.70
  - Satisfactory threshold: 300ms
  - Time window: 15 minutes
  - Environment: production
Actions (Critical):
  - PagerDuty
```

---

## Sentry — Required Secrets / Config

Add to `.env.production`:
```
SENTRY_LARAVEL_DSN=https://<key>@o<org>.ingest.sentry.io/<project-id>
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_PROFILES_SAMPLE_RATE=0.05
```

The Sentry SDK is already configured in `config/sentry.php` and bootstrapped
via the existing `App\Exceptions\Handler`. See `docs/sentry-integration.md`
for the full integration guide.

---

## Required GitHub Secrets for CD Pipeline

Add these secrets in `Settings > Secrets and variables > Actions`:

| Secret name     | Description                                           |
|-----------------|-------------------------------------------------------|
| `DEPLOY_HOST`   | Production server hostname or IP                      |
| `DEPLOY_USER`   | SSH username (e.g. `forge`)                           |
| `DEPLOY_KEY`    | Private SSH key (RSA or Ed25519, no passphrase)       |
| `DEPLOY_PATH`   | Absolute path to the app on the server (e.g. `/home/forge/brio.com`) |
