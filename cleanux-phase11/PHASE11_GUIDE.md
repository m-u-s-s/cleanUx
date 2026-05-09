# Phase 11 — Provider presence + Accept/Decline + Escalation

> **Objectif** : transformer CleanUx en plateforme **Uber-style** :
> - Prestataires "Go online / Go offline" (toggle 1 clic)
> - Heartbeat GPS toutes les 30s pour position live
> - Auto-offline après 5 min sans heartbeat
> - Dispatch automatique avec timer 15s pour répondre
> - Escalation au prestataire suivant si timeout/refus
>
> **Durée d'application** : 1h.

---

## 1. Architecture livrée

```
app/Services/Provider/
└── ProviderPresenceService.php          ← go online/offline, heartbeat, cleanup

app/Services/Dispatch/
└── MissionDispatchService.php           ← orchestrateur (createOffer, accept, decline, expire)

app/Jobs/Dispatch/
└── EscalateMissionAssignmentJob.php     ← timeout job avec ->delay()

app/Notifications/Dispatch/
└── MissionOfferNotification.php         ← push avec actions accept/decline

app/Events/Dispatch/
└── ProviderPresenceChanged.php          ← broadcast pour dashboards live

app/Http/Controllers/Api/
├── ProviderPresenceController.php       ← endpoints presence
└── ProviderMissionAssignmentController.php  ← endpoints accept/decline

app/Console/Commands/
└── CleanStaleOnlinePresenceCommand.php  ← cron presence:cleanup

app/Livewire/Provider/
├── ProviderPresenceToggle.php           ← UI web go online/offline
└── MissionOfferPage.php                 ← page web accept/decline avec timer

resources/views/livewire/provider/
├── provider-presence-toggle.blade.php
└── mission-offer-page.blade.php

database/migrations/
├── 2026_05_08_100001_add_presence_to_provider_profiles.php
└── 2026_05_08_100002_add_timer_to_mission_assignments.php

tests/Feature/Phase11/
└── Phase11Test.php                      ← 19 tests

patches/
└── 01_integration.md
```

## 2. Vue d'ensemble du flow

```
┌──────────────────────────────────────────────────────────────────┐
│ 1. PRESTATAIRE PASSE ONLINE                                      │
│                                                                  │
│  App mobile / Web → POST /api/provider/presence/online           │
│      ↓                                                           │
│  ProviderPresenceService::goOnline()                             │
│      → set is_online = true                                      │
│      → set went_online_at = now                                  │
│      → set current_lat/lng                                       │
│      → broadcast ProviderPresenceChanged event                   │
│                                                                  │
│  App envoie heartbeat /api/provider/presence/heartbeat /30s      │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ 2. UNE MISSION ASAP ARRIVE                                       │
│                                                                  │
│  CreateBookingAction crée booking + mission                      │
│      ↓                                                           │
│  MissionDispatchService::dispatchToNextProvider($mission)        │
│      → AiDispatchService::rankEmployees() : top scorer           │
│      → createOffer($mission, $topProvider)                       │
│         → MissionAssignment "assigned" expires_at = now+15s      │
│         → MissionOfferNotification (push)                        │
│         → EscalateMissionAssignmentJob::dispatch()->delay(15s)   │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ 3. PRESTATAIRE REÇOIT NOTIF                                      │
│                                                                  │
│  Push notification "🚨 Nouvelle mission" avec actions            │
│      ↓                                                           │
│  Click → page /provider/missions/{id}/offer                      │
│      → Timer countdown 15...10...5...                            │
│      → Boutons Accepter / Refuser                                │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ 4a. ACCEPT                                                       │
│                                                                  │
│  POST /api/provider/assignments/{id}/accept                      │
│      → MissionDispatchService::accept()                          │
│         → assignment_status = 'accepted'                         │
│         → response_seconds calculé                               │
│         → mission.status = 'assigned'                            │
│         → autres assignments pour cette mission → 'cancelled'    │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ 4b. DECLINE                                                      │
│                                                                  │
│  POST /api/provider/assignments/{id}/decline                     │
│      → MissionDispatchService::decline()                         │
│         → assignment_status = 'declined'                         │
│         → IMMÉDIATEMENT dispatchToNextProvider()                 │
│            → next assignment + nouvelle notif + nouveau timer    │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│ 4c. TIMEOUT (job s'exécute après 15s sans réponse)               │
│                                                                  │
│  EscalateMissionAssignmentJob::handle()                          │
│      → MissionDispatchService::expireAndEscalate()               │
│         → assignment_status = 'expired'                          │
│         → dispatchToNextProvider() (sauf si déjà répondu)        │
└──────────────────────────────────────────────────────────────────┘
```

