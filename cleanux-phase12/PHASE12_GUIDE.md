# Phase 12 — API mobile complète

> **Objectif** : exposer une **vraie API REST** mobile pour CleanUx.
> 15 endpoints couvrant : auth, profil, notifications, bookings, missions.
>
> **Durée d'application** : 30 min. C'est essentiellement de la plomberie HTTP
> autour de tes services métier existants.

---

## 1. Architecture livrée

```
app/Http/Controllers/Api/
├── ApiNotificationController.php             ← /api/notifications/*
├── ProfileController.php                      ← /api/profile
│
├── Auth/
│   └── ApiAuthController.php                 ← /api/auth/{login,register,logout}
│
├── Client/
│   └── ClientBookingController.php           ← /api/client/bookings/*
│
└── Provider/
    └── ProviderMissionLifecycleController.php ← /api/provider/missions/*

tests/Feature/Phase12/
└── Phase12Test.php                            ← 20 tests

patches/
└── 01_integration.md
```

## 2. Vue d'ensemble des endpoints

### Auth (4 endpoints)
| Méthode | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/api/auth/login` | – | Login + token Sanctum |
| POST | `/api/auth/register` | – | Inscription client basique |
| POST | `/api/auth/logout` | sanctum | Révoque le token courant |
| POST | `/api/auth/logout-all` | sanctum | Révoque TOUS les tokens (multi-device logout) |

### Profile + Notifications (5 endpoints)
| Méthode | Endpoint | Description |
|---|---|---|
| GET | `/api/profile` | Profil détaillé (incluant providerProfile si applicable) |
| PATCH | `/api/profile` | Update name/phone/locale/password |
| GET | `/api/notifications` | Liste paginée (filtre `?unread_only=1`) |
| POST | `/api/notifications/{id}/read` | Marquer une notif comme lue |
| POST | `/api/notifications/read-all` | Tout marquer comme lu |

### Client bookings (5 endpoints)
| Méthode | Endpoint | Description |
|---|---|---|
| GET | `/api/client/bookings` | Mes bookings (paginé, filtre status/from/to) |
| POST | `/api/client/bookings` | Créer (scheduled ou ASAP avec dispatch auto si Phase 11 OK) |
| GET | `/api/client/bookings/{id}` | Détail |
| POST | `/api/client/bookings/{id}/cancel` | Annuler |
| GET | `/api/client/bookings/{id}/eta` | ETA prestataire (Haversine, Phase 13 améliorera) |

### Provider missions (5 endpoints)
| Méthode | Endpoint | Description |
|---|---|---|
| GET | `/api/provider/missions/active` | Mes missions actives |
| GET | `/api/provider/missions/{id}` | Détail (avec checklists, client info) |
| POST | `/api/provider/missions/{id}/start` | "Je pars" (status → en_route) |
| POST | `/api/provider/missions/{id}/arrive` | "Je suis arrivé" |
| POST | `/api/provider/missions/{id}/complete` | "J'ai terminé" |

**Total Phase 12** : 19 endpoints (15 nouveaux + 4 utilitaires).
**Total API combiné Phase 0+11+12** : 27 endpoints.

## 3. Sécurité

- **Auth Sanctum** sur tous endpoints sauf `login` et `register`
- **Rate limit** : 5 tentatives login/min par IP+email
- **Authorization** par endpoint :
  - Client : ne voit que ses bookings (customer_user_id, client_id, ou même org)
  - Provider : ne peut agir que sur ses missions (lead_provider_user_id ou assignment accepté)
  - Admin : accès total via `isPlatformAdmin()`
- **Validation stricte** : tous les inputs sont validés (lat/lng bornés, dates futures, formats)
- **Password change** : exige le `current_password`
- **CORS** : à configurer côté `config/cors.php` pour ton domaine mobile

## 4. Flow type "client mobile"

```
1. App ouvre, demande email/password
   POST /api/auth/login
   → reçoit { token, user }

2. App stocke le token (Keychain iOS / Keystore Android)
   Toutes les requêtes suivantes :
   → Authorization: Bearer <token>

3. Liste accueil
   GET /api/notifications?unread_only=1   → badge sur cloche
   GET /api/client/bookings?status=confirme  → mes RDV à venir

4. Création booking ASAP
   POST /api/client/bookings { booking_mode: "asap", ... }
   → Phase 11 dispatch automatiquement au top scorer

5. Suivi
   GET /api/client/bookings/{id}           → status mis à jour
   GET /api/client/bookings/{id}/eta       → position prestataire en live

6. Reverb websocket pour push UI :
   PrivateChannel("mission.{id}") → MissionPositionUpdated, MissionStatusUpdated
```

## 5. Flow type "prestataire mobile"

```
1. Login
   POST /api/auth/login → token

2. Go online
   POST /api/provider/presence/online { lat, lng }   (Phase 11)

3. Heartbeat /30s
   POST /api/provider/presence/heartbeat { lat, lng } (Phase 11)

