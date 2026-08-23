# API

546 routes sous `/api`. Elles servent les deux applications mobiles et toute intégration
externe.

## Authentifier

L'API emploie **Laravel Sanctum**. Vous échangez des identifiants contre un jeton porteur, puis
vous présentez ce jeton à chaque appel.

```http
POST /api/auth/login
Content-Type: application/json

{ "email": "client@example.com", "password": "…", "device_name": "iPhone de Léa" }
```

```json
{
  "token": "12|AbCdEf…",
  "user": { "id": 42, "name": "Léa", "roles": ["client"] }
}
```

Ensuite :

```http
GET /api/client/bookings
Authorization: Bearer 12|AbCdEf…
Accept: application/json
```

### Les routes d'authentification

| Méthode | Route | Rôle |
|---|---|---|
| `POST` | `/api/auth/register` | Créer un compte |
| `POST` | `/api/auth/login` | Obtenir un jeton |
| `POST` | `/api/auth/refresh` | Renouveler un jeton qui expire |
| `POST` | `/api/auth/logout` | Révoquer le jeton courant |
| `POST` | `/api/auth/logout-all` | Révoquer tous les jetons du compte |
| `GET` | `/api/auth/me` | Le compte porté par ce jeton |
| `POST` | `/api/auth/phone/verify-request` | Envoyer un code SMS |
| `POST` | `/api/auth/phone/verify-confirm` | Valider le code |
| `POST` | `/api/auth/forgot-password` | Ouvrir une réinitialisation |

## Conventions de réponse

Les réponses portent une enveloppe :

```json
{ "data": { … } }                       // une ressource
{ "data": [ … ], "meta": { … } }        // une collection paginée
{ "ok": true }                          // une action sans contenu
{ "message": "…", "errors": { … } }     // un refus
```

### Codes employés

| Code | Sens |
|---|---|
| `200` | Succès |
| `201` | Ressource créée |
| `403` | Authentifié, mais ce compte n'a pas ce droit |
| `404` | Introuvable — ou masqué délibérément, pour ne pas révéler l'existence |
| `409` | Conflit : l'action a déjà eu lieu, ou l'état ne la permet plus |
| `422` | La charge utile est refusée par la validation ; `errors` dit quel champ |
| `429` | Limite de fréquence atteinte |

### Erreurs de validation

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": {
    "scheduled_date": ["La date doit être dans le futur."],
    "trade_id": ["Ce métier n'est pas disponible dans cette zone."]
  }
}
```

Les messages sont **traduits** dans la langue du compte. Aucune clé technique
(`validation.required`) ne doit apparaître : un test le garde.

## Les trois surfaces

### Client — `/api/client/*` (130 routes)

Ce qu'une application cliente peut faire : parcourir le catalogue, obtenir une estimation, poser
une commande, suivre son intervention, payer, noter, ouvrir un litige.

```http
GET  /api/client/trades                 le catalogue
POST /api/client/bookings/estimate      un prix, sans engagement
POST /api/client/bookings               créer une réservation
GET  /api/client/bookings/{id}          la suivre
POST /api/client/bookings/{id}/cancel   annuler — les frais sont calculés et annoncés
```

### Prestataire — `/api/provider/*` (217 routes)

Intergiciels appliqués selon la sensibilité :

| Intergiciel | Ce qu'il exige |
|---|---|
| `auth:sanctum` | Un jeton valide |
| `role:employe` | Le compte est un prestataire |
| `provider.approved` | Son dossier est validé |
| `face.verified` | Son contrôle facial est à jour, si le métier l'exige |
| `org.type:provider` | Il appartient à une société prestataire |

```http
GET  /api/provider/missions                        ses missions
POST /api/provider/missions/{id}/accept            accepter une offre
POST /api/provider/presence/heartbeat              signaler sa position
GET  /api/provider/missions/{id}/access-sheet      la fiche d'accès, après arrivée
POST /api/provider/missions/{id}/complete          clore
```

La fiche d'accès ne s'ouvre **qu'après** confirmation de présence. Avant, elle répond un refus
explicite : une fiche vide se lirait comme une donnée manquante et ferait appeler le support.

### Administration — `/api/admin/*` (119 routes)

Deux contrôles distincts, et il faut les deux :

- **`role:admin`** dit *qui* porte le jeton.
- **`api_scope:…`** dit *ce que ce jeton a le droit de faire* (`admin:read`, `admin:write`,
  `admin:everything`).

Un jeton mobile est émis sans liste de capacités : Sanctum y inscrit `*`. La portée seule ne
garde donc rien — c'est le contrôle de rôle qui ferme la porte.

## Limites de fréquence

| Surface | Limite |
|---|---|
| Authentification | 5 tentatives par minute et par IP |
| API générale | 60 appels par minute et par compte |
| Webhooks entrants | Non limités, mais signature vérifiée |

Un dépassement rend `429` avec un en-tête `Retry-After`.

## Temps réel

Les mises à jour vivantes passent par **Reverb** (WebSocket), avec repli Pusher.

| Canal | Contenu |
|---|---|
| `private-user.{id}` | Notifications personnelles |
| `private-mission.{id}` | Changements d'état d'une mission |
| `private-booking.{id}` | Suivi de course, position du prestataire |
| `presence-dispatch.{zone}` | Offres immédiates diffusées à une zone |

L'autorisation des canaux est dans `routes/channels.php`.

## Webhooks entrants

| Source | Route | Vérification |
|---|---|---|
| Stripe | `/api/webhooks/stripe` | Signature `Stripe-Signature` |
| KYC | `/api/webhooks/kyc` | Signature du fournisseur |
| SMS | `/api/webhooks/sms` | Signature du fournisseur |
| Assurance | `/api/webhooks/insurance` | Signature du fournisseur |

Tous sont **idempotents** : le même événement rejoué ne produit aucun effet supplémentaire.
Chaque famille a sa file dédiée — voir [Exploitation](exploitation.md).

## Documentation interactive

Scribe génère une référence complète depuis les annotations des contrôleurs :

```bash
php artisan scribe:generate
```

Elle est servie sur `/docs` et exporte une spécification OpenAPI dans
`public/docs/openapi.yaml`.

## Ensuite

- [Parcours](parcours.md) — l'enchaînement métier derrière ces routes
- [Exploitation](exploitation.md) — les files qui traitent les webhooks