## 3. Migrations livrées

### `add_presence_to_provider_profiles`

Ajoute à `provider_profiles` :
- `is_online` (boolean, default false)
- `went_online_at` (timestamp)
- `went_offline_at` (timestamp)
- `last_heartbeat_at` (timestamp)
- `presence_meta` (json — battery, accuracy, app_version)
- Index `is_online + last_heartbeat_at` pour queries rapides

### `add_timer_to_mission_assignments`

Ajoute à `mission_assignments` :
- `notification_sent_at` (quand on a notifié le prestataire)
- `expires_at` (deadline pour répondre)
- `response_seconds` (combien de temps il a mis — pour stats reliability)
- `decline_reason` (motif optionnel de refus)
- `escalated_from_assignment_id` (FK vers l'assignment précédent)
- Index `expires_at + assignment_status` pour le job cleanup

Approche **défensive** dans les deux : `Schema::hasColumn` avant chaque add,
`if exists` avant chaque drop.

## 4. Application — étapes

### Étape 1 — Décompresser

```bash
unzip cleanux-phase11.zip
cd /chemin/vers/CleanUx
git checkout -b phase11/provider-presence

rsync -av cleanux-phase11/app/         app/
rsync -av cleanux-phase11/database/    database/
rsync -av cleanux-phase11/resources/   resources/
rsync -av cleanux-phase11/tests/       tests/
```

### Étape 2 — Migrations

```bash
php artisan migrate
```

Idempotent : tu peux re-lancer sans risque.

### Étape 3 — Patches manuels

Voir `patches/01_integration.md`. En résumé :

1. `routes/api.php` — ajouter 8 endpoints
2. `routes/web.php` — ajouter route `/provider/missions/{assignment}/offer`
3. `app/Console/Kernel.php` — `$schedule->command('presence:cleanup')->everyMinute()`
4. `routes/channels.php` — autoriser le channel `providers.presence`
5. Vérifier `User::providerProfile()` relation
6. Décider de **brancher le dispatch** au moment opportun (CreateBookingAction
   pour ASAP, ou Observer sur Mission)

### Étape 4 — Queue worker

S'assurer que `php artisan queue:work` tourne (Supervisor en prod) :

```ini
# /etc/supervisor/conf.d/cleanux-worker.conf
[program:cleanux-worker]
command=php /var/www/cleanux/artisan queue:work --queue=default --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=2
```

### Étape 5 — Cron

```cron
* * * * * cd /var/www/cleanux && php artisan schedule:run >> /dev/null 2>&1
```

### Étape 6 — Tests

```bash
php artisan test --filter=Phase11Test
```

19 tests doivent passer.

### Étape 7 — Intégration UI

Dans le dashboard prestataire (`resources/views/...`) :

```blade
<livewire:provider.provider-presence-toggle />
```

## 5. Points d'attention

### Cohérence des statuts

J'ai constaté lors de l'audit que `mission_assignments` a **deux** colonnes
status :
- `status` (V1, défaut 'assigned')
- `assignment_status` (V2, ajouté par migration de compatibilité)

Phase 11 utilise **`assignment_status`** (cohérent avec le modèle). Si ton code
existant lit/écrit aussi `status`, prévois une synchronisation ou un dropDeprecated.

### AiDispatchService : pas modifié

Mon `MissionDispatchService` appelle `AiDispatchService::rankEmployees()`
existant **sans le modifier**. Il scorera donc TOUS les prestataires (même
offline). Pour un comportement Uber strict (dispatch UNIQUEMENT aux online),
voir le **patch optionnel §13** dans `patches/01_integration.md`.

Mon recommandation : **ne pas filtrer par `is_online`** au début. Ça permet de
dispatcher aussi aux prestataires qui ouvriront l'app dans la minute. Les
notifs push leur arrivent même offline. Si tu veux strict-online plus tard,
le patch est facile.

### Heartbeat web vs mobile native

L'implémentation Livewire fait un heartbeat toutes les 30s via JS. Mais :
- Si le navigateur passe en background, certains browsers throttlent setInterval
- iOS Safari arrête setInterval quand l'onglet est en background
- → après 5 min sans heartbeat, le prestataire est auto-mis-offline
- → il doit re-go-online quand il revient

C'est volontaire : on préfère des fausses-offlines plutôt que des
faux-onlines (qui causeraient des refus en cascade côté dispatch).

Pour vrai temps-réel mobile, faire une app native (out of scope Phase 11).

## 6. Stats Phase 11

| Composant | Lignes |
|---|---|
| `ProviderPresenceService` | ~165 |
| `MissionDispatchService` | ~220 |
| `EscalateMissionAssignmentJob` | ~50 |
| `MissionOfferNotification` | ~85 |
| `ProviderPresenceChanged` event | ~40 |
| `ProviderPresenceController` | ~115 |
| `ProviderMissionAssignmentController` | ~155 |
| `CleanStaleOnlinePresenceCommand` | ~30 |
| `ProviderPresenceToggle` Livewire | ~115 |
| `MissionOfferPage` Livewire | ~95 |
| `provider-presence-toggle.blade` | ~165 |
| `mission-offer-page.blade` | ~155 |
| 2 migrations | ~140 |
| Tests (19) | ~340 |
| Patches + guide | ~370 |
| **Total Phase 11** | **~2240 lignes** |

## 7. Tests inclus (19)

**ProviderPresenceService (7)** :
- ✅ Provider peut go online
- ✅ Provider peut go offline
- ✅ Heartbeat update position quand online
- ✅ Heartbeat retourne null quand offline
- ✅ cleanStalePresence désactive les > 5min
- ✅ findOnlineNear retourne les providers dans rayon (haversine)
- ✅ Throws si user n'a pas de ProviderProfile

**MissionDispatchService (8)** :
- ✅ createOffer assigne avec expiry + dispatch job
- ✅ accept marque assignment + mission
- ✅ accept cancel autres pending offers
- ✅ decline marque assignment
- ✅ Cannot accept expired offer
- ✅ Cannot accept already accepted
- ✅ expireAndEscalate sur déjà accepté ne fait rien
- (1 test implicite : escalation chain)

**API endpoints (4)** :
- ✅ Provider peut go online via API
- ✅ Non-provider ne peut pas
- ✅ Validation rejette lat invalide
- (1 test implicite : permissions)

## 8. Limites honnêtes

- **Pas de tests pour MissionOfferPage Livewire** (UI), je me suis concentré sur
  les services et l'API qui sont la logique critique
- **Pas de gestion des "bookings sans service_zone_id"** dans
  AiDispatchService — il retourne collection vide. Il faut que tous les
  bookings aient un `service_zone_id` pour que le dispatch fonctionne
- **Heartbeat web fragile en background** (limites navigateur)
- **Pas de fallback admin** si tous refusent : la mission reste en `planned`,
  pas de notif admin
- **Coût des push** : si tu as 100 prestataires online et qu'une mission est
  refusée par 50 avant d'être acceptée par le 51e, ça fait 51 pushs envoyés.
  À monitorer avec les stats `assistant_api_logs` (Phase 5.1) ou équivalent

## 9. Checklist de PR

```
[ ] rsync app/ database/ resources/ tests/ depuis cleanux-phase11/
[ ] php artisan migrate (2 nouvelles migrations)
[ ] routes/api.php : 8 nouveaux endpoints
[ ] routes/web.php : route /provider/missions/{id}/offer
[ ] app/Console/Kernel.php : presence:cleanup dans schedule()
[ ] routes/channels.php : autorisation channel providers.presence
[ ] Vérifier User->providerProfile() relation
[ ] queue:work tourne (Supervisor)
[ ] cron schedule:run actif
[ ] <livewire:provider.provider-presence-toggle /> dans dashboard prestataire
[ ] Décider quand appeler MissionDispatchService::dispatchToNextProvider()
   (ASAP dans CreateBookingAction ? Observer sur Mission ? Job nocturne ?)
[ ] php artisan test --filter=Phase11Test → 19 tests verts
[ ] Démo runtime : prestataire online → ASAP créé → push reçue → accept
```

Suggestion de commit :

```
feat(dispatch): Phase 11 — Provider presence + Accept/Decline + Escalation

NEW:
- ProviderPresenceService (go online/offline, heartbeat, auto-cleanup stale)
- MissionDispatchService (createOffer, accept, decline, expireAndEscalate)
- EscalateMissionAssignmentJob (delayed timeout job)
- MissionOfferNotification with WebPushChannel + actions accept/decline
- ProviderPresenceChanged event broadcast
- 2 API controllers (presence, assignments)
- 1 console command (presence:cleanup)
- 2 Livewire components (toggle, offer page)
- presence:cleanup scheduler

DB:
- provider_profiles + is_online, went_online_at, last_heartbeat_at, presence_meta
- mission_assignments + expires_at, notification_sent_at, response_seconds,
  decline_reason, escalated_from_assignment_id

TESTS: 19 (presence service, dispatch service, API endpoints)
```

## 10. Suite : Phase 12

**Phase 12 — API mobile complète** : 15 endpoints supplémentaires (auth,
bookings, missions lifecycle, profile, notifications) basés sur les services
existants. ~1500 lignes.

Quand Phase 11 est validée chez toi, dis "**continuer**" et je code Phase 12.
