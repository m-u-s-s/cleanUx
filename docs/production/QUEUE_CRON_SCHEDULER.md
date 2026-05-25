# CleanUx — Queue / Cron / Scheduler

## Crontab (server-level)

Add one line to the server crontab (`crontab -e` as the web user):

```
* * * * * cd /var/www/cleanux && php artisan schedule:run >> /dev/null 2>&1
```

## Complete scheduled task list (Kernel.php)

| Command | Frequency | Description |
|---|---|---|
| `app:send-rendezvous-reminders` | Every 15 min | Client/provider booking reminders |
| `app:prune-read-notifications --days=30` | Daily 02:30 | Prune old read notifications |
| `google-calendar:sync --future-days=30` | Every 15 min | Sync bookings to Google Calendar |
| `finance:sync-documents` | Hourly | Sync financial documents |
| `finance:sync-documents --reminders` | Daily 09:00 | Send unpaid invoice reminders |
| `subscriptions:generate` | Daily | Generate subscription invoices |
| `app:send-smart-rdv-notifications` | Every 15 min | Smart booking notifications |
| `currencies:refresh` | Daily 06:00 | Refresh FX rates (sync) |
| `presence:cleanup` | Every minute | Remove stale presence rows |
| `presence:scan-stale --threshold=5` | Every 2 min | Auto-offline providers with stale heartbeat |
| `surge:recompute` | Every minute | Recompute surge pricing multipliers |
| `gdpr:enforce-retention` | Daily 04:00 | Enforce data retention policies |
| `gdpr:execute-erasure-requests` | Daily 04:30 | Execute pending GDPR erasure requests |
| `ops:check-providers --strict` | Every 30 min | Check provider compliance status |
| `subscriptions:tick --limit=500` | Daily 03:00 | Process subscription billing cycles |
| `accounting:close-previous-month` | Monthly (6th) 04:00 | Close accounting period |
| `fleet:scan-expiring` | Daily 05:00 | Alert on expiring vehicle certs/insurance |
| `payouts:process` | Daily 02:00 | Compute commissions + Stripe Transfers for completed bookings |
| `stripe:reconcile --scope=all --days=1` | Daily 05:30 | Audit Stripe vs DB payment status |
| `PurgeAuditEventsJob` (job) | Daily 03:15 | Purge old audit events per retention policy |
| `marketing:dispatch-steps` (job) | Every 10 min | Execute drip campaign steps |
| `marketing:recompute-segments` (job) | Daily 02:00 | Recompute marketing segments |
| `RefreshFxRatesJob` (job) | Daily 06:15 | Refresh FX rates async |
| `backup:clean` | Daily 01:00 | Clean old backups |
| `backup:run` | Daily 01:30 | Run daily backup |
| `backup:monitor` | Daily 07:00 | Check backup health |
| `app:ops-heartbeat` | Every 5 min | Ops heartbeat ping |
| `app:production-health-check` | Hourly | Production health check (logged) |

## Queue workers (Supervisor)

```ini
[program:cleanux-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cleanux/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/cleanux-worker.log
stopwaitsecs=3600
```

## Configuration recommandée en production

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

