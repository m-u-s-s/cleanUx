# Phase 13 — ETA temps réel + Stripe Connect complet

> **Objectif** : finir les manques Uber-style :
> 1. Le client voit un ETA précis et live de son prestataire
> 2. Les paiements Stripe Connect sont **complètement bouclés** (capture, transfer,
>    refund, webhooks)
> 3. Le prestataire voit ses versements et son solde

> **Durée d'application** : 1h. Le code est livré, mais 2-3 patches manuels
> sont nécessaires (services.php, web.php, AppServiceProvider).

---

## 1. Architecture livrée

```
app/Models/
└── ProviderPayout.php                              ← model pour la table existante

app/Services/Eta/
└── EtaService.php                                  ← Google Distance Matrix + Haversine fallback

app/Services/Payments/
└── StripeConnectPaymentService.php                 ← capture, refund, sync (étend MissionPaymentService)

app/Events/Dispatch/
└── MissionEtaUpdated.php                           ← broadcast sur channel mission.{id}

app/Observers/
└── MissionTrackingPointObserver.php                ← recalc ETA à chaque ping GPS

app/Http/Controllers/Webhooks/
└── StripeConnectWebhookController.php              ← /webhooks/stripe-connect

app/Http/Controllers/Api/Provider/
└── ProviderPayoutsController.php                   ← /api/provider/payouts/*

app/Livewire/Provider/
└── ProviderPayoutsPage.php                         ← écran web "Mes versements"

resources/views/livewire/provider/
└── provider-payouts-page.blade.php

database/migrations/
└── 2026_05_08_120001_add_eta_columns_to_missions.php

tests/Feature/Phase13/
└── Phase13Test.php                                 ← 12 tests

patches/
└── 01_integration.md
```

## 2. Vue d'ensemble des flows

### Flow A — ETA temps réel (côté client)

```
Prestataire en route → push GPS toutes les 30s
       ↓
MissionTrackingService::pushPoint() → crée MissionTrackingPoint
       ↓
MissionTrackingPointObserver::created() (auto)
       ↓
EtaService::computeForMission()
   ├─ Google Distance Matrix (si key configurée)
   └─ Fallback Haversine (sinon)
       ↓
   - missions.last_eta_meters/seconds/calculated_at mis à jour
   - Cache 60s côté serveur
       ↓
event(MissionEtaUpdated) → PrivateChannel "mission.{id}"
       ↓
Client (web Livewire ou app mobile via Reverb) reçoit l'event
       ↓
UI affiche "Arrive dans 4 min" (mis à jour live)
```

### Flow B — Capture du paiement après mission complétée

```
Provider POST /api/provider/missions/{id}/complete
       ↓
MissionLifecycleService::completeMission()
       ↓
[patch §10] StripeConnectPaymentService::captureMissionPayment()
       ↓
   - PaymentIntent.capture() → Stripe
   - booking.payment_status = 'captured'
   - booking.payment_captured_at = now
       ↓
StripeConnectPaymentService::createProviderPayout()
   → ProviderPayout entry status='pending'
       ↓
Stripe push transfer auto vers le compte Connect du provider
   (configuré au moment du authorize via transfer_data.destination)
       ↓
Stripe regroupe les transfers en payout standard (selon planning compte Connect)
       ↓
Webhook payout.paid reçu sur /webhooks/stripe-connect
       ↓
ProviderPayout.markAsPaid() → status='paid'
       ↓
Provider voit son versement dans /provider/payouts (web)
   ou GET /api/provider/payouts (mobile)
```

### Flow C — Refund

```
Admin déclenche refund (via Tinker ou endpoint à créer)
       ↓
StripeConnectPaymentService::refundMissionPayment($booking, $amount?)
       ↓
   - Refund.create() → Stripe
   - booking.payment_status = 'refunded' ou 'partially_refunded'
       ↓
Webhook charge.refunded reçu (confirmation Stripe)
       ↓
StripeConnectWebhookController::handleChargeRefunded() → re-sync booking
```

## 3. Endpoints API ajoutés (3)

| Méthode | Endpoint | Description |
|---|---|---|
| GET | `/api/provider/payouts` | Historique paginé avec filtres status/dates |
| GET | `/api/provider/payouts/summary` | Solde mois en cours / passé / total |
| GET | `/api/provider/balance` | Balance Stripe Connect (available + pending) |

Plus le webhook public :

| Méthode | Endpoint | Description |
|---|---|---|
| POST | `/webhooks/stripe-connect` | Reçoit les events Stripe Connect (signature vérifiée) |

## 4. Webhook Stripe Connect — events gérés

| Event | Action |
|---|---|
| `account.updated` | Re-sync verification status du provider |
| `payout.paid` | ProviderPayout → status='paid' + provider_payout_id |
| `payout.failed` | ProviderPayout → status='failed' + reason metadata |
| `charge.refunded` | Booking → status='refunded' ou 'partially_refunded' |
| `payment_intent.succeeded` | Re-sync booking via syncPaymentIntent() |
| `payment_intent.payment_failed` | Booking → payment_status='failed' |

Tous les autres events sont 200 OK silencieux.

