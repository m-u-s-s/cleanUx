# Phase 14 — Surge pricing + Provider onboarding + Cancellation fees

> **Objectif** : finir les nice-to-have Uber-style identifiés dans l'analyse :
> 1. **Surge pricing avancé** par zone + temporel + décay (remplace les 32
>    lignes de `DynamicPricingService`)
> 2. **Provider onboarding self-service** : un prestataire peut s'inscrire et
>    aller jusqu'à actif sans intervention manuelle
> 3. **Cancellation fees** : règles métier de frais d'annulation selon délai
>    + détection no-show
>
> **Durée d'application** : 1h. Le code est livré, 5-6 patches manuels.

---

## 1. Architecture livrée

```
app/Models/
├── PricingZoneState.php                        ← état surge par zone
└── ProviderOnboardingDocument.php              ← KYC documents

app/Services/Pricing/
└── SurgePricingEngine.php                      ← moteur multi-critères

app/Services/Onboarding/
└── ProviderOnboardingService.php               ← gestion étapes onboarding

app/Services/Cancellation/
├── CancellationFeeCalculator.php               ← pure function fee calculator
└── CancelBookingService.php                    ← orchestrateur cancel + redispatch

app/Jobs/Pricing/
└── RecomputeSurgeJob.php                       ← job de recalcul périodique

app/Console/Commands/
└── RecomputeSurgeCommand.php                   ← php artisan surge:recompute

app/Http/Controllers/Api/Provider/
├── ProviderOnboardingController.php            ← /api/provider/onboarding/*
└── ProviderCancellationController.php          ← /api/provider/missions/{id}/cancel

app/Http/Controllers/Api/Client/
└── CancellationController.php                  ← /api/client/bookings/{id}/cancel-with-fee

config/
├── surge.php                                   ← seuils surge (ajustables)
└── cancellation.php                            ← règles fees (ajustables)

database/migrations/
├── 2026_05_09_100001_create_pricing_zones_state_table.php
├── 2026_05_09_100002_create_provider_onboarding_documents_table.php
└── 2026_05_09_100003_add_onboarding_to_provider_profiles.php

tests/Feature/Phase14/
└── Phase14Test.php                             ← 22 tests

patches/
└── 01_integration.md
```

## 2. Vue d'ensemble

### Surge pricing — multi-critères

```
multiplier = demand_factor × supply_factor × temporal_factor × asap_extra
```

Avec :
- **demand_factor** (max 1.5) : nombre de bookings ouverts dans la zone (60 dernières minutes)
- **supply_factor** (max 1.6) : inverse du nombre de prestataires online dans la zone
- **temporal_factor** : pics horaires (07-09, 11-13, 17-19, 22-02) + bonus weekend +10%
- **asap_extra** : ×1.25 si mode ASAP

**Cap absolu** : 3.0 (configurable) — au-delà = "prix abusif" en BE/FR.

**Cache + decay** :
- Multiplier d'une zone stocké dans `pricing_zones_state` (1 row / zone)
- Recalculé toutes les 60s par `RecomputeSurgeJob`
- Si pas calculé depuis `surge.state_ttl_seconds` → revient à 1.0 (decay naturel)

### Onboarding provider — 7 étapes

```
0. profile_basics  → nom, photo, bio
1. identity        → 1 doc parmi {ID, passport, residence_permit}
2. tax             → numéro TVA / SIREN
3. insurance       → attestation responsabilité civile pro
4. skills          → métiers + zones de travail
5. stripe_connect  → onboarding Stripe (utilise StripeConnectService existant)
6. ready           → admin valide → verification_status='verified'
```

Chaque étape est idempotente (re-uploader doc, re-définir skills, etc.).

### Cancellation fees — 4 paliers client

| Délai avant RDV | Fee |
|---|---|
| ≥ 24h | 0% |
| 2h - 24h | 25% |
| 30 min - 2h | 50% |
| < 30 min ou après start | 100% |

**Grace window** : 5 min après création (free) — utile pour les ASAP qu'on annule juste après.

**Provider cancellation** : pénalité fixe (5€) + reliability_penalty (-10pts) si moins de 30 min avant RDV.

