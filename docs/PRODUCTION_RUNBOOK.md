# CleanUx — runbook production

## 1. Déploiement initial
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan app:seed-platform production --force
```

## 2. Vérification santé immédiate
```bash
php artisan app:production-health-check --strict
php artisan app:go-live-readiness-report --strict
php artisan app:ops-heartbeat --json
php artisan app:consolidation-final-check
php artisan app:audit-platform-integrity --fail-on-issues
php artisan schedule:list
```

## 3. Scheduler attendu
- `app:send-rendezvous-reminders` : toutes les 15 min
- `app:prune-read-notifications --days=30` : 02:30
- `google-calendar:sync --future-days=30` : toutes les 15 min
- `finance:sync-documents` : toutes les heures
- `finance:sync-documents --reminders` : 09:00
- `app:ops-heartbeat` : toutes les 5 min
- `app:production-health-check` : toutes les heures

## 4. Commandes de contrôle

### Health / readiness
```bash
php artisan app:production-health-check --strict
php artisan app:go-live-readiness-report --strict
php artisan app:ops-heartbeat --json
```

### Seed / intégrité
```bash
php artisan app:prepare-fresh-seed --strict
php artisan app:audit-platform-integrity --fail-on-issues
```

### Intégrations
```bash
php artisan google-calendar:sync --future-days=30
php artisan finance:sync-documents --reminders
php artisan app:send-rendezvous-reminders
```

## 5. Incident standard

### Worker queue bloqué
- vérifier Supervisor/systemd
- vérifier backlog `jobs`
- vérifier `failed_jobs`
- relancer le worker si nécessaire

### Heartbeat stale
- vérifier `schedule:run`
- vérifier `app:ops-heartbeat`
- vérifier cache/disk de heartbeat

### Health check en erreur
- relancer `app:production-health-check --strict`
- corriger d’abord les `ERROR`
- traiter ensuite les `WARNING`

## 6. Monitoring minimal
- heartbeat JSON monitorable
- health check exploitable en CLI
- rapport go-live exploitable avant cutover
- log `storage/logs/production-health.log`

## 7. Sauvegardes
Prévoir au minimum :
- dump DB régulier
- sauvegarde du stockage public
- rotation et externalisation
- test de restauration