**Sécurité** : signature vérifiée via `STRIPE_CONNECT_WEBHOOK_SECRET`. Sans ce secret en .env, le webhook retourne 500.

## 5. Stratégie ETA

Le calcul ETA est **double** :

1. **Google Distance Matrix** (si `GOOGLE_MAPS_API_KEY` dans .env)
   - Vrai routing routier
   - Prend le trafic en compte (`departure_time=now`)
   - Coût : ~$5 / 1000 requêtes
   - Cache 60s pour économiser le quota

2. **Haversine** (fallback)
   - Distance ligne droite × 30 km/h
   - Gratuit
   - Imprécis en ville (-30 à +50%)

L'observer est **throttlé naturellement** par le cache 60s. Si 2 ping GPS arrivent en 30s, le 2e n'appelle pas Google.

## 6. Stats Phase 13

| Composant | Lignes |
|---|---|
| `ProviderPayout` model | ~135 |
| `EtaService` | ~205 |
| `StripeConnectPaymentService` | ~245 |
| `StripeConnectWebhookController` | ~205 |
| `ProviderPayoutsController` | ~155 |
| `MissionEtaUpdated` event | ~50 |
| `MissionTrackingPointObserver` | ~50 |
| `ProviderPayoutsPage` Livewire | ~75 |
| `provider-payouts-page.blade` | ~135 |
| Migration ETA columns | ~50 |
| Tests (12) | ~270 |
| Patches + guide | ~390 |
| **Total Phase 13** | **~1965 lignes** |

## 7. Tests inclus (12)

**ProviderPayout (3)** :
- ✅ markAsPaid update status + provider_payout_id
- ✅ Scopes forProvider/paid/pending fonctionnent
- ✅ Helpers isPending/isPaid + markAsProcessing/markAsFailed

**EtaService (3)** :
- ✅ Haversine retourne meters et seconds (Bruxelles → Anvers ~40km)
- ✅ computeForMission retourne empty si pas de tracking actif
- ✅ computeForMission persiste sur mission.last_eta_*

**API Payouts (4)** :
- ✅ Provider voit ses payouts (pas ceux des autres)
- ✅ Summary calcule les totaux ce mois / mois dernier
- ✅ Non-provider rejeté (403)
- ✅ Filtre status fonctionne

**Webhook (2)** :
- ✅ Rejette signature invalide (400)
- ✅ Retourne 500 si secret non configuré

## 8. Application — étapes

### Étape 1 — Décompresser

```bash
unzip cleanux-phase13.zip
cd /chemin/vers/CleanUx
git checkout -b phase13/eta-stripe-connect

rsync -av cleanux-phase13/app/        app/
rsync -av cleanux-phase13/database/   database/
rsync -av cleanux-phase13/resources/  resources/
rsync -av cleanux-phase13/tests/      tests/
```

### Étape 2 — Migrations

```bash
php artisan migrate
```

Idempotent. La migration ajoute juste 4 colonnes ETA sur `missions`.

### Étape 3 — Patches manuels

Voir `patches/01_integration.md`. **Essentiels** :

1. `routes/api.php` : ajouter 3 routes payouts
2. `routes/web.php` : ajouter webhook + page payouts (avec exclusion CSRF)
3. `config/services.php` : ajouter section `stripe.connect_webhook_secret` + `google_maps.api_key`
4. `.env` : ajouter `STRIPE_CONNECT_WEBHOOK_SECRET`, `GOOGLE_MAPS_API_KEY`
5. `app/Providers/AppServiceProvider.php` : register `MissionTrackingPointObserver`
6. `routes/channels.php` : autoriser channel `mission.{id}`
7. (Optionnel) Patcher `MissionLifecycleService::completeMission()` pour auto-capture

### Étape 4 — Stripe Dashboard

Configurer le webhook dans https://dashboard.stripe.com/webhooks :
- URL : `https://ton-app.com/webhooks/stripe-connect`
- Listen to : **"Events on Connected accounts"**
- Events : `account.updated`, `payout.paid`, `payout.failed`, `charge.refunded`, `payment_intent.succeeded`, `payment_intent.payment_failed`
- Récupérer signing secret → `STRIPE_CONNECT_WEBHOOK_SECRET`

### Étape 5 — Tests

```bash
php artisan test --filter=Phase13Test
```

12 tests doivent passer.

### Étape 6 — Test live webhook (Stripe CLI)

```bash
# Forward Stripe events vers ton local
stripe listen --forward-to localhost:8000/webhooks/stripe-connect \
  --events account.updated,payout.paid

# Dans un autre terminal, déclencher un test
stripe trigger payout.paid
```

## 9. Flow de test end-to-end

```
1. Provider connecte son compte Stripe Connect
   → onboarding link via StripeConnectService::onboardingLink()

2. Webhook account.updated reçu après onboarding
   → user.stripe_connect_status = 'active'

3. Client crée et autorise un booking
   → MissionPaymentService::authorize()
   → booking.payment_status = 'authorized'

4. Mission assignée + démarrée + tracking GPS
   → MissionTrackingPointObserver recalcule ETA à chaque ping
   → Channel mission.{id} reçoit MissionEtaUpdated en live

5. Mission complétée
   → StripeConnectPaymentService::captureMissionPayment()
   → PaymentIntent.capture()
   → ProviderPayout créé en pending
   → booking.payment_status = 'captured'

6. Stripe push payout sur le compte Connect (selon planning)
   → Webhook payout.paid reçu
   → ProviderPayout.markAsPaid()

7. Provider voit le paiement dans /provider/payouts
```