4. Reception offre via push
   GET /api/provider/assignments/inbox   → voir les pendantes
   GET /api/provider/assignments/{id}    → détail offre
   POST /api/provider/assignments/{id}/accept  → 15s pour répondre

5. Mission lifecycle
   POST /api/provider/missions/{id}/start    → en_route
   POST /api/missions/{id}/tracking/start    → tracking GPS (Phase 0)
   POST /api/provider/missions/{id}/arrive   → arrived
   POST /api/provider/missions/{id}/complete → done
   POST /api/mission-tracking-sessions/{s}/stop → fin tracking

6. Logout
   POST /api/auth/logout
```

## 6. Application — étapes

### Étape 1 — Décompresser

```bash
unzip cleanux-phase12.zip
cd /chemin/vers/CleanUx
git checkout -b phase12/api-mobile

rsync -av cleanux-phase12/app/    app/
rsync -av cleanux-phase12/tests/  tests/
```

### Étape 2 — Patches manuels

Voir `patches/01_integration.md`. **L'essentiel** :

1. Remplacer le contenu de `routes/api.php` (version complète Phase 0+11+12 fournie)
2. Vérifier `config/sanctum.php` (durée tokens)
3. Configurer `config/cors.php` (autoriser ton domaine app mobile)
4. Vérifier que User model a bien `HasApiTokens` (déjà OK selon audit)

### Étape 3 — Test rapide curl

```bash
# Login
TOKEN=$(curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@local","password":"secret"}' \
  | jq -r .token)

echo "Token: $TOKEN"

# Profile
curl http://localhost:8000/api/profile \
  -H "Authorization: Bearer $TOKEN"
```

### Étape 4 — Tests automatisés

```bash
php artisan test --filter=Phase12Test
```

20 tests doivent passer :
- 5 sur Auth (login, register, logout, validation)
- 3 sur Profile (show, update, password)
- 2 sur Notifications (list, mark)
- 6 sur Client Bookings (list, show, authz, cancel, completed-cant-cancel, eta)
- 2 sur Provider Missions (list active, authz)
- 2 implicites

## 7. Stats Phase 12

| Composant | Lignes |
|---|---|
| `ApiAuthController` | ~135 |
| `ProfileController` | ~95 |
| `ApiNotificationController` | ~85 |
| `ClientBookingController` | ~310 |
| `ProviderMissionLifecycleController` | ~165 |
| Tests (20) | ~290 |
| Patches + guide | ~280 |
| **Total Phase 12** | **~1360 lignes** |

## 8. Limites & TODOs Phase 13+

### Volontairement écarté de Phase 12
- **Création booking complexe** : POST /api/client/bookings est simplifié.
  Pour zone resolution, pricing dynamique calculé, recurring series, options de
  service, multi-sites entreprise → passer par le composant Livewire web.
- **ETA précis** : Haversine simple. Phase 13 ajoutera Google Distance Matrix.
- **Stripe Connect endpoints** : payment intents, transfers, payouts → Phase 13.
- **Provider onboarding self-service** : pas de POST /api/provider/register.
  Un prestataire est créé en backoffice. Phase 13+ pour onboarding.

### Pour aller plus loin
- **OpenAPI/Swagger doc** : `composer require knuckleswtf/scribe --dev` puis
  `php artisan scribe:generate` → doc auto sur `/docs`
- **Refresh tokens** : Sanctum n'en a pas. Pour OAuth2 → Laravel Passport.
- **API versioning** : prévoir `/api/v1/...` dès maintenant si tu vises plusieurs
  versions en parallèle.
- **Pagination cursor** : pour grosses listes (>10K bookings), passer en cursor
  pagination au lieu d'offset. À faire si volume.

## 9. Checklist de PR

```
[ ] rsync app/ tests/ depuis cleanux-phase12/
[ ] routes/api.php : remplacer par la version Phase 0+11+12 (patches/01)
[ ] config/sanctum.php : vérifier expiration
[ ] config/cors.php : configurer allowed_origins pour ton app mobile
[ ] composer dump-autoload
[ ] php artisan optimize:clear
[ ] php artisan test --filter=Phase12Test → 20 tests verts
[ ] Test curl : login → profile → bookings → logout fonctionne
[ ] Doc API générée (optionnel) : php artisan scribe:generate
[ ] HTTPS configuré en prod
[ ] git commit -m "feat(api): Phase 12 — API mobile complète (auth, profile, notifications, bookings, missions)"
```

## 10. Suite

**Phase 13 — ETA + Stripe Connect complet**. Au programme :
- `EtaService` avec Google Distance Matrix
- `MissionEtaUpdated` event broadcasté à chaque tracking point
- Refonte `StripeConnectService` : capture PaymentIntent, transfer avec
  commission, webhooks Stripe (account.updated, payout.paid, charge.refunded)
- Page provider "Mes payouts" avec historique

Quand Phase 12 est validée chez toi, dis "**continuer**" et j'attaque Phase 13.
