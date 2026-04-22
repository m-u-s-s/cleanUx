# CleanUx — go-live checklist

## Avant le cutover
```bash
php artisan test
php artisan app:consolidation-final-check
php artisan app:audit-platform-integrity --fail-on-issues
php artisan app:production-health-check --strict
php artisan app:go-live-readiness-report --strict
```

## Go / no-go
Le cutover est **GO** si :
- tous les tests critiques sont verts
- `production-health-check --strict` retourne 0
- `go-live-readiness-report --strict` retourne 0
- aucune commande scheduler attendue n'est absente
- backlog queue et failed jobs sont sous les seuils fixés
- heartbeat est récent

## Contrôles après mise en ligne
- [ ] login admin/client/employé OK
- [ ] réservation publique OK
- [ ] page admin pays OK
- [ ] workspace mission employé OK
- [ ] suivi mission client OK
- [ ] scheduler actif
- [ ] worker queue actif
- [ ] heartbeat écrit
- [ ] mails métier sortent
- [ ] sync Google Calendar OK
- [ ] finance sync OK
