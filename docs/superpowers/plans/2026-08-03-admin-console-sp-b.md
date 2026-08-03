# Console d'administration mobile — Sous-projet B

> **Prérequis :** le sous-projet A est livré (garde `api_admin`, registre des 99 routes, annuaire,
> accueil, `AdminNavigator`). Ce plan s'appuie sur `config/admin_console.php` et sur les écrans
> `AdminDirectoryScreen` / `AdminResourceScreen` déjà en place.

**But :** un moteur de console qui rend nativement un domaine d'administration à partir d'un
descripteur serveur, et les dix premiers descripteurs qui s'en servent.

**Architecture :** chaque domaine expose un `AdminResource` — un objet PHP qui décrit ses colonnes,
ses filtres, ses actions et son formulaire, et qui délègue toute logique métier aux services
existants. Un contrôleur générique sert ces descripteurs et leurs données sous un contrat unique ;
trois écrans React Native les rendent. Ajouter un domaine devient : écrire un descripteur.

**Pile :** Laravel 12 / Sanctum / PHPUnit ; Expo SDK 56, React Native 0.85, React Query.

## Contraintes globales

- Reprendre les contraintes du sous-projet A : Expo SDK 56 documenté, commentaires en français
  expliquant le pourquoi, `phpstan analyse` complet, contre-épreuve MySQL, jamais de battement de
  présence dans l'espace admin.
- **Le moteur ne réimplémente aucune règle métier.** Un descripteur qui aurait besoin d'une règle
  nouvelle est le signe qu'il faut un écran sur-mesure (sous-projet C), pas une règle dupliquée.
- **Un descripteur ne peut pas mentir.** Un module déclaré `descriptor` dans le registre sans
  classe enregistrée fait échouer la suite (tâche 2).
- Toute action destructive porte `destructive: true` et exige une confirmation côté mobile.

---

### Tâche 1 : le contrat `AdminResource`

**Fichiers :**
- Créer : `app/Admin/Console/AdminResource.php` (interface)
- Créer : `app/Admin/Console/Column.php`, `Filter.php`, `Action.php`, `Field.php` (objets de valeur)
- Test : `tests/Feature/Admin/Console/AdminResourceContractTest.php`

**Interfaces produites :**

```php
interface AdminResource
{
    /** Clé du module dans config/admin_console.php. */
    public function key(): string;

    /** @return Builder<Model> la requête de base, déjà scopée. */
    public function query(): Builder;

    /** @return list<Column> */
    public function columns(): array;

    /** @return list<Filter> */
    public function filters(): array;

    /** @return list<string> champs triables */
    public function sorts(): array;

    /** @return list<Action> */
    public function actions(): array;

    /** @return list<Field> champs de création/édition ; vide = lecture seule */
    public function formFields(): array;

    /** @return array<string, mixed> une ligne telle que le mobile l'affiche */
    public function toRow(Model $model): array;

    /** @return array<string, mixed> le détail d'une ligne */
    public function toDetail(Model $model): array;
}
```

`Column` porte `key`, `label`, `type` parmi `text|money|date|datetime|badge|bool|number`.
`Filter` porte `key`, `label`, `type` parmi `search|select|date_range|bool`, `options`.
`Action` porte `key`, `label`, `destructive`, `confirmLabel`, et une closure d'exécution.
`Field` porte `key`, `label`, `type`, `rules` (règles de validation Laravel), `options`.

- [ ] **Étape 1** : écrire le test qui fige la forme sérialisée de chaque objet de valeur.
- [ ] **Étape 2** : lancer, constater l'échec.
- [ ] **Étape 3** : écrire l'interface et les quatre objets de valeur, chacun avec un `toArray()`.
- [ ] **Étape 4** : relancer, commit.

---

### Tâche 2 : le registre et son test anti-mensonge

**Fichiers :**
- Créer : `app/Admin/Console/ResourceRegistry.php`
- Créer : `app/Providers/AdminConsoleServiceProvider.php`
- Test : `tests/Feature/Admin/Console/ResourceRegistryTest.php`

**Interfaces produites :** `ResourceRegistry::for(string $key): ?AdminResource`,
`ResourceRegistry::keys(): list<string>`.

- [ ] **Étape 1 : écrire le test qui interdit de mentir**

```php
public function test_tout_module_declare_descriptor_a_bien_un_descripteur(): void
{
    $declares = collect(config('admin_console.modules'))
        ->where('coverage', 'descriptor')
        ->pluck('key');

    $manquants = $declares->reject(fn ($k) => app(ResourceRegistry::class)->for($k) !== null);

    // Un module annoncé disponible dans l'annuaire mais sans descripteur ouvrirait un écran
    // vide — pire que l'annoncer « à venir », parce que personne ne le remarque.
    $this->assertSame([], $manquants->values()->all());
}

public function test_tout_descripteur_enregistre_correspond_a_un_module_connu(): void
{
    $connus = array_column(config('admin_console.modules'), 'key');

    foreach (app(ResourceRegistry::class)->keys() as $key) {
        $this->assertContains($key, $connus, "Descripteur orphelin : {$key}");
    }
}
```

- [ ] **Étape 2** : lancer, constater l'échec.
- [ ] **Étape 3** : écrire le registre + le service provider qui l'enregistre.
- [ ] **Étape 4** : relancer, commit.

---

