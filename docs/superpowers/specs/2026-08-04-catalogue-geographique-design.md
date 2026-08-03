# Catalogue géographique : Pays → Zones → Secteurs & métiers

**Date :** 2026-08-04
**État :** conception validée, lot 1 approuvé (navigation complète, sans câblage tarifaire)

## Le besoin

Aujourd'hui `/admin/catalogue` ouvre directement sur les secteurs et métiers du moteur de commande.
Un métier existe partout ou nulle part, et à un seul prix.

On veut deux choses qui n'ont rien sur quoi s'appuyer :

1. **qu'un pays reçoive des secteurs ou des métiers que d'autres n'ont pas** ;
2. **qu'un même métier ait un prix différent selon la zone**, une zone très demandée coûtant plus
   cher qu'une zone calme.

## Ce qui existe déjà — mesuré, pas supposé

C'est le point de départ le plus important de cette conception : **presque tout le code est là, et
presque toutes les données manquent.** Une lecture du schéma seul aurait conclu l'inverse.

| Élément | État réel |
|---|---|
| Table `countries` | Existe, bien formée (`iso_code`, `is_active`, `booking_enabled`, `market_stage`, devise, locale, fuseau). **1 ligne** : la Belgique |
| `service_zones` | 6 lignes, toutes rattachées à `country_id = 1`. Porte déjà `status`, `is_bookable`, `is_visible`, `activated_at`, `deactivated_at` |
| `trades` / `sectors` | 15 et 4 lignes. **Aucune colonne géographique** — un métier est mondial |
| `trade_zone_pricing` | Table complète (`base_rate_cents`, `surge_multiplier`, `min`, `max`, `is_active`). **0 ligne** |
| `trade_zone_settings` | Table concurrente (`is_active`, `price_multiplier`). **0 ligne** |
| `OrderEngine\PricingEngine` | Accepte un `zone_multiplier`… que **personne ne fournit**. Il vaut toujours 1,0 |
| `ZoneCoverageService` | Résolution code postal → zone, complète. Appelée par l'**ancien** parcours seulement |
| `service_zone_postal_code` | Le pivot qui la nourrit : **0 ligne** |
| `order_drafts` | Ne porte **ni zone ni code postal** |

### Ce que ce tableau implique

Il y a **trois chemins de prix par zone**, tous morts pour le parcours de commande, et deux tables
concurrentes vides. Le prix d'un couple métier × zone était réglable à deux endroits sans qu'aucun
ne serve.

Et surtout : **la chaîne est coupée au dernier maillon.** Tant que le brouillon de commande ne
résout pas de zone, une grille tarifaire par zone n'a rien à quoi s'appliquer. Ce n'est pas un
détail d'implémentation, c'est un lot en soi.

## Décisions

| Question | Décision |
|---|---|
| Où se décide qu'un métier existe à tel endroit ? | **Par zone uniquement.** Le pays ne sert qu'à créer et organiser les zones. Aucun héritage pays → zone |
| Comment le prix varie-t-il ? | **Grille tarifaire complète par zone** : prix de base, surge, plancher et plafond, par métier × zone |
| Que fait-on des chemins concurrents ? | **On tranche.** Un seul survit, les autres sont supprimés avec un test qui interdit leur retour |

La première décision a une conséquence directe qu'il faut avoir en tête : c'est parce que
l'activation se fait **par zone** qu'un même métier peut porter un prix par zone. Les deux ne sont
pas deux fonctionnalités mais une seule — l'existence d'une ligne `(métier, zone)`.

## Architecture

### Navigation

Trois niveaux sous la même route, chacun un cran plus précis :

```
/admin/catalogue                       →  Pays
/admin/catalogue/{pays}                →  Zones de ce pays
/admin/catalogue/{pays}/{zone}         →  Secteurs & métiers de cette zone
```

L'écran actuel n'est pas remplacé : il **descend d'un cran** et devient le niveau 3, contextualisé
à une zone. Ce qu'il montre déjà — ce qui bloque la publication d'un métier, ce qui attend d'être
mis en ligne — est conservé tel quel, c'est précisément ce qui lui donne sa valeur.

**Conséquence à assumer :** les liens existants vers `/admin/catalogue` arriveront désormais sur la
liste des pays. Avec un seul pays et six zones, deux clics séparent l'ancien écran de sa nouvelle
place.

### Le doublon `/admin/zones`

`GestionZones` fait déjà le CRUD des zones, avec filtres région / province / commune, réglages de
réservabilité et de visibilité, et les traits `ManagesZonesData`,
`PerformsZoneManagementActions`, `ManagesTradeZoneSettings`.

