# CleanUx

Plateforme Laravel/Livewire de gestion opérationnelle pour services terrain : réservation, couverture par zones, multi-rôles, multi-sites entreprise, finance, rappels, qualité et pilotage admin.

Le projet n'est plus un simple site de prise de rendez-vous. Il s'agit désormais d'un noyau de plateforme structuré pour :
- gérer une couverture par zones en Belgique,
- affecter des employés selon la zone et la disponibilité,
- proposer des parcours distincts admin / client / employé,
- gérer des comptes entreprise multi-sites,
- produire des devis / factures / exports,
- synchroniser Google Calendar,
- contrôler l'intégrité métier via audit et seed profiles.

## Stack technique

- **Backend** : Laravel, Eloquent, Policies, Notifications, Scheduler, Jobs
- **UI** : Livewire, Blade, Tailwind, Alpine.js
- **Auth** : Jetstream / Fortify / Sanctum
- **Build front** : Vite
- **Exports PDF** : barryvdh/laravel-dompdf
- **Paiement premium** : Laravel Cashier / Stripe
- **Calendrier** : Google Calendar sync + FullCalendar côté UI

## Ce que couvre la plateforme

### 1. Réservation zone-aware
- résolution de zone selon code postal / ville / site entreprise,
- vérification de la couverture,
- validation des règles de zone et de service,
- affectation employé compatible,
- snapshots de zone et pricing sur le rendez-vous,
- gestion des occurrences récurrentes.

### 2. Portails par rôle
- **Admin** : dashboard, zones, entreprises, finance, analytics, modules, qualité, audit logs
- **Client** : nouveau rendez-vous, historique, profil, finance, litiges, favoris employés
- **Employé** : planning, missions, disponibilités, Google Agenda, incidents, historique

### 3. Gestion entreprise
- comptes organisation,
- sites multiples,
- utilisateurs rattachés,
- priorités de zones,
- règles contractuelles,
- workflow avancé de réservation enterprise.

### 4. Pilotage et exploitation
- commandes d'audit,
- seed profiles (`demo`, `reference`, `production`),
- health checks production,
- heartbeat supervision,
- rappels automatiques,
- sync finance,
- sync Google Calendar.

## Structure du projet

### Dossiers importants
- `app/Models` : entités métier (`User`, `RendezVous`, `ServiceZone`, `OrganizationAccount`, etc.)
- `app/Services` : logique métier (booking, finance, calendrier, notifications, enterprise)
- `app/Livewire` : écrans interactifs par rôle
- `app/Console/Commands` : audit, seed, ops, maintenance
- `database/migrations` : structure de la plateforme
- `database/seeders` : référentiel, bootstrap démo, production
- `resources/views` : Blade, exports, PDF, composants visuels
- `routes/web.php` : portail public + admin/client/employé
- `docs/` : documentation technique et exploitation

## Installation locale

### Prérequis
- PHP 8.2+
- Composer
- Node.js 20+
- MySQL 8+ ou MariaDB compatible

### Installation
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run dev
php artisan serve
```

### Démarrage recommandé en environnement de travail
```bash
php artisan optimize:clear
php artisan app:seed-platform demo --fresh
php artisan app:prepare-fresh-seed --strict
php artisan app:audit-platform-integrity --fail-on-issues
php artisan test
```

## Profils de seed

Le projet gère trois profils :
- `demo` : référentiel + données démo cohérentes
- `reference` : référentiel seulement
- `production` : bootstrap minimal sans comptes démo

Exemples :
```bash
php artisan app:seed-platform demo --fresh
php artisan app:seed-platform reference --fresh
php artisan app:seed-platform production --fresh --force
```

Le profil actif est piloté par `config/cleanux.php` et les variables :
- `CLEANUX_SEED_PROFILE`
- `CLEANUX_SEED_DEFAULT_PROFILE`

## Commandes utiles

### Intégrité / audit
```bash
php artisan app:prepare-fresh-seed --strict
php artisan app:audit-platform-integrity --fail-on-issues
php artisan app:production-health-check --strict
php artisan app:ops-heartbeat --json
```

### Booking / exploitation
```bash
php artisan app:send-rendezvous-reminders
php artisan google-calendar:sync --future-days=30
php artisan finance:sync-documents
php artisan finance:sync-documents --reminders
```

### Nettoyage / maintenance
```bash
php artisan livewire:verify
php artisan livewire:missing-views
php artisan livewire:unused
php artisan livewire:unused-includes
php artisan app:cleanup-report
```

## Qualité projet

État actuellement visé :
- `migrate:fresh --seed` doit passer,
- la suite de tests doit rester verte,
- l'audit d'intégrité ne doit pas remonter d'anomalie bloquante,
- les snapshots de réservation/finance doivent rester cohérents,
- les zones et règles doivent être liées à des références structurées.

## Docs à lire ensuite

- `docs/ARCHITECTURE_OVERVIEW.md`
- `docs/LOCAL_SETUP.md`
- `docs/SEED_AND_AUDIT_GUIDE.md`
- `docs/BOOKING_ZONE_AWARE.md`
- `docs/ROLE_PORTALS_AND_PERMISSIONS.md`
- `docs/DEPLOYMENT_CHECKLIST.md`
- `docs/PRODUCTION_RUNBOOK.md`

## Règles de livraison recommandées

Ne pas livrer un zip source avec :
- `.env`
- `vendor/`
- `node_modules/`

Préférer :
- `.env.example`
- `.env.production.example`
- le code source,
- les migrations,
- les seeders,
- les tests,
- la documentation.