**No-show** : 15 min après start → fee 100% côté client, pénalité 20€ + 30pts côté provider.

## 3. Endpoints API ajoutés

### Onboarding (6)
| Méthode | Endpoint | Description |
|---|---|---|
| POST | `/api/provider/onboarding/start` | Crée ProviderProfile vide |
| GET | `/api/provider/onboarding/progress` | État d'avancement complet |
| POST | `/api/provider/onboarding/profile` | Étape 0 (bio, photo) |
| POST | `/api/provider/onboarding/documents` | Upload doc KYC |
| POST | `/api/provider/onboarding/tax` | Étape 2 (tax_id) |
| POST | `/api/provider/onboarding/skills` | Étape 4 (skills + zones) |

### Cancellation (4)
| Méthode | Endpoint | Description |
|---|---|---|
| GET | `/api/client/bookings/{id}/cancellation-quote` | Quote AVANT cancel |
| POST | `/api/client/bookings/{id}/cancel-with-fee` | Cancel client avec fee |
| POST | `/api/provider/missions/{id}/cancel` | Cancel provider (avec penalty) |
| POST | `/api/provider/missions/{id}/no-show` | Déclarer no-show client |

## 4. Stats Phase 14

| Composant | Lignes |
|---|---|
| `SurgePricingEngine` | ~265 |
| `RecomputeSurgeJob` + Command | ~100 |
| `PricingZoneState` model | ~75 |
| `ProviderOnboardingService` | ~290 |
| `ProviderOnboardingDocument` model | ~115 |
| `ProviderOnboardingController` | ~135 |
| `CancellationFeeCalculator` | ~190 |
| `CancelBookingService` | ~245 |
| `CancellationController` (client) | ~80 |
| `ProviderCancellationController` | ~75 |
| 3 migrations | ~155 |
| `config/surge.php` | ~60 |
| `config/cancellation.php` | ~55 |
| Tests (22) | ~410 |
| Patches + guide | ~480 |
| **Total Phase 14** | **~2730 lignes** |

## 5. Tests inclus (22)

**SurgePricingEngine (6)** :
- ✅ Base price quand pas de zone et pas de peak
- ✅ Peak temporel evening 18h → ×1.30
- ✅ ASAP extra ×1.25
- ✅ Cap au max_multiplier
- ✅ Utilise zone state quand active
- ✅ Fallback live quand state expired

**Onboarding (7)** :
- ✅ start crée le ProviderProfile
- ✅ setProfileBasics update user + profile
- ✅ uploadDocument crée record pending
- ✅ reviewDocument approve/reject
- ✅ approveOnboarding refuse si pas de doc identité
- ✅ approveOnboarding succeed avec tous docs + Stripe actif
- ✅ getProgress retourne l'état complet

**Cancellation (9)** :
- ✅ Free si > 24h avant
- ✅ 25% si 2h-24h avant
- ✅ 50% si 30min-2h avant
- ✅ 100% si < 30min avant
- ✅ Free dans grace window après création
- ✅ Provider free si > 30min avant
- ✅ Provider penalty si < 30min avant
- ✅ No-show détection après grace
- ✅ cancelByClient enregistre fee dans metadata
- (1 implicite : cannot cancel completed)

## 6. Workflow d'application

```bash
unzip cleanux-phase14.zip
cd /chemin/vers/CleanUx
git checkout -b phase14/surge-onboarding-cancellation

rsync -av cleanux-phase14/app/      app/
rsync -av cleanux-phase14/config/   config/
rsync -av cleanux-phase14/database/ database/
rsync -av cleanux-phase14/tests/    tests/

php artisan migrate

# Patches manuels (voir patches/01_integration.md):
# - routes/api.php : 10 nouveaux endpoints
# - app/Console/Kernel.php : surge:recompute scheduler
# - config/filesystems.php : disk 'private' pour KYC docs
# - (optionnel) AppServiceProvider : alias DynamicPricingService → SurgePricingEngine

php artisan test --filter=Phase14Test  # 22 tests verts

# Test manuel surge
php artisan surge:recompute
```

## 7. Limites honnêtes

