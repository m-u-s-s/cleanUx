# Conventions

Ce qu'on attend d'une contribution acceptée ici.

## Langue

Le code, les commentaires, les noms de migration et les messages de commit sont en **français**.
Les termes techniques établis restent en anglais : `Controller`, `Service`, `Repository`,
`middleware`, `booking`.

Les identifiants existants ne sont pas renommés pour la forme. `bookings.customer_user_id` reste
tel quel.

## Nommer

| Objet | Forme | Exemple |
|---|---|---|
| Classe | `PascalCase`, nom de ce qu'elle fait | `OrderConfirmationService` |
| Méthode | `camelCase`, verbe | `deviseAttendue()`, `dispatchBooking()` |
| Migration | `snake_case` français, verbe à l'infinitif | `poser_les_dix_neuf_dernieres_cles` |
| Test | `test_` + phrase qui affirme | `test_une_zone_marocaine_donne_le_dirham` |
| Colonne | `snake_case`, sans abréviation | `cancellation_fee_amount` |

Un nom de test est une **affirmation**, pas une étiquette. `test_booking_works` ne dit rien ;
`test_une_zone_marocaine_donne_le_dirham` dit ce qui est vrai.

## Commenter

Un commentaire tient en **deux lignes au plus**. Il dit ce que fait le code, ce que c'est, et à
quoi ça sert.

```php
// La devise suit la position du client, jamais un défaut de colonne.
// Deux portes d'entrée : deviseAttendue() depuis une adresse, effectiveCurrency() depuis un contexte.
```

**Ne commentez pas l'histoire.** Le défaut que vous corrigez, comment vous l'avez mesuré et
pourquoi la correction prend cette forme appartiennent au **message de commit**. Le code dit ce
qu'il fait ; Git dit pourquoi il en est arrivé là.

```bash
git log --grep="devise" -p        # retrouver le raisonnement
git blame app/Services/…/X.php    # savoir quel commit a écrit cette ligne
```

Les annotations PHPDoc — `@param`, `@return`, `@var`, `@property` — ne comptent pas dans ces deux
lignes. Elles ne sont pas de la documentation : l'analyse statique les applique. Une annotation
fausse **aveugle** PHPStan.

## Écrire du code

### Le métier vit dans un service

Un contrôleur et un composant Livewire valident, appellent, rendent. Ils ne décident pas. Quand
la même règle existe aux deux endroits, elle diverge — et c'est le chemin le moins emprunté qui
porte le défaut.

### Une notion, une source

Deux tables, deux colonnes ou deux services qui décident de la même chose finissent par se
contredire. Avant d'ajouter une source, cherchez celle qui existe.

### L'argent ne passe pas par l'assignation en masse

Les colonnes de montant sont hors `$fillable`, délibérément : une charge utile de requête ne doit
pas pouvoir fixer un prix. Employez `forceFill([...])->save()`, avec une intention explicite.

### Mesurez avant de corriger

Ne vous fiez ni à la documentation, ni aux commentaires, ni à votre souvenir. Ils vieillissent.
Lisez le code, les migrations, les routes. Citez le fichier et la ligne.

## Avant de proposer

```bash
vendor/bin/pint                                  # style — porte dure de la CI
vendor/bin/phpstan analyse --memory-limit=2G     # sans argument de chemin
composer test:parallele                          # la suite complète
```

Les trois doivent passer. Pint échappe à PHPStan comme à la suite : douze fichiers ont déjà été
rouges au portail alors que tout passait en local.

## Commits

Le message porte le raisonnement. Il répond à trois questions :

1. **Qu'est-ce qui n'allait pas ?** Le symptôme, pas la solution.
2. **Comment le sait-on ?** La mesure, avec ses chiffres.
3. **Pourquoi cette forme ?** Ce qui a été écarté, et pour quelle raison.

```
fix(annulations): le centre d'analyse annonçait 0 EUR de frais, depuis toujours

`bookings.cancellation_fee_amount` existe depuis la création de la table, et
`CancellationReasonsCenter` en fait `SUM(COALESCE(…, 0))`. AUCUN code ne l'écrivait.

Les frais vivaient dans `metadata['cancellation_fee']` (chemin v1) et dans
`booking_cancellations_v2` (chemin v2). Les deux moteurs sont branchés.

Le détail reste où il est ; la colonne devient le résumé agrégeable, écrit par les
deux. Une seule écriture, deux lectures — pas deux vérités.

Couverture prouvée par sabotage : la correction retirée, exactement les deux tests
qui la gardent tombent.
```

Préfixes : `feat`, `fix`, `perf`, `refactor`, `test`, `docs`, `chore`.

**Dites ce qui n'a pas été fait.** Un test qui échoue se rapporte avec sa sortie. Une partie
laissée de côté se nomme, avec son motif.

## Revue

Ce qu'un relecteur cherche :

| Question | Signal d'alerte |
|---|---|
| Le test mesure-t-il quelque chose ? | Il passerait au vert si le code était cassé |
| Un test de refus a-t-il son témoin ? | Sans témoin, il mesure peut-être une panne |
| L'erreur nomme-t-elle le problème ? | « Failed asserting that false is true » |
| Une notion a-t-elle acquis une seconde source ? | Deux endroits décident du même fait |
| Le commentaire raconte-t-il l'histoire ? | Elle appartient au commit |

## Ensuite

- [Tests](tests.md) — écrire un test qui prouve quelque chose
- [Architecture](architecture.md) — les choix qu'il ne faut pas défaire par accident
