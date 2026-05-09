# CleanUx — Correctifs techniques mai 2026

Ce patch corrige **7 bombes techniques** identifiées lors d'un audit ciblé, plus 1 bug critique découvert pendant l'application des fixes (relation `$mission->booking` cassée).

Chaque fix est minimal et défensif. Aucune migration n'est ajoutée. Aucun changement de signature publique.

---

## Liste des fixes

### Fix #1 — Webhook Stripe Connect routé

**Bug** : `app/Http/Controllers/Webhooks/StripeConnectWebhookController.php` (200+ lignes, signature, handlers pour 6 events) existait mais **aucune route ne pointait dessus**. Tous les events Connect (`account.updated`, `payout.paid`, `payout.failed`, `charge.refunded`, `payment_intent.succeeded`, `payment_intent.payment_failed`) étaient ignorés. Les `ProviderPayout` restaient éternellement en `pending`.

**Fichiers modifiés** :
- `routes/public.php` → ajout `POST /webhooks/stripe-connect`
- `app/Http/Middleware/VerifyCsrfToken.php` → ajout de `webhooks/stripe-connect` à `$except`

**À faire après le déploiement** :
1. Configurer `STRIPE_CONNECT_WEBHOOK_SECRET` dans `.env` prod
2. Dans le Stripe Dashboard → Developers → Webhooks, ajouter l'endpoint `https://yourdomain/webhooks/stripe-connect` avec les events listés ci-dessus

---

### Fix #2 — `lead_provider_user_id` fillable + relation `booking()` + harmonisation channels

**Bug A — fillable manquant** : `app/Models/Mission.php` n'avait pas `lead_provider_user_id` dans `$fillable`. `MissionDispatchService::accept()` faisait `$mission->update(['lead_provider_user_id' => ...])` → mass assignment ignorait silencieusement → la colonne restait NULL → le prestataire ne voyait jamais sa mission acceptée dans `/api/provider/missions/active`.

**Bug B — relation manquante** : 8+ endroits (Phase 11/12/13) utilisaient `$mission->booking` mais la relation Eloquent s'appelait `rendezVous()` (vestige de l'ancienne nomenclature française). Donc `$mission->booking` retournait `null` partout — `MissionDispatchService::dispatchToNextProvider()`, `EtaService::computeForMission()`, `StripeConnectPaymentService::captureMissionPayment()`, et plusieurs controllers étaient **silencieusement cassés en prod**.

**Bug C — autorisation broadcast** : `routes/channels.php` ne checkait que `lead_employee_id` pour autoriser un user sur le channel mission. Un prestataire qui acceptait via API obtenait `lead_provider_user_id` mais pas `lead_employee_id` → broadcast Reverb refusé silencieusement → tracking GPS et notifications temps-réel cassés côté client.

**Fichiers modifiés** :
- `app/Models/Mission.php`
  - Ajout `lead_provider_user_id` dans `$fillable`
  - Nouvelle relation `booking()` (alias de `rendezVous()` sur la même FK `rendez_vous_id`)
  - Nouvelle relation `leadProvider()` (sur `lead_provider_user_id`)
- `routes/channels.php` → mission channel auth accepte maintenant les **deux** colonnes lead
- `app/Services/Dispatch/MissionDispatchService.php::accept()` → écrit dans `lead_provider_user_id` ET `lead_employee_id` pour compat full-stack

---

### Fix #3 — Layout `Trades.php`

**Bug** : `app/Livewire/Admin/Trades.php` déclarait `#[Layout('layouts.admin')]` mais ce layout n'existe pas. La page `/admin/trades` crashait au premier accès avec `View [layouts.admin] not found`.

**Fichier modifié** :
- `app/Livewire/Admin/Trades.php` → `#[Layout('layouts.app')]` (convention historique du repo, alignée avec les autres pages admin)

---

### Fix #4 — `TradeSeeder` dans la chaîne de seeding

**Bug** : `TradeSeeder` et `ServiceCatalogTradeBackfillSeeder` n'étaient appelés **nulle part** (ni dans `DatabaseSeeder`, ni dans `ReferencePlatformSeeder`, ni dans aucun profil demo/reference/production). Sur `php artisan migrate:fresh --seed`, **aucun trade n'était créé**, et donc aucun service ne pouvait être rattaché à un métier.

**Fichier modifié** :
- `database/seeders/ReferencePlatformSeeder.php` → ajout de `TradeSeeder` (avant `ServiceCatalogSeeder`) et `ServiceCatalogTradeBackfillSeeder` (après)