### Tâche 3 : les endpoints génériques

**Fichiers :**
- Créer : `app/Http/Controllers/Api/Admin/Console/ResourceController.php`
- Modifier : `routes/api/admin.php`
- Test : `tests/Feature/Api/Admin/Console/ResourceEndpointsTest.php`

**Contrat :**

```
GET    /api/admin/console/{resource}            descripteur + page de résultats
GET    /api/admin/console/{resource}/{id}       détail
POST   /api/admin/console/{resource}            création
PATCH  /api/admin/console/{resource}/{id}       mise à jour
DELETE /api/admin/console/{resource}/{id}       suppression
POST   /api/admin/console/{resource}/{id}/actions/{action}
```

- [ ] **Étape 1 : écrire les tests** — un descripteur factice enregistré pour le test ; vérifier
  la pagination, l'application des filtres, le refus d'un tri non déclaré, la validation du
  formulaire, l'exécution d'une action, le 404 sur ressource inconnue, le 403 sur non-admin.
- [ ] **Étape 2** : lancer, constater l'échec.
- [ ] **Étape 3 : écrire le contrôleur.** Points de vigilance :
  - un tri non déclaré dans `sorts()` est **refusé**, jamais passé à la requête (injection) ;
  - un filtre inconnu est ignoré silencieusement, pas appliqué au hasard ;
  - la validation se fait avec les `rules` du descripteur, et les erreurs sortent au format
    `{ok:false, errors:{champ:[…]}}` que l'application sait déjà lire ;
  - la pagination est **par curseur** et non par offset : une console sur des tables qui bougent
    (bookings, audit) sauterait des lignes avec un offset.
- [ ] **Étape 4** : relancer, commit.

---

### Tâche 4 : les trois écrans natifs

**Fichiers :**
- Créer : `mobile/provider/src/admin/console/ResourceListScreen.tsx`
- Créer : `mobile/provider/src/admin/console/ResourceDetailScreen.tsx`
- Créer : `mobile/provider/src/admin/console/ResourceFormScreen.tsx`
- Créer : `mobile/provider/src/admin/console/hooks.ts`, `fields.tsx`
- Modifier : `mobile/provider/src/navigation/RootNavigator.tsx` (remplacer
  `AdminResourceScreen` par la pile console)
- Tests : `mobile/provider/__tests__/admin/console/*.test.tsx`

- [ ] **Étape 1 : écrire les tests** — liste virtualisée avec pagination par curseur, filtres en
  bottom-sheet, recherche, état vide, état d'erreur avec réessai, ouverture du détail, exécution
  d'une action avec confirmation sur destructif, formulaire dont les erreurs serveur se posent
  champ par champ.
- [ ] **Étape 2** : lancer, constater l'échec.
- [ ] **Étape 3** : écrire les écrans. `fields.tsx` rend un `Field` typé en composant natif —
  c'est le seul endroit qui connaît la correspondance type → composant.
- [ ] **Étape 4** : typecheck, tests, commit.

---

### Tâche 5 à 7 : les dix premiers descripteurs

Trois lots, chacun terminé par son portail. Choisis pour couvrir les quatre formes que le moteur
doit savoir rendre — sinon dix descripteurs de la même forme ne prouveraient qu'une chose.

| Lot | Descripteurs | Ce que le lot éprouve |
|---|---|---|
| **5** | `users`, `companies`, `sites` | Le CRUD complet, avec formulaire et suppression |
| **6** | `kyc`, `kyb`, `enterprise-approvals`, `disputes` | Les files de décision : actions métier, pas de formulaire |
| **7** | `promo-codes`, `badges`, `feature-flags` | La création simple et les bascules booléennes |

Pour chaque descripteur :

- [ ] Écrire le test du descripteur (colonnes servies, filtres appliqués, actions déléguées au
  service existant, isolation vérifiée).
- [ ] Lancer, constater l'échec.
- [ ] Écrire le descripteur.
- [ ] Basculer `coverage` sur `descriptor` dans `config/admin_console.php`.
- [ ] Relancer — le test anti-mensonge de la tâche 2 valide la bascule.
- [ ] Commit.

---

### Tâche 8 : portail du sous-projet B

- [ ] `vendor/bin/pint --test`
- [ ] `vendor/bin/phpstan analyse` (complet ; référence : 20 erreurs préexistantes sur `main`,
      aucune nouvelle tolérée)
- [ ] `php artisan test`
- [ ] `cd mobile/provider && npm run typecheck && npm test`
- [ ] Contre-épreuve MySQL des tests console
- [ ] Vérifier dans l'annuaire que le compteur affiche bien `10 / 81 modules disponibles`

## Auto-revue du plan

- **Couverture.** Contrat → tâche 1 ; registre honnête → tâche 2 ; endpoints → tâche 3 ; rendu
  natif → tâche 4 ; dix domaines → tâches 5 à 7 ; portail → tâche 8.
- **Cohérence des noms.** `AdminResource`, `ResourceRegistry`, `Column/Filter/Action/Field` sont
  définis en tâches 1-2 et consommés en 3-7. Les valeurs de `coverage` restent celles du
  sous-projet A.
- **Risque assumé.** La pagination par curseur suppose une clé de tri stable par ressource ; les
  descripteurs qui n'en ont pas (agrégats, vues) relèvent du sous-projet C, pas du moteur.
