# Operations Runbook

## Daily
- `php artisan payouts:process` — process completed booking payouts
- `php artisan presence:scan-stale` — auto-offline stale providers

## Deploy
1. `php artisan deploy:check` — verify all production requirements
2. `php artisan migrate --force` — run pending migrations
3. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
4. `php artisan queue:restart` — restart queue workers

## Monitoring
- `GET /api/health` — returns `{status: healthy|degraded, checks: {...}}`
- `storage/logs/slow-queries.log` — queries > 500ms
- Sentry dashboard — crash reports + performance traces

## Emergency
- Kill all queue workers: `php artisan queue:restart`
- Clear all caches: `php artisan cache:clear && php artisan config:clear`
- Rollback last migration: `php artisan migrate:rollback --step=1`