## 10. Limites honnêtes

- **Auto-capture pas branchée par défaut** : le patch §10 (intégration dans
  `MissionLifecycleService::completeMission`) est manuel parce que ça touche
  un service core. Mon recommandation prod : queue les captures en job async
  pour pouvoir retry si Stripe est down.
- **ETA latence 60s** : le cache économise le quota Google mais limite la
  fraîcheur. Pour vrai temps-réel < 5s il faudrait WebSocket et non Distance
  Matrix.
- **Disputes Stripe pas gérées** : la résolution se fait dans le dashboard
  Stripe. Phase 13 ne fait que recevoir le webhook.
- **Pas d'endpoint API admin pour refund** : la méthode `refundMissionPayment`
  existe sur le service mais n'est pas exposée via une route. À ajouter si
  besoin business (sinon, refund via Tinker ou dashboard Stripe).
- **Pas de tests Livewire UI** : la page payouts n'a pas de test (j'ai gardé le
  focus sur services + API).
- **Pas de tests pour l'observer** : il dépend d'un Mission réel et d'un
  HTTP call Google → testable mais lourd, j'ai testé EtaService directement.
- **Stripe Connect = pays uniques par compte** : ton service crée des comptes
  BE. Pour multi-pays, il faut adapter `StripeConnectService::createOrGetAccount`
  selon le user.country.

## 11. Checklist de PR

```
[ ] rsync app/ database/ resources/ tests/ depuis cleanux-phase13/
[ ] php artisan migrate (1 nouvelle migration)
[ ] routes/api.php : 3 routes payouts
[ ] routes/web.php : webhook + page provider/payouts (CSRF exclusion)
[ ] config/services.php : stripe.connect_webhook_secret + google_maps.api_key
[ ] .env : STRIPE_CONNECT_WEBHOOK_SECRET, GOOGLE_MAPS_API_KEY (optionnel)
[ ] app/Providers/AppServiceProvider.php : Observer registered
[ ] routes/channels.php : autoriser mission.{id}
[ ] Stripe Dashboard : webhook configuré sur URL prod
[ ] (optionnel) MissionLifecycleService patch §10 pour auto-capture
[ ] php artisan test --filter=Phase13Test → 12 tests verts
[ ] Test stripe listen → trigger event
[ ] git commit -m "feat(payments+eta): Phase 13 — ETA temps réel + Stripe Connect complet"
```

## 12. Ce qu'il reste pour terminer Uber-style

Avec Phase 8 + 11 + 12 + 13, **tu as tout le backbone Uber** :
- ✅ Provider go online/offline + heartbeat
- ✅ Dispatch automatique avec timer accept/decline
- ✅ Escalation au prestataire suivant
- ✅ API mobile complète (auth, bookings, missions, payouts)
- ✅ Push notifications PWA + Web Push
- ✅ ETA temps réel avec Google Distance Matrix
- ✅ Stripe Connect bouclé (capture → transfer → payout webhook)
- ✅ Provider voit ses versements et son solde

Ce qu'il **reste** au-dessus, c'est la croissance / scale (qui sont des nice-to-have, pas des bloquants) :

| Quoi | Effort | Quand |
|---|---|---|
| **Surge pricing avancé** (par zone + temporel) | ~500 lignes | Phase 14 si business l'exige |
| **Provider onboarding self-service** | ~800 lignes | Phase 14 |
| **Cancellation fees + no-show** | ~200 lignes | Phase 14 |
| **Phase 10 White-label** | ~2500-10500 lignes | Quand revendeurs prêts |
| **Vraie app native** (iOS + Android) | ~mois de dev | Quand la PWA n'est plus suffisante |

## 13. Récap global Uber-style depuis Phase 8

| Phase | Lignes | Cible |
|---|---|---|
| Phase 8 — PWA + Push | 1947 | Notifications instantanées |
| Phase 11 — Presence + Accept/Decline + Escalation | 2765 | Dispatch temps réel |
| Phase 12 — API mobile | 1780 | App mobile possible |
| Phase 13 — ETA + Stripe Connect | 1965 | UX client + paiements bouclés |
| **Total Phase 8→13** | **~8460 lignes** | **Plateforme Uber-style fonctionnelle** |

---

Quand Phase 13 est validée chez toi (capture après completion + payout reçu + ETA en live), tu pourras :
- Soit attaquer les nice-to-have (Phase 14 surge / onboarding / cancellation fees)
- Soit attaquer **Phase 10 — White-label** (nécessite refonte multi-tenant)

Pour Phase 10 il faudra reprendre la discussion architecture (single-DB tenant_id vs stancl/tenancy). Quand tu es prêt, dis "Phase 10" et je propose un plan détaillé.
