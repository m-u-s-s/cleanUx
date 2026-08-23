# Tests

7 415 tests dans 1 023 fichiers. Cette page dit comment les lancer, et comment en écrire un qui
prouve quelque chose.

## Lancer

```bash
composer test:parallele          # tout, ~13 min sur 16 processus
php artisan test                 # tout, en séquentiel — ~55 min
php artisan test tests/Feature/OrderEngine        # un dossier
php artisan test --filter=devise                  # par nom
```

**Employez le parallèle par défaut.** Le séquentiel n'a qu'un usage : lire une sortie ordonnée
quand vous traquez un échec précis.

Le parallèle passe `--no-coverage` parce que le bloc `<coverage>` de `phpunit.xml` fait échouer
ParaTest **au démarrage** sur tout poste sans Xdebug ni PCOV.

## Deux règles absolues

### Ne modifiez aucun fichier pendant qu'une suite tourne

Seize processus lisent les fichiers en même temps. Une édition en cours de route produit des
échecs qui n'ont rien à voir avec votre changement, et vous les chercherez longtemps.

Encadrez chaque exécution d'un contrôle de l'état du dépôt.

### Un test de refus exige un témoin positif

Un test qui affirme « ceci est refusé » passe au vert quand le chemin est **cassé pour tout le
monde** — une route disparue, un intergiciel mal branché.

```php
// Le refus
$this->assertSame([], $ouverts, 'Ces lectures s’ouvrent à un simple exécutant.');

// LE TÉMOIN : sans lui, un 403 universel passerait pour une garde qui fonctionne
$this->assertSame([], $fermes, 'Le dirigeant devrait lire ces quatre écrans.');
```

## Écrire un test utile

### Posez la bonne question

Demandez **« la fonction rend-elle la même valeur ? »**, pas « l'utilisateur a-t-il reçu quelque
chose ? ».

| Faible | Fort |
|---|---|
| `assertOk()` | `assertJsonPath('data.currency', 'MAD')` |
| `assertNotNull($total)` | `assertSame(4980, $total)` |
| `assertNotEmpty($lignes)` | `assertCount(3, $lignes)` |

`assertOk()` seul dit qu'une page répond. Il ne dit pas qu'elle répond juste.

### Rapportez toutes les erreurs, pas la première

Une assertion **à l'intérieur** d'une boucle interrompt la méthode au premier écart. Les suivants
restent invisibles : vous corrigez, relancez, découvrez le suivant.

```php
// ✗ Une erreur montrée sur N
foreach ($contextes as $c) {
    $this->assertContains('profile.show', $this->routesDe($c));
}

// ✓ Toutes nommées d'un coup
$manques = [];
foreach ($contextes as $c) {
    if (! in_array('profile.show', $this->routesDe($c), true)) {
        $manques[] = "{$c} → profile.show";
    }
}
$this->assertSame([], $manques, 'Ces modules manquent à ces rôles.');
```

Sur une matrice — droits, taux de TVA, devises, langues — c'est la différence entre une
correction et autant d'exécutions qu'il y a de cas.

### Prouvez que votre test mesure quelque chose

Écrivez le test, puis **cassez volontairement** ce qu'il garde. S'il reste vert, il ne mesure
rien. S'il tombe, lisez le message : nomme-t-il le problème assez précisément pour qu'on sache
quoi corriger ?

## Les gardes de structure

Ces tests balaient **tout** le dépôt, pas une liste tenue à la main. Un fichier ajouté demain y
entre sans que personne y pense.

| Test | Ce qu'il empêche |
|---|---|
| `Schema/LesModelesConcordentAvecLeSchema` | `$fillable`, `$casts`, fabriques, annotations qui désignent une colonne inexistante |
| `Catalogue/ChaqueMetierAppartientAUnSecteur` | Un métier tarifé mais invisible |
| `Catalogue/LeCatalogueEstTraduit…` | Un catalogue qui redevient monolingue |
| `Catalogue/LesIconesDuCatalogueExistent` | Une icône qui retombe silencieusement sur un cercle |
| `I18n/LeFormatageMonetaireNeMentPas` | Une devise affichée en euros |
| `Ops/ConfigParityCheck` | Un déploiement qui migre avant d'avoir validé |
| `Devops/AucunPortailNestPassif` | Un job de CI dont le verdict ne compte plus |

## Fabriques

297 modèles, et leurs fabriques dans `database/factories`.

Une fabrique doit poser des clés qui sont de **vraies colonnes**. Quatre avaient été écrites
contre un schéma imaginaire — elles n'avaient aucun appelant, et attendaient le premier test qui
les emploierait. Un garde les vérifie toutes.

### Ouvrir le catalogue dans un test

Une zone plus un métier **sans ligne de tarif** donnent un service fermé. Le trait
`OuvreLeCatalogue` pose ce qu'il faut :

```php
use Tests\Concerns\OuvreLeCatalogue;
```

Sans lui, un test de commande échoue pour une raison correcte et parfaitement incompréhensible.

## Pièges de mesure

| Piège | Ce qui se passe |
|---|---|
| `Http::fake()` global du `TestCase` | Il gagne sur le vôtre — le test passe pour une mauvaise raison |
| Deux `now()` séparés | Le test tombe seulement dans les dix minutes suivant minuit |
| `BookingFactory` | `synchronizeStructuredContext()` écrase `ville` et `code_postal` **après** vos surcharges |
| `#[Computed]` de Livewire | Le cache ne fonctionne que sur l'accès propriété, pas sur `$this->truc()` |
| `boot{NomDuComposant}` | Inerte. Seuls `boot()` et `boot{Trait}` sont appelés |

## Analyse statique

```bash
vendor/bin/phpstan analyse --memory-limit=2G
```

Niveau 6, avec Larastan. **Lancez-la sans argument de chemin** : une analyse partielle rate les
erreurs de résolution entre fichiers.

512 Mo ne suffisent plus. Si vous la lancez pendant que la suite parallèle tourne, elle sature et
rend un « child process error » qui n'a rien à voir avec votre code.

Les annotations `@property` ne sont **pas** de la documentation : elles apprennent à l'outil que
la lecture est valide. Une annotation fausse aveugle l'analyse — trois défauts réels s'y étaient
logés.

## Style

```bash
vendor/bin/pint
```

Pint est une **porte dure de la CI**. Le style échappe à PHPStan comme à la suite : douze
fichiers ont déjà été rouges au portail alors que tout passait en local.

## Ensuite

- [Conventions](conventions.md) — ce qu'on attend d'une contribution
- [Données](donnees.md) — les pièges de schéma que les tests ne voient pas
