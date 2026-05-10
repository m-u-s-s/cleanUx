# CleanUx

CleanUx est une plateforme opérationnelle multi-services construite avec Laravel, Livewire et Jetstream.
Le projet sépare désormais clairement :

- **RendezVous** : réservation, planification, snapshots et finance
- **Mission** : exécution terrain, tracking, checklist, incidents, qualité et rapport
- **Admin / Client / Employé** : portails dédiés
- **Géo / zones / entreprises / sites** : moteur de couverture et routing
- **Ops / prod** : heartbeat, health check, audit, seed profiles, readiness

## Stack
- PHP 8.5 obligatoire
- Laravel 10
- Livewire 3
- Jetstream
- Tailwind + Vite
- FullCalendar + ApexCharts

## Commandes utiles
```bash
php artisan test
php artisan app:audit-platform-integrity --fail-on-issues
php artisan app:consolidation-final-check
php artisan app:production-health-check --strict
php artisan app:go-live-readiness-report --strict
php artisan app:ops-heartbeat --json
```

## Mise en ligne
Consulter en priorité :
- `docs/DEPLOYMENT_CHECKLIST.md`
- `docs/PRODUCTION_RUNBOOK.md`
- `docs/GO_LIVE_CHECKLIST.md`
- `.env.production.example`
- `deploy/supervisor/` et `deploy/systemd/`

## Seed / profils
```bash
php artisan app:seed-platform reference --force
php artisan app:seed-platform production --force
php artisan app:prepare-fresh-seed --strict
```

## Checks de stabilisation
- `app:consolidation-final-check` : dette métier restante (littéraux rôles / statuts / priorités)
- `app:production-health-check` : santé prod (queue, storage, mail, heartbeat, backups, HTTPS, tables infra)
- `app:go-live-readiness-report` : synthèse finale avant cutover
# cleanUx