**Effet collatéral positif** : sur tout nouvel environnement (dev, staging, prod), les trades de base seront créés et les services existants seront automatiquement backfillés sur le trade "Nettoyage" (idempotent).

---

### Fix #5 — Icônes PWA + commande de génération

**Bug** : `public/manifest.webmanifest` référençait 11 icônes (`/icons/icon-72.png` jusqu'à `/icons/icon-512-maskable.png`). Le dossier `public/icons/` n'existait pas. Conséquence : Chrome/Edge refusaient d'installer la PWA, le prompt "Ajouter à l'écran d'accueil" ne s'affichait jamais.

**Fichiers ajoutés** :
- `public/icons/icon-{72,96,128,144,152,192,384,512}.png` — placeholders monogramme "CU" sur fond `#2563eb` (couleur du theme_color du manifest)
- `public/icons/icon-{192,512}-maskable.png` — variantes maskable (safe-area 80%)
- `app/Console/Commands/GeneratePwaIconsCommand.php` — nouvelle commande artisan `php artisan pwa:icons` pour régénérer

**À faire** : dès que ton vrai logo est prêt, le remplacer :
```bash
php artisan pwa:icons --source=path/to/your/logo.png --force
```

---

### Fix #6 — Code mort `boot()` dans `SurgePricingEngine` et `DynamicPricingService`

**Bug** : `SurgePricingEngine.php` ET `DynamicPricingService.php` avaient chacun une méthode `boot()` qui faisait `$this->app->bind(...)`. Mais ces classes ne sont pas des `ServiceProvider` — la méthode n'était jamais appelée et `$this->app` n'existait pas (PHP fatal si invoqué). Le binding "backward-compat" qui était censé router `DynamicPricingService` vers `SurgePricingEngine` n'avait **jamais fonctionné** depuis Phase 14. Tout code legacy appelant `app(DynamicPricingService::class)->calculate(...)` exécutait encore les 4 règles fixes pré-Phase 14 au lieu du nouveau moteur multi-critères.

**Fichiers modifiés** :
- `app/Services/Pricing/SurgePricingEngine.php` → suppression de `boot()` (commentaire explicatif laissé)
- `app/Services/Pricing/DynamicPricingService/DynamicPricingService.php` → `calculate()` délègue maintenant directement au `SurgePricingEngine` en interne (pas besoin de binding container). Fallback sur la logique legacy 4-règles uniquement si le moteur Surge throw (ex. table `pricing_zones_state` pas encore migrée).

---

### Fix #7 — `lockForUpdate()` dans `accept()` + filtre `is_online` correct

**Bug A — race condition** : `MissionDispatchService::accept()` n'avait pas de `lockForUpdate()` sur la mission. Si 2 prestataires acceptaient dans la même seconde (broadcast pattern, double-tap mobile), les 2 `update` lisaient la mission en `planned`, les 2 la passaient en `assigned`, le second écrasait le lead du premier.

**Bug B — closure `filter()` cassée** : `AiDispatchService::rankEmployees()` avait une closure `filter()` sans `return true` final. Pour les bookings non-ASAP (= cas par défaut, scheduled), elle retournait implicitement `null` (= falsy) → **tous les prestataires éliminés** → `dispatchToNextProvider()` retournait `null` → flux Phase 11 cassé pour toutes les missions planifiées.

**Fichiers modifiés** :
- `app/Services/Dispatch/MissionDispatchService.php::accept()` → ajout `Mission::query()->whereKey($id)->lockForUpdate()->first()` en début de transaction
- `app/Services/Dispatch/AiDispatchService.php::rankEmployees()` → `return true` ajouté en bas de la closure, commentaire explicatif

---

## Tests de régression ajoutés

`tests/Feature/Regression/PostFixesRegressionTest.php` — un test ciblé par fix. Si l'un de ces bugs réapparaît, la CI le détectera immédiatement avec un message clair qui pointe vers le fichier à inspecter.

Les tests couvrent :
- ✅ Route `/webhooks/stripe-connect` enregistrée et exclue du CSRF
- ✅ `lead_provider_user_id` mass-assignable
- ✅ `$mission->booking` résout vers Booking
- ✅ `accept()` écrit les deux colonnes lead
- ✅ `/admin/trades` retourne pas 500 (HTTP-level, attrape les layouts manquants)
- ✅ `ReferencePlatformSeeder` crée bien les trades
- ✅ `DynamicPricingService::calculate()` délègue à `SurgePricingEngine`

**Note importante** : le test `test_accept_marks_assignment_and_mission` existant dans `Phase11Test.php` aurait dû attraper le bug fillable initial. Il ne l'a pas fait parce que les tests n'ont jamais été exécutés (PHP 8.5 / extension DOM manquante en dev). **Recommande fortement** de débloquer la suite de tests dès maintenant — voir section suivante.

---

## Comment appliquer le patch

### Option 1 — Copie directe des fichiers
Les fichiers modifiés sont fournis dans `files/` avec la structure complète du projet. Copie-les par-dessus ton repo :

```bash
# depuis la racine de CleanUx
cp -r /chemin/vers/cleanux-fixes/files/* .
```

### Option 2 — Si tu as Git
Tu peux comparer chaque fichier avec ta version :
```bash
diff -u /chemin/cleanux-fixes/files/app/Models/Mission.php app/Models/Mission.php
```

### Étapes post-application

1. **Pas de migration à passer** — les colonnes utilisées existent déjà.

2. **Re-seed pour avoir les trades** (sécurisé, idempotent grâce au backfill) :
   ```bash
   php artisan db:seed --class=ReferencePlatformSeeder
   ```

3. **Générer les vraies clés VAPID prod** (ne pas réutiliser les placeholders du `.env`) :
   ```bash
   php artisan webpush:vapid
   # copier les clés affichées dans .env
   ```

4. **Configurer le webhook Stripe Connect** :
   - Dans `.env` : `STRIPE_CONNECT_WEBHOOK_SECRET=whsec_...`
   - Dans le Stripe Dashboard : ajouter l'endpoint `https://yourdomain/webhooks/stripe-connect`
   - Events à activer : `account.updated`, `payout.paid`, `payout.failed`, `charge.refunded`, `payment_intent.succeeded`, `payment_intent.payment_failed`

5. **Remplacer les icônes PWA** par ton vrai logo :
   ```bash
   php artisan pwa:icons --source=storage/app/logo.png --force
   ```

6. **Faire tourner les tests** (résoudre l'environnement PHP avant) :
   ```bash
   # Si PHP 8.5 n'est pas dispo en local :
   #   - soit installer PHP 8.5
   #   - soit downgrader composer.json à "php": "^8.2"
   # Et installer ext-dom / ext-xml :
   sudo apt install php-xml php-dom  # Linux
   brew install php@8.4 && brew link --force php@8.4  # macOS

   php artisan test --filter=Phase11
   php artisan test --filter=Phase12
   php artisan test --filter=Phase13
   php artisan test --filter=Phase14
   php artisan test --filter=PostFixesRegressionTest
   ```

---

## Bug bonus découvert pendant l'application

En lisant `app/Models/Mission.php` pour le Fix #2, j'ai découvert que `$mission->booking` retournait `null` dans 8+ call sites (Phase 11/12/13) parce que la relation Eloquent s'appelait `rendezVous()` (vestige de l'ancienne nomenclature). La table s'appelle `bookings`, le code Phase 11+ utilise `$mission->booking`, mais la classe Mission n'avait pas de méthode `booking()` → null partout.

C'est probablement le **bug le plus impactant** parmi tout ce qui a été corrigé : il rendait le dispatch, l'ETA, le paiement Stripe Connect, et les controllers Phase 12 silencieusement non-fonctionnels en production. Il a été ajouté au Fix #2 (relation `booking()` ajoutée à Mission).

---

## Ce qui reste à faire après ce patch

Aucun de ces points ne bloque le déploiement immédiat des fixes ci-dessus, mais ils complèteront le tableau :

1. **Multi-trade fonctionnel côté UX** : le booking flow `PrendreRendezVous` ignore encore les Trades. À refactor en Step 1 (trade) → Step 2 (service). ~4-6h.
2. **`CatalogueServices` admin** : ajout d'un champ `trade_id` lors de la création d'un service. ~1h.
3. **Wording multi-services** : home, register, chatbot parlent encore "nettoyage" en dur. ~2h.
4. **Système d'invitations entreprise** : table `organization_invitations` + Mailable + tokens. ~6-8h.
5. **Observabilité dispatch** : table `dispatch_metrics` + dashboard admin. ~3h.
6. **Idempotency-key** sur les endpoints d'écriture API mobile. ~2h.

Voir le diagnostic précédent pour le détail du roadmap "marketplace multi-services".