- **Pas d'UI admin pour valider les documents** : actuellement via Tinker ou
  endpoint à créer. Le service `ProviderOnboardingService::reviewDocument()`
  est prêt, il faut juste un controller admin (~30 lignes).
- **Surge supply approximatif** : le calcul "online providers in zone" peut
  faire fallback sur "tous les providers online" si la relation
  `zoneAssignments` n'existe pas dans ton User model. Pour précision géo,
  Phase 14.1 ajouter haversine.
- **Reliability penalty stockée en JSON** : `provider_profiles.metadata`
  fonctionne mais une colonne dédiée serait mieux pour reporting.
- **Pas de rappel auto** si un provider abandonne en cours d'onboarding.
  Phase 14.1 : email/push après 7j d'inactivité.
- **DynamicPricingService legacy** : pas supprimé. Le patch §7 propose un
  alias backward-compatible. Une fois call sites migrés, tu peux le supprimer.
- **Pas d'UI client surge dans le flow booking** : il faut intégrer manuellement
  l'affichage "Tarifs élevés" dans `PrendreRendezVous` Livewire (exemple dans
  patches/01_integration.md §8).
- **Cancellation refund partiel** : utilise Phase 13 (`StripeConnectPaymentService`)
  si dispo. Sinon, log + admin doit refund manuellement.
- **Pas de UI Livewire pour onboarding** : tout est API-first. La UI Livewire
  pour l'onboarding est ~500 lignes additionnelles que je peux livrer en
  Phase 14.1 si demandé.

## 8. Checklist de PR

```
[ ] rsync app/ config/ database/ tests/ depuis cleanux-phase14/
[ ] php artisan migrate (3 nouvelles migrations)
[ ] routes/api.php : 10 endpoints (onboarding 6 + cancellation 4)
[ ] app/Console/Kernel.php : $schedule->command('surge:recompute')->everyMinute()
[ ] config/filesystems.php : disk 'private' (si pas déjà présent)
[ ] (optionnel) AppServiceProvider : alias DynamicPricingService → SurgePricingEngine
[ ] (optionnel) UI surge dans PrendreRendezVous Livewire
[ ] (optionnel) UI quote dans modale cancel
[ ] queue:work tourne (Supervisor)
[ ] cron schedule:run actif
[ ] php artisan test --filter=Phase14Test → 22 tests verts
[ ] git commit -m "feat: Phase 14 — Surge pricing + Provider onboarding + Cancellation fees"
```

## 9. Récap global Uber-style

| Phase | Lignes | Statut |
|---|---|---|
| Phase 8 — PWA + Push | 1947 | ✅ livré |
| Phase 11 — Presence + Accept/Decline + Escalation | 2765 | ✅ livré |
| Phase 12 — API mobile complète | 1780 | ✅ livré |
| Phase 13 — ETA + Stripe Connect | 2285 | ✅ livré |
| Phase 14 — Surge + Onboarding + Cancellation | 2730 | ✅ livré |
| **Total Phases 8-14** | **~11500 lignes** | |

**Avec les 5 phases livrées, CleanUx est une plateforme Uber-style complète** :
- ✅ Push notifications PWA installable
- ✅ Provider go online/offline avec heartbeat GPS
- ✅ Dispatch automatique avec timer 15s + escalation
- ✅ API mobile complète (auth, profile, bookings, missions lifecycle)
- ✅ ETA temps réel avec Google Distance Matrix
- ✅ Stripe Connect bouclé (capture, transfer, payouts, webhooks)
- ✅ Surge pricing par zone + temporel + décay
- ✅ Provider onboarding self-service KYC
- ✅ Cancellation fees + no-show detection

Il ne reste que les phases "scale" :

| À faire | Effort | Quand |
|---|---|---|
| Phase 14.1 (UI admin docs review, UI Livewire onboarding) | ~500 lignes | Si demandé |
| Phase 10 — White-label multi-tenant | 2500-10500 lignes | Quand revendeurs prêts |
| App native iOS/Android | mois de dev | Quand PWA n'est plus suffisante |

Pour Phase 10, dis "**Phase 10**" et je propose un plan détaillé avec les
2 options chiffrées (single-DB tenant_id que je recommande, vs stancl/tenancy).