**Le niveau 2 EST ce composant**, monté avec le pays pré-filtré et verrouillé. Écrire un second
écran de zones garantirait qu'ils divergent : l'un recevrait un filtre, l'autre un réglage, et
personne ne saurait plus lequel fait foi. `/admin/zones` reste accessible comme vue transversale
tous pays.

### Modèle de données

`trade_zone_pricing` devient la **source unique** du couple métier × zone :

- **l'existence de la ligne** dit « ce métier est offert dans cette zone » ;
- `is_active` est l'interrupteur, sans perdre la grille quand on éteint ;
- `base_rate_cents`, `surge_multiplier`, `min_price_cents`, `max_price_cents` sont la grille.

`trade_zone_settings` est supprimée. `SurgePricingEngine` écrira dans
`trade_zone_pricing.surge_multiplier`, qui existe déjà et porte exactement la même chose.

## Règles

### Supprimer n'est pas désactiver

Un pays qui porte des zones, une zone qui porte des réservations : **refus motivé**, jamais de
cascade. Le bouton « supprimer » n'apparaît que si rien ne dépend de l'objet ; sinon l'interface
propose « désactiver » et dit ce qui bloque, avec le compte (« 6 zones rattachées »).

Une suppression en cascade sur ces objets détruirait de l'historique de facturation.

### Désactiver un pays ne touche pas ses zones

Symétrique de la règle demandée pour les zones. Éteindre la Belgique rend ses zones non réservables
**sans changer leur statut propre** : la réactivation restaure exactement l'état d'avant, y compris
les zones qui étaient déjà éteintes pour leur propre raison.

L'implémentation est donc une **lecture** (`zone réservable ET pays actif`), pas une écriture en
chaîne sur les zones.

### Le pays n'est pas un filtre sur les métiers

Décision assumée : il n'existe pas de table `country_trade`. Un pays « a » un métier si au moins
une de ses zones l'a. C'est un calcul, pas un réglage — donc rien à tenir à jour et rien qui puisse
se contredire.

## Périmètre du lot 1

**Dans le lot :**

- CRUD des pays : ajouter, supprimer (sous condition), activer, désactiver ;
- le clic sur un pays mène à ses zones ;
- CRUD des zones dans le contexte du pays, sans effet sur le pays ;
- le clic sur une zone ouvre les secteurs et métiers **de cette zone** ;
- l'activation d'un métier par zone, stockée dans `trade_zone_pricing` (présence + `is_active`).

**Explicitement hors lot, et il faut le dire franchement :**

- le moteur de commande ne lit pas encore `trade_zone_pricing`. **Après ce lot, activer ou
  désactiver un métier dans une zone n'a aucun effet sur ce que voit un client.** L'écran est
  exact, il n'est pas encore branché ;
- les champs de prix ne sont pas éditables au niveau 3 ;
- `trade_zone_settings` n'est pas encore supprimée ;
- la résolution code postal → zone reste absente du moteur de commande.

## Lots suivants, identifiés

**Lot 2 — câblage tarifaire.** `trade_zone_pricing` devient la source unique lue par
`OrderEngine\PricingEngine`. `trade_zone_settings` meurt, `SurgePricingEngine` migre.

Ce lot porte **le risque principal de tout le chantier** : 15 métiers × 6 zones, et la table est à
0 ligne. Brancher la résolution telle quelle rendrait tous les métiers indisponibles partout, d'un
coup, en production. Une migration doit semer les 90 lignes depuis les prix actuels des métiers,
avec un test qui prouve que le jour de la bascule **aucun prix ne bouge**.

**Lot 3 — résolution de zone.** Peupler `service_zone_postal_code`, faire porter une zone au
brouillon de commande, brancher `ZoneCoverageService` sur le moteur. Sans ce lot, les grilles
saisies restent décoratives.

## Ce qui pourrait mal tourner

**Le mode d'échec le plus probable** est silencieux : livrer le lot 1, voir un bel écran
d'activation par zone, et croire la fonctionnalité acquise. Elle ne l'est qu'au lot 3. La
documentation de l'écran doit le dire à l'endroit où quelqu'un le lira — dans l'interface, pas
seulement ici.

**Le second** est la bascule du lot 2 décrite ci-dessus : une classe de défaut où tout est vert et
où le catalogue client est vide.

**Le troisième** est le retour du doublon : quelqu'un remplit `trade_zone_settings` après sa mort
supposée. D'où le test qui interdit son retour plutôt qu'un simple `drop`.
