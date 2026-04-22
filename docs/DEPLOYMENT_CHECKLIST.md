# CleanUx — checklist de déploiement production

## 1. Préparation du code
- [ ] archive source sans `.env`
- [ ] archive source sans `vendor/`
- [ ] archive source sans `node_modules/`
- [ ] `.env.example` à jour
- [ ] `.env.production.example` à jour
- [ ] tests verts avant build

## 2. Infrastructure minimale
- [ ] PHP 8.2+
- [ ] MySQL 8+
- [ ] Node.js 20+
- [ ] Supervisor ou systemd pour les workers
- [ ] cron ou timer systemd actif
- [ ] HTTPS et reverse proxy correctement configurés

## 3. Variables d'environnement
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://...`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `QUEUE_CONNECTION=database` ou `redis`
- [ ] `SESSION_DRIVER=database` ou `redis`
- [ ] `CACHE_DRIVER=redis` ou `database/file` selon infra
- [ ] `OPS_HEARTBEAT_ENABLED=true`
- [ ] `OPS_HEARTBEAT_MAX_AGE_SECONDS=900`
- [ ] `OPS_MONITORING_NOTIFY_EMAIL=...`
- [ ] `OPS_BACKUP_ENABLED=true`
- [ ] `CLEANUX_SEED_DEFAULT_PROFILE=production`

## 4. Installation serveur
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

## 5. Bootstrap recommandé
```bash
php artisan app:seed-platform production --force
php artisan app:production-health-check --strict
php artisan app:go-live-readiness-report --strict
php artisan app:audit-platform-integrity --fail-on-issues
```

## 6. Scheduler
Cron :
```bash
* * * * * php /chemin/vers/projet/artisan schedule:run >> /dev/null 2>&1
```

Ou timer systemd : voir `deploy/systemd/`.

## 7. Worker queue
Voir `deploy/supervisor/cleanux-worker.conf.example` ou `deploy/systemd/cleanux-queue.service.example`.

## 8. Vérifications après déploiement
```bash
php artisan route:list
php artisan schedule:list
php artisan app:production-health-check --strict
php artisan app:go-live-readiness-report --strict
php artisan app:ops-heartbeat --json
php artisan app:audit-platform-integrity --fail-on-issues
```

## 9. Sécurité et exploitation
- [ ] rotation des logs
- [ ] sauvegardes base + fichiers
- [ ] contrôle d'accès admin
- [ ] permissions correctes sur `storage/` et `bootstrap/cache/`
- [ ] monitoring heartbeat et health check
- [ ] procédure de rollback documentée
