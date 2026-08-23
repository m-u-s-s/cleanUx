# Architecture

Cette page décrit les couches, ce qui décide quoi, et les choix structurants que vous devez
connaître avant de modifier le code.

## Les couches

```
   Navigateur                    Applications mobiles
        │                                │
   Livewire 3                        API Sanctum
   (239 composants)                  (546 routes)
        │                                │
        └───────────┬────────────────────┘
                    │
              Services (496)
        le métier vit ici, pas ailleurs
                    │
              Modèles Eloquent (297)
                    │
              MySQL · 184 migrations
```

**Le métier vit dans `app/Services`.** Un composant Livewire et un contrôleur d'API font la même
chose : ils valident une entrée, appellent un service, rendent un résultat. Quand la même règle
existe aux deux endroits, elle finit par diverger — c'est arrivé plusieurs fois sur ce dépôt, et
chaque fois c'est le chemin le moins emprunté qui portait le défaut.

## Ce qui décide quoi

| Question | Qui répond | Où |
|---|---|---|
| Ce métier est-il vendu dans cette zone ? | `trade_zone_pricing` | une ligne absente = fermé |
| Combien coûte cette commande ? | `PricingEngine` | `app/Services/OrderEngine` |
| Quelle devise ? | `CountryMarketResolver` | déduite de la position, jamais codée en dur |
| Qui peut prendre cette mission ? | `CandidateFinder` | `app/Services/Dispatch` |
| Ce prestataire est-il en ligne ? | `provider_presence` | Presence v2 fait foi |
| Qui intervient sur cette réservation ? | `Booking::intervenantId()` | trois colonnes de repli, dans cet ordre |
| Cet utilisateur a-t-il ce droit ? | `PermissionService` | jamais un `where('role', …)` écrit à la main |

Quand vous cherchez « où est décidé X », commencez par ce tableau.

## Les cinq choix structurants

### 1. Le catalogue ne connaît aucun métier en dur

`SECTEUR → MÉTIER → QUESTIONS` est entièrement en base. Ajouter un métier ne demande aucune ligne
de PHP : vous créez le métier, ses questions, ses lignes de tarif, et il apparaît.

Conséquence : un métier sans `sector_id` est **invisible**, un métier sans ligne dans
`trade_zone_pricing` est **fermé**. Ces deux cas sont silencieux et ne lèvent aucune erreur. Deux
tests les gardent.

### 2. Le prix avant l'identité

Un visiteur obtient son prix sans compte. L'identité n'est demandée qu'à la confirmation. Le
panier vit dans `order_drafts`, rattaché à un jeton de session, et devient une réservation à la
dernière étape.

Le devis est **figé** au moment du clic : recalculer plus tard exposerait le client à un montant
différent de celui qu'il a accepté.

### 3. Deux moteurs de mission

Une réservation devient une ou plusieurs missions. Le mode décide du moteur :

- **Immédiat (`asap`)** — chaîne d'offres, TTL 20 secondes, vagues successives, diffusion
  temps réel. Le premier prestataire qui accepte emporte la mission.
- **Planifié (`scheduled`)** — attribution différée, le prestataire reçoit une proposition.

Une seule porte d'entrée : `DispatchEngine::dispatchBooking()`. Il y en a eu deux pendant un
temps, et une même course sortait par les deux — deux prestataires se déplaçaient.

### 4. L'argent va directement au prestataire

Stripe Connect en **charge à destination** : le client paie, Stripe crédite le compte du
prestataire et prélève la commission de la plateforme au passage.

Il n'y a donc **pas de virement à exécuter**. Un `Stripe\Payout` déclenché par la plateforme
paierait une seconde fois. Le registre de règlement atteste ce qui s'est passé ; il ne pilote
rien.

### 5. La géographie décide de la devise et de la langue

`DeviseParPays` connaît 61 devises. La devise d'une commande vient de la zone de service, pas
d'un défaut de colonne. Sept langues sont configurées, six actives ; le catalogue est traduit
dans les cinq langues actives autres que le français.

## Les surfaces

| Surface | Technologie | Entrée |
|---|---|---|
| Web client | Livewire + Blade | `routes/web.php` |
| Web administration | Livewire | `routes/admin.php` |
| API client | Sanctum | `routes/api/client.php` |
| API prestataire | Sanctum | `routes/api/provider.php` |
| API administration | Sanctum + portée de rôle | `routes/api/admin.php` |
| Temps réel | Reverb (WebSocket) | `routes/channels.php` |

Les deux applications mobiles (`mobile/client`, `mobile/provider`) consomment l'API. Elles ne
partagent aucun code PHP avec le serveur : le contrat est le JSON.

## Les gardes

Ces règles sont vérifiées par des tests qui balaient **tout** le dépôt, pas une liste tenue à la
main :

| Garde | Ce qu'il empêche |
|---|---|
| `tests/Feature/Schema/LesModelesConcordentAvecLeSchema` | Un `$fillable` ou un `$casts` qui désigne une colonne inexistante |
| `tests/Feature/Catalogue/ChaqueMetierAppartientAUnSecteur` | Un métier tarifé mais invisible |
| `tests/Feature/Catalogue/LeCatalogueEstTraduit…` | Un catalogue qui redevient monolingue |
| `tests/Feature/Ops/ConfigParityCheck` | Un déploiement qui migre avant d'avoir validé |
| `tests/Feature/Devops/AucunPortailNestPassif` | Un job de CI dont le verdict ne compte plus |

Quand vous ajoutez une règle structurante, ajoutez le garde qui la tient.

## Ensuite

- [Domaine](domaine.md) — le vocabulaire précis
- [Parcours](parcours.md) — la chaîne complète, du clic au paiement
- [Données](donnees.md) — comment le schéma est organisé
