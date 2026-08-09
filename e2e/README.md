# E2E — Playwright (par rôle)

Smoke tests navigateur qui vérifient que chaque rôle (admin, client, prestataire,
entreprise cliente, entreprise prestataire) se connecte et atteint son espace.

## Lancer en local

L'app doit être **servie** avec une base contenant les comptes QA.

```bash
# 1. Base + comptes QA (mot de passe commun : config/brio.php → seed.password, 12345678 par défaut)
php artisan migrate --seed --force            # ou: db:seed --class="Database\\Seeders\\QaAccountsSeeder"

# 2. Servir l'app
php artisan serve --host=127.0.0.1 --port=8000

# 3. Dans un autre terminal : navigateur + tests
npm run e2e:install      # une fois — installe Chromium
npm run e2e              # lance la suite (E2E_BASE_URL défaut http://127.0.0.1:8000)
npm run e2e:ui           # mode UI interactif
```

Cibler une autre URL : `E2E_BASE_URL=https://staging... npm run e2e`.

## CI

Le job **`e2e`** de `.github/workflows/ci.yml` boote l'app (SQLite + `QaAccountsSeeder`),
installe Chromium et lance la suite. Non-bloquant au départ (signal d'abord) ; à
passer en bloquant une fois stable.

## Comptes (QaAccountsSeeder)

| Rôle | Email |
|------|-------|
| admin | admin@brio.test |
| client | lemoine.gabrielle@example.net |
| provider | bsanchez@example.org |
| entreprise (cliente) | dominique.monnier@example.org |
| provider_company | qa-provider-company@brio.test |
