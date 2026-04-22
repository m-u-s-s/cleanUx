# Installation locale et workflow développeur

## 1. Prérequis
- PHP 8.2+
- Composer
- Node.js 20+
- MySQL 8+
- extensions PHP standard Laravel (`mbstring`, `xml`, `dom`, `pdo_mysql`, etc.)

## 2. Installation projet
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configurer ensuite :
- `APP_URL`
- `DB_*`
- `MAIL_*`
- éventuellement Stripe / Google Calendar selon les modules à tester

## 3. Base de données

### Seed rapide démo
```bash
php artisan app:seed-platform demo --fresh
```

### Référentiel uniquement
```bash
php artisan app:seed-platform reference --fresh
```

### Vérification post-seed
```bash
php artisan app:prepare-fresh-seed --strict
php artisan app:audit-platform-integrity --fail-on-issues
```

## 4. Front et application
```bash
npm run dev
php artisan serve
```

## 5. Tests

### Suite complète
```bash
php artisan test
```

### Sous-ensembles utiles
```bash
php artisan test --filter=ZoneAware
php artisan test --filter=Recurring
php artisan test --filter=Finance
php artisan test --filter=AdminSecurity
```

## 6. Commandes utiles au quotidien

### Booking / exploitation
```bash
php artisan app:send-rendezvous-reminders
php artisan google-calendar:sync --future-days=30
php artisan finance:sync-documents
```

### Audit / maintenance
```bash
php artisan app:audit-platform-integrity
php artisan app:production-health-check
php artisan app:ops-heartbeat --json
php artisan app:cleanup-report
```

### Vérifications Livewire
```bash
php artisan livewire:verify
php artisan livewire:missing-views
php artisan livewire:unused
php artisan livewire:unused-includes
```

## 7. Conseils de travail
- garder les tests verts avant toute livraison,
- éviter de réintroduire des dépendances directes aux champs legacy quand une relation structurée existe,
- utiliser les seed profiles plutôt qu'un `db:seed` flou,
- ne pas committer `.env`, `vendor/` ou `node_modules/` dans un zip de livraison source.
