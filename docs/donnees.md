# Données

184 migrations, 303 tables. Cette page dit où vit quoi, comment migrer sans casser, et les
pièges qui ont déjà coûté des heures.

## Les tables du cœur

| Table | Colonnes | Ce qu'elle porte |
|---|---|---|
| `bookings` | 159 | La réservation — le contrat client |
| `missions` | 61 | Le travail de terrain |
| `users` | 55 | Les comptes, tous rôles confondus |
| `trades` | 45 | Le catalogue des métiers |
| `order_drafts` | 39 | Le panier, avant identité |
| `organization_accounts` | 38 | Les sociétés, clientes comme prestataires |
| `service_zones` | 25 | Le maillage commercial |
| `mission_assignments` | 19 | Qui fait quoi sur une mission |
| `trade_zone_pricing` | 18 | **La source unique de l'ouverture commerciale** |
| `provider_presence` | 15 | La position vivante des prestataires |
| `sectors` | 13 | Les familles de services |

## Migrations

### Écrire une migration

Une migration crée ou modifie **une** notion. Nommez-la par ce qu'elle fait, en français :

```
2026_09_21_090000_poser_les_dix_neuf_dernieres_cles.php
2026_08_11_090000_supprimer_la_table_miroir_rendez_vous.php
```

Dans le fichier, expliquez **ce que vous créez et pourquoi cette forme** — pas l'historique du
défaut, qui appartient au commit.

### Vérifier avant de fusionner

```bash
php artisan migrate --pretend        # affiche le SQL sans l'exécuter
```

**`--pretend` ne prouve pas grand-chose.** Les `select` ne s'exécutent pas : une garde
`Schema::hasColumn()` rend donc faux, et tout le bloc qu'elle protège est sauté sans être
affiché. Pour une vraie vérification, migrez sur une base d'essai jetable et comparez le schéma.

### Le harnais d'empreinte

Quand une modification touche beaucoup de migrations, prouvez qu'elle ne change rien :

1. Prenez une empreinte du schéma vivant — tables, colonnes avec type et nullabilité, index avec
   leurs colonnes **ordonnées**, clés étrangères avec leurs actions.
2. Rejouez `migrate:fresh` sur une base d'essai.
3. Comparez les deux empreintes. L'écart doit être **exactement** ce que vous vouliez.

Vérifiez d'abord que `migrate:fresh` reproduit déjà la base vivante à zéro écart. Sans ce
contrôle préalable, la comparaison finale ne prouve rien.

## Semeurs

59 semeurs, organisés en profils :

| Profil | Commande | Ce qu'il pose |
|---|---|---|
| `demo` | `php artisan db:seed` | Référentiel + comptes et données de démonstration |
| `reference` | `db:seed --class=ReferencePlatformSeeder` | Le référentiel seul : géographie, catalogue, zones, prix |
| `production` | `db:seed --class=ProductionBootstrapSeeder` | Le strict nécessaire pour une plateforme neuve |

### L'ordre compte

`ReferencePlatformSeeder` appelle ses semeurs dans un ordre que vous ne pouvez pas changer sans
mesurer :

```
BelgiumGeographySeeder      les pays, régions, codes postaux
TradeSeeder                 les métiers — SANS secteur, ils n'existent pas encore
ServiceCatalogSeeder        les services
ZoneManagementSeeder        les zones
OrderEngineCatalogSeeder    les secteurs et leurs questionnaires
TradeZonePricingSeeder      la grille (métier × zone)
CourseCatalogSeeder         le métier de course, avec son tarif kilométrique
TradeSectorLinkSeeder       rattache les métiers restants — EN DERNIER, quand tout existe
CatalogueTraductionsSeeder  les noms dans les cinq langues actives
```

Un semeur ajouté au mauvais rang ne trouve pas ce dont il dépend, et échoue **silencieusement**
plutôt que bruyamment.

### Règles

- **Idempotent.** `updateOrCreate` sur une clé stable — un slug, jamais un libellé.
- **Non destructif.** Ne jamais écraser une saisie d'exploitant. Un semeur propose ; il ne
  reprend pas la main.
- **Une seule source.** Ne définissez pas le même objet dans deux semeurs : le catalogue
  dépendrait de qui parle en dernier.

## Pièges vérifiés

Chacun a coûté du temps sur ce dépôt.

### La suite tourne sur SQLite, l'application sur MySQL

Une classe entière de défauts est **invisible** aux tests :

| MySQL | SQLite |
|---|---|
| Rejette un identifiant inconnu | Le prend pour une **chaîne littérale** — aucune erreur |
| Impose la longueur des noms d'index (64 caractères) | Accepte tout |
| Réordonne les clés d'une colonne JSON | Les garde |
| Mode strict : refuse une valeur trop longue | Tronque |

Conséquence : comparer une colonne JSON castée avec `===` est toujours faux en production, et un
`where('colonne_supprimee', …)` passe les tests puis casse en ligne.

### Une colonne hors de `$fillable` est écartée en silence

`Model::create(['montant' => 100])` ignore `montant` s'il n'est pas assignable en masse. Aucune
erreur en production — le refus explicite n'est armé qu'en développement.

Pour les colonnes d'argent, c'est **délibéré** : une charge utile de requête ne doit pas pouvoir
fixer un montant. Employez `forceFill([...])->save()` avec une intention explicite.

### Un défaut SQL n'existe pas en mémoire

Après un `create()`, une colonne hors `$fillable` qui porte un `default()` en base vaut `null`
sur l'objet PHP. Le modèle que vous venez de créer **ment**. Relisez-le avec `fresh()`.

### Les jumelles de `bookings`

Quinze paires français/anglais. Une écriture par le **constructeur de requêtes** ne déclenche pas
le trait qui les synchronise : elle doit citer les deux côtés.

Cherchez `DB::table('bookings')` avant de croire une colonne tenue à jour.

### Le nom `fix_` ne veut pas dire correctif

Douze migrations nommées `fix_*` ou `add_*` **créent** des tables. Ce sont des origines.
Vérifiez `Schema::create` avant de classer une migration sur son nom.

## Ce qui reste ouvert, mesuré

| Sujet | État |
|---|---|
| 92 colonnes `*_id` sans clé étrangère | 65 sont des identifiants externes (Stripe, KYC), 21 polymorphes, 6 écartées avec motif |
| `bookings` à 159 colonnes | 30 sont de la duplication FR/EN ; effondrer une paire demande un arbitrage FR ou EN |
| 6 colonnes `recurrence_*` | Écrites par la création de réservation, relues par personne — le moteur lit `recurring_booking_series` |
| `users.role` | Héritage encore lu en repli par 4 prédicats ; 115 fichiers de test l'écrivent |

Ces points sont documentés parce qu'ils sont **connus et assumés**, pas oubliés.

## Ensuite

- [Domaine](domaine.md) — ce que ces tables représentent
- [Tests](tests.md) — les gardes qui tiennent le schéma
