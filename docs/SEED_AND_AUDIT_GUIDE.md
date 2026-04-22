# Seed, audit et readiness

## 1. Objectif

Le projet distingue clairement :
- le **référentiel**,
- le **bootstrap démo**,
- le **bootstrap production**.

Cela évite de mélanger données de démonstration et base exploitable.

## 2. Profils disponibles

### `demo`
Charge :
- référentiel,
- utilisateurs démo,
- disponibilités,
- rendez-vous,
- feedbacks,
- limites journalières,
- statuts réalistes.

### `reference`
Charge uniquement :
- géographie,
- services,
- modules,
- zones,
- paramètres cœur.

### `production`
Charge :
- le référentiel,
- le bootstrap minimal de production,
- sans comptes démo ni données de démonstration.

## 3. Commande recommandée

```bash
php artisan app:seed-platform demo --fresh
php artisan app:seed-platform reference --fresh
php artisan app:seed-platform production --fresh --force
```

## 4. Variables de configuration

Dans `config/cleanux.php` :
- `cleanux.seed.profile`
- `cleanux.seed.default_profile`
- `cleanux.seed.allowed_profiles`

Variables associées :
- `CLEANUX_SEED_PROFILE`
- `CLEANUX_SEED_DEFAULT_PROFILE`

## 5. Vérification seed

### Readiness seed
```bash
php artisan app:prepare-fresh-seed --strict
```

Cette commande vérifie notamment :
- présence du référentiel,
- cohérence des comptes,
- affectations employés/zones,
- présence des références structurées,
- snapshots booking,
- duplications critiques (email, slug, TVA, booking reference).

### Audit d'intégrité
```bash
php artisan app:audit-platform-integrity --fail-on-issues
```

Cette commande contrôle notamment :
- références orphelines,
- rendez-vous sans structure,
- règles de zones incohérentes,
- sites entreprise mal reliés,
- données démo hors profil démo.

## 6. Workflow recommandé avant livraison
```bash
php artisan optimize:clear
php artisan app:seed-platform demo --fresh
php artisan app:prepare-fresh-seed --strict
php artisan app:audit-platform-integrity --fail-on-issues
php artisan app:consolidation-final-check
php artisan test
```

## 7. Cas production

Avant un bootstrap production :
- vérifier `APP_ENV=production`,
- ne pas injecter de données démo,
- garder un `.env.production.example` maintenu,
- exécuter ensuite `app:production-health-check --strict`.


## 8. Audit final de consolidation
```bash
php artisan app:consolidation-final-check
```

Cette commande ne lit pas la base ; elle scanne le code pour repérer les littéraux métier et les marqueurs de dette technique encore visibles.
