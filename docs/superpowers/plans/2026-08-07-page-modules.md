# Page Modules — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** remplacer les quatre registres de navigation éparpillés par un registre unique, en tirer une page « Modules » par tableau de bord (cases nom + icône, groupées par fonction), et alléger la navbar — avec un test qui échoue si un module n'a pas sa case.

**Architecture :** `config/modules.php` devient la source unique (catalogue, catégories, liste blanche). Un composant Livewire unique, monté sur cinq routes, rend les cases du contexte courant. La navbar et les deux layouts société consomment le même registre. Un test parcourt la table de routes réelle et refuse toute page de tableau de bord sans case.

**Tech Stack :** Laravel 12, Livewire 3, Blade, Tailwind, PHPUnit.

## Global Constraints

- Les libellés et icônes existants sont **repris verbatim** depuis `navigation-menu.blade.php`, `layouts/client-company.blade.php` et `layouts/provider-company.blade.php`. Ne pas les réécrire.
- Les icônes restent des **emoji** dans le registre ; la traduction en Heroicon passe par la table existante (`$iconMap`, lignes 242-260 de `navigation-menu.blade.php`), déplacée en Task 3.
- **Ne pas toucher** à `mobile/`, ni à `config/parity.php`, ni au contenu des pages de modules.
- Contextes valides, exactement ces cinq chaînes : `client`, `employe`, `admin`, `client-company`, `provider-company`.
- Clés de catégories valides, exactement ces douze : `rendez-vous`, `missions`, `documents`, `finance`, `comptes`, `prestataires`, `communication`, `qualite`, `conformite`, `croissance`, `donnees`, `plateforme`.
- Tout commit passe `vendor/bin/phpstan analyse --memory-limit=2G` **sans argument de chemin**.

## File Structure

| Fichier | Responsabilité |
|---|---|
| `config/modules.php` (créer) | Registre : catégories, catalogue des modules, liste blanche des non-modules |
| `app/Support/Navigation/ModuleCatalogue.php` (créer) | Lecture du registre : filtrage par contexte, groupement par catégorie, retrait des routes absentes |
| `app/Support/Navigation/ModuleIcons.php` (créer) | Table emoji → Heroicon, extraite de la vue |
| `app/Livewire/Shared/ModulesDirectory.php` (créer) | Composant de la page, un par contexte via paramètre |
| `resources/views/livewire/shared/modules-directory.blade.php` (créer) | Rendu des cases groupées |
| `resources/views/navigation-menu.blade.php` (modifier) | Perd ses trois tableaux inline, consomme le catalogue |
| `resources/views/layouts/client-company.blade.php` (modifier) | Perd ses 11 liens en dur |
| `resources/views/layouts/provider-company.blade.php` (modifier) | Perd ses 6 liens en dur |
| `routes/admin.php`, `routes/client.php`, `routes/employe.php`, `routes/company-dashboards.php` (modifier) | Une route `/modules` par contexte, dans le groupe de middleware existant |
| `tests/Feature/Navigation/CatalogueDesModulesTest.php` (créer) | Le garde-fou d'exhaustivité |
| `tests/Feature/Navigation/PageModulesTest.php` (créer) | Rendu et accès de la page |

---

### Task 1 : Le garde-fou d'exhaustivité

Ce test vient **en premier** et reste rouge jusqu'à la fin de la Task 2. C'est lui qui définit la
condition d'arrêt : tant qu'il liste des routes, le travail n'est pas fini. Écrire le registre
d'abord et le test ensuite reviendrait à mesurer ce qu'on a tapé, pas ce qui existe.

**Files:**
- Create: `tests/Feature/Navigation/CatalogueDesModulesTest.php`
- Create: `config/modules.php` (squelette vide seulement)

**Interfaces:**
- Produces: `config('modules.catalogue')` — liste d'entrées `['key','label','icon','route','context','category','primary']` ; `config('modules.categories')` — map `clé => libellé` ; `config('modules.non_modules')` — map `nom de route => raison`.

- [ ] **Step 1 : Créer le squelette du registre**

`config/modules.php` :

```php
<?php

/**
 * Registre des modules — source unique des points d'entrée du web.
 *
 * Il remplace quatre registres épars : les trois tableaux inline de
 * `navigation-menu.blade.php`, et les liens en dur des deux layouts société.
 *
 * `CatalogueDesModulesTest` part de la table de routes RÉELLE : toute page de tableau de bord
 * absente d'ici fait échouer la suite. C'est volontaire — ce dépôt a déjà produit des tests de
 * joignabilité qui asséraient une déclaration au lieu d'un chemin.
 */
return [
    'categories' => [
        'rendez-vous'   => 'Rendez-vous & planning',
        'missions'      => 'Missions & terrain',
        'documents'     => 'Documents & contrats',
        'finance'       => 'Finance & paiements',
        'comptes'       => 'Comptes & organisations',
        'prestataires'  => 'Prestataires & équipes',
        'communication' => 'Communication',
        'qualite'       => 'Qualité & litiges',
        'conformite'    => 'Conformité & sécurité',
        'croissance'    => 'Croissance & fidélité',
        'donnees'       => 'Données & analytics',
        'plateforme'    => 'Plateforme & réglages',
    ],

    'catalogue' => [
        // Rempli en Task 2, jusqu'à ce que CatalogueDesModulesTest passe.
    ],

    /*
     * Routes de tableau de bord qui ne sont PAS des modules : téléchargements et callbacks
     * OAuth. Elles n'ont pas de page, donc pas de case. Chaque ligne porte sa raison, pour
     * qu'on ne puisse pas y glisser un vrai module en douce.
     */
    'non_modules' => [
        'admin.export.csv'                        => 'Téléchargement CSV, pas une page',
        'admin.export.pdf'                        => 'Téléchargement PDF, pas une page',
        'admin.feedbacks.export'                  => 'Téléchargement, pas une page',
        'admin.feedbacks.export.csv'              => 'Téléchargement CSV, pas une page',
        'admin.missions.export.pdf'               => 'Téléchargement PDF, pas une page',
        'admin.quality.export.incidents.csv'      => 'Téléchargement CSV, pas une page',
        'admin.quality.export.missions.csv'       => 'Téléchargement CSV, pas une page',
        'client.analytics.export.bookings'        => 'Téléchargement CSV, pas une page',
        'client.analytics.export.kpis'            => 'Téléchargement CSV, pas une page',
        'client.analytics.export.monthly_revenue' => 'Téléchargement CSV, pas une page',
        'client.exports.bookings.xlsx'            => 'Téléchargement XLSX, pas une page',
        'employe.stripe-connect.refresh'          => 'Callback OAuth Stripe, pas une page',
        'employe.stripe-connect.return'           => 'Callback OAuth Stripe, pas une page',
    ],
];
```

- [ ] **Step 2 : Écrire le test qui échoue**

`tests/Feature/Navigation/CatalogueDesModulesTest.php` :

```php
<?php

namespace Tests\Feature\Navigation;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * LE CATALOGUE DOIT COUVRIR CE QUI EXISTE, PAS CE QU'ON CROIT AVOIR ÉCRIT.
 *
 * Ce test part de la table de routes réelle — la seule source qui ne peut pas mentir sur ce qui
 * existe. Un test qui lirait `config/modules.php` pour vérifier `config/modules.php` ne prouverait
 * rien : c'est exactement le piège dans lequel les tests de joignabilité de ce dépôt sont déjà
 * tombés, en asserant qu'une route était DÉCLARÉE plutôt qu'ATTEIGNABLE.
 */
class CatalogueDesModulesTest extends TestCase
{
    /** Les pages de tableau de bord réellement servies, par nom de route. */
    private function pagesDeTableauDeBord(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->filter(fn ($route) => preg_match('#^(admin|dashboard)(/|$)#', $route->uri()) === 1)
            ->reject(fn ($route) => str_contains($route->uri(), '{'))
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function test_chaque_page_de_tableau_de_bord_a_sa_case_ou_sa_raison(): void
    {
        $avecCase = collect(config('modules.catalogue'))->pluck('route')->all();
        $sansCase = array_keys(config('modules.non_modules'));

        $orphelines = collect($this->pagesDeTableauDeBord())
            ->reject(fn ($nom) => in_array($nom, $avecCase, true))
            ->reject(fn ($nom) => in_array($nom, $sansCase, true))
            ->values()
            ->all();

        $this->assertSame([], $orphelines, sprintf(
            "%d page(s) de tableau de bord sans case dans config/modules.php :\n  %s\n\n".
            "Ajouter une entrée au catalogue, ou une ligne dans non_modules avec sa raison.",
            count($orphelines),
            implode("\n  ", $orphelines),
        ));
    }

    public function test_aucune_case_ne_mene_a_une_route_inexistante(): void
    {
        // Une case morte est pire qu'une case absente : elle promet une page et rend un 404.
        $mortes = collect(config('modules.catalogue'))
            ->reject(fn ($module) => Route::has($module['route']))
            ->pluck('route')
            ->values()
            ->all();

        $this->assertSame([], $mortes, 'Cases pointant vers une route inexistante : '.implode(', ', $mortes));
    }

    public function test_chaque_case_declare_un_contexte_et_une_categorie_connus(): void
    {
        $contextes = ['client', 'employe', 'admin', 'client-company', 'provider-company'];
        $categories = array_keys(config('modules.categories'));

        foreach (config('modules.catalogue') as $module) {
            $this->assertContains($module['context'], $contextes, "Contexte inconnu pour {$module['key']}");
            $this->assertContains($module['category'], $categories, "Catégorie inconnue pour {$module['key']}");
        }
    }

    public function test_aucune_cle_de_case_en_double(): void
    {
        $cles = collect(config('modules.catalogue'))->pluck('key');

        $this->assertSame($cles->unique()->count(), $cles->count(), 'Clés dupliquées dans le catalogue');
    }
}
```

- [ ] **Step 3 : Lancer le test et vérifier qu'il échoue pour la bonne raison**

Run : `php artisan test tests/Feature/Navigation/CatalogueDesModulesTest.php`
Expected : FAIL sur `test_chaque_page_de_tableau_de_bord_a_sa_case_ou_sa_raison`, avec la liste des 149 routes sans case. Les trois autres tests passent (catalogue vide = rien à valider).

Si le compte n'est pas 149, ne pas ajuster le test : relever l'écart, c'est une information sur le code, pas sur le test.

- [ ] **Step 4 : Commit**

```bash
git add config/modules.php tests/Feature/Navigation/CatalogueDesModulesTest.php
git commit -m "test(navigation): le catalogue des modules doit couvrir la table de routes reelle"
```

---

### Task 2 : Remplir le catalogue jusqu'au vert

C'est la boucle. Elle s'arrête quand le test de la Task 1 passe — pas quand on estime avoir fini.

**Files:**
- Modify: `config/modules.php` (clé `catalogue`)

**Interfaces:**
- Consumes: le squelette et le test de la Task 1.
- Produces: 149 entrées, chacune `['key','label','icon','route','context','category','primary']`.

- [ ] **Step 1 : Reprendre les 126 entrées de la navbar**

Source : `resources/views/navigation-menu.blade.php`, lignes 27-213 (`$clientGroups`, `$employeGroups`, `$adminGroups`). Copier `label`, `route` et `icon` **verbatim**. Ajouter `context` (`client`, `employe`, `admin` selon le tableau d'origine) et traduire le groupe d'origine en `category` :

| Groupe d'origine | `category` |
|---|---|
| Essentiel (client) | `rendez-vous` |
| Marketplace | `prestataires` |
| Engagement | `croissance` |
| Finance & paiement | `finance` |
| Communication & SAV | `communication` — sauf « Litiges » → `qualite` |
| Compte client | `comptes` — sauf « Mes données RGPD » et « API tokens » → `conformite` et `plateforme` |
| Pro (compte entreprise) | `comptes` — sauf « Contrats » → `documents`, « KYB » → `conformite` |
| Mon travail | `missions` — sauf Planning, Disponibilités, Google Agenda → `rendez-vous` |
| Mes revenus | `finance` |
| Mes performances | `croissance` |
| Vérifications & support | `conformite` — sauf « Mes litiges » → `qualite` |
| Qualité & équipe | `qualite` — sauf « Équipe terrain », « Coordination », « Chef d'équipe » → `prestataires` |
| Pilotage | `donnees` — sauf Planning → `rendez-vous`, Missions → `missions`, Finance → `finance` |
| Opérations | `comptes` — sauf Catalogue géographique et Pays → `plateforme`, Automation et Orchestration → `missions` |
| Gestion | `comptes` — sauf Modules, Feature flags, Outils admin → `plateforme`, Feedbacks → `qualite`, Crédits clients → `finance` |
| Business avancé | `finance` — sauf IA Dispatch → `donnees`, Emails produit → `communication`, Readiness → `plateforme` |
| Qualité & confiance | `qualite` — sauf KYC → `conformite`, Trades catalogue → `plateforme` |
| Croissance & marketing | `croissance` — sauf NPS scoring et Raisons annulation → `donnees` |
| Plateforme & risk | `plateforme` — sauf Risk, Audit, GDPR → `conformite`, SMS/Push/Realtime/Notifications → `communication` |
| Finance avancée | `finance` — sauf Assurance → `conformite`, Comptabilité → `documents` |
| Opérations terrain | `missions` — sauf Disponibilités → `rendez-vous`, Contrats v2 → `documents`, Analytics v2 et Matching → `donnees`, Pricing → `finance`, Inscriptions et Onboarding → `prestataires` |
| B2B & multi-tenant | KYB → `conformite`, Chat → `communication` |

- [ ] **Step 2 : Reprendre les 17 entrées des layouts société**

Source : `resources/views/layouts/client-company.blade.php` lignes 31-42 (11 liens, `context` = `client-company`) et `resources/views/layouts/provider-company.blade.php` lignes 30-37 (6 liens, `context` = `provider-company`). Catégories :

| Libellé | `category` |
|---|---|
| Accueil, Dashboard | `donnees` |
| Mes locaux | `comptes` |
| Réservations, Multi-locaux, Import bulk | `rendez-vous` |
| Membres, Équipe, Équipes terrain | `prestataires` |
| Contrats, Signatures | `documents` |
| Facturation | `finance` |
| Litiges | `qualite` |
| Analytics | `donnees` |
| Canaux | `communication` |
| Tâches, Dispatch | `missions` |

- [ ] **Step 3 : Marquer les entrées `primary`**

Au plus **cinq par contexte** — ce qui restera dans la navbar. Reprendre les cinq premiers liens de chaque rôle, qui sont déjà l'ordre choisi (`$primaryLinks = $roleLinks->take(5)`, ligne 238) :

- `client` : Accueil, Nouveau RDV, Mes rendez-vous, Historique, Trouver un prestataire
- `employe` : Ma journée, Mes missions, Devis chantiers, Planning, Disponibilités
- `admin` : Dashboard, Vue d'ensemble, Planning, Missions, Alertes
- `client-company` : Accueil, Mes locaux, Réservations, Membres, Facturation
- `provider-company` : Dashboard, Dispatch, Tâches, Équipe, Canaux

Toutes les autres entrées : `'primary' => false`.

- [ ] **Step 4 : Lancer le test et lire ce qui manque**

Run : `php artisan test tests/Feature/Navigation/CatalogueDesModulesTest.php`

Le message d'échec liste les routes sans case. Pour chacune : ajouter une entrée au catalogue avec un libellé tiré du composant Livewire qui la sert (`config/modules.php` n'invente pas de nom — ouvrir la classe listée dans la table de routes), une icône cohérente avec les voisines de sa catégorie, son contexte et sa catégorie.

**Répéter jusqu'au vert.** Ne pas élargir `non_modules` pour faire taire le test : cette liste ne contient que des téléchargements et des callbacks, et les 13 y sont déjà.

- [ ] **Step 5 : Vérifier qu'il passe**

Run : `php artisan test tests/Feature/Navigation/CatalogueDesModulesTest.php`
Expected : 4 tests PASS.

- [ ] **Step 6 : Commit**

```bash
git add config/modules.php
git commit -m "feat(navigation): le catalogue couvre les 149 modules du web"
```

---

### Task 3 : Lecture du catalogue et table d'icônes

**Files:**
- Create: `app/Support/Navigation/ModuleIcons.php`
- Create: `app/Support/Navigation/ModuleCatalogue.php`
- Test: `tests/Feature/Navigation/ModuleCatalogueTest.php`

**Interfaces:**
- Produces :
  - `ModuleIcons::heroicon(string $emoji): ?string`
  - `ModuleCatalogue::pourContexte(string $contexte): Collection` — collection de `['category','label','modules']`, catégories vides retirées, routes absentes retirées, ordre des catégories = celui de `config('modules.categories')`
  - `ModuleCatalogue::principaux(string $contexte): Collection` — les entrées `primary`

- [ ] **Step 1 : Écrire le test qui échoue**

`tests/Feature/Navigation/ModuleCatalogueTest.php` :

```php
<?php

namespace Tests\Feature\Navigation;

use App\Support\Navigation\ModuleCatalogue;
use Tests\TestCase;

class ModuleCatalogueTest extends TestCase
{
    public function test_groupe_les_modules_d_un_contexte_par_categorie(): void
    {
        $groupes = ModuleCatalogue::pourContexte('client');

        $this->assertNotEmpty($groupes);
        foreach ($groupes as $groupe) {
            $this->assertArrayHasKey('category', $groupe);
            $this->assertArrayHasKey('label', $groupe);
            $this->assertNotEmpty($groupe['modules'], 'Une catégorie vide ne doit pas être rendue');
            foreach ($groupe['modules'] as $module) {
                $this->assertSame('client', $module['context']);
            }
        }
    }

    public function test_retire_les_modules_dont_la_route_n_existe_pas(): void
    {
        // Une case morte promet une page et rend un 404. `Route::has` est le seul juge.
        config()->set('modules.catalogue', [
            ['key' => 'fantome', 'label' => 'Fantôme', 'icon' => '👻', 'route' => 'route.qui.nexiste.pas',
             'context' => 'client', 'category' => 'comptes', 'primary' => false],
        ]);

        $this->assertCount(0, ModuleCatalogue::pourContexte('client'));
    }

    public function test_respecte_l_ordre_des_categories_du_registre(): void
    {
        $attendu = array_keys(config('modules.categories'));
        $obtenu = ModuleCatalogue::pourContexte('admin')->pluck('category')->all();

        $this->assertSame(array_values(array_intersect($attendu, $obtenu)), $obtenu);
    }

    public function test_ne_rend_que_cinq_principaux_au_maximum(): void
    {
        foreach (['client', 'employe', 'admin', 'client-company', 'provider-company'] as $contexte) {
            $this->assertLessThanOrEqual(5, ModuleCatalogue::principaux($contexte)->count(), $contexte);
        }
    }
}
```

- [ ] **Step 2 : Lancer et vérifier l'échec**

Run : `php artisan test tests/Feature/Navigation/ModuleCatalogueTest.php`
Expected : FAIL, `Class "App\Support\Navigation\ModuleCatalogue" not found`.

- [ ] **Step 3 : Écrire les deux classes**

`app/Support/Navigation/ModuleIcons.php` :

```php
<?php

namespace App\Support\Navigation;

/**
 * Table emoji → Heroicon, extraite de `navigation-menu.blade.php`.
 *
 * Elle vivait dans la vue, qui était la seule à en avoir besoin. La page Modules et les deux
 * layouts société la consomment désormais aussi : la laisser en Blade obligerait à la recopier
 * trois fois, et ce dépôt a déjà payé le prix d'une table dupliquée.
 */
class ModuleIcons
{
    /** Copier ici la table `$iconMap` des lignes 242-260 de `navigation-menu.blade.php`, verbatim. */
    private const TABLE = [
        '🏠' => 'home', '➕' => 'plus', '📅' => 'calendar', '🕘' => 'clock', '🗓️' => 'calendar',
        // … reprendre l'intégralité des entrées existantes …
    ];

    /** `null` quand l'emoji n'est pas mappé : l'appelant affiche alors l'emoji tel quel. */
    public static function heroicon(string $emoji): ?string
    {
        return self::TABLE[$emoji] ?? null;
    }
}
```

`app/Support/Navigation/ModuleCatalogue.php` :

```php
<?php

namespace App\Support\Navigation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class ModuleCatalogue
{
    /** Les modules d'un contexte, groupés par catégorie, dans l'ordre du registre. */
    public static function pourContexte(string $contexte): Collection
    {
        $modules = self::visibles($contexte);

        return collect(config('modules.categories'))
            ->map(fn (string $libelle, string $cle) => [
                'category' => $cle,
                'label' => $libelle,
                'modules' => $modules->where('category', $cle)->values()->all(),
            ])
            ->filter(fn (array $groupe) => $groupe['modules'] !== [])
            ->values();
    }

    /** Les entrées qui restent dans la navbar allégée. */
    public static function principaux(string $contexte): Collection
    {
        return self::visibles($contexte)->where('primary', true)->values();
    }

    /**
     * Une case dont la route n'existe pas promet une page et rend un 404. `Route::has` est le
     * seul juge : les routes varient selon les modules activés.
     */
    private static function visibles(string $contexte): Collection
    {
        return collect(config('modules.catalogue'))
            ->where('context', $contexte)
            ->filter(fn (array $module) => Route::has($module['route']))
            ->values();
    }
}
```

- [ ] **Step 4 : Vérifier que ça passe**

Run : `php artisan test tests/Feature/Navigation/ModuleCatalogueTest.php`
Expected : 4 tests PASS.

- [ ] **Step 5 : Commit**

```bash
git add app/Support/Navigation tests/Feature/Navigation/ModuleCatalogueTest.php
git commit -m "feat(navigation): lecture du catalogue et table d'icones partagee"
```

---

### Task 4 : La page Modules

**Files:**
- Create: `app/Livewire/Shared/ModulesDirectory.php`
- Create: `resources/views/livewire/shared/modules-directory.blade.php`
- Modify: `routes/admin.php`, `routes/client.php`, `routes/employe.php`, `routes/company-dashboards.php`
- Test: `tests/Feature/Navigation/PageModulesTest.php`

**Interfaces:**
- Consumes : `ModuleCatalogue::pourContexte()`, `ModuleIcons::heroicon()`
- Produces : routes nommées `client.modules`, `employe.modules`, `admin.modules.directory`, `client-company.modules`, `provider-company.modules`

> `admin.modules` est **déjà pris** par la page de gestion des modules plateforme (`admin/modules`). D'où `admin.modules.directory`. Vérifier avec `php artisan route:list --name=modules` avant de déclarer.

- [ ] **Step 1 : Écrire le test qui échoue**

`tests/Feature/Navigation/PageModulesTest.php` :

```php
<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PageModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_client_voit_ses_modules_groupes_par_categorie(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $reponse = $this->actingAs($client)->get(route('client.modules'));

        $reponse->assertOk();
        $reponse->assertSee('Rendez-vous & planning');
        $reponse->assertSee('Mes rendez-vous');
        // La case doit MENER quelque part : un libellé seul ne prouve rien.
        $reponse->assertSee(route('client.rendezvous.index'), false);
    }

    public function test_ne_montre_pas_a_un_client_les_modules_d_administration(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $reponse = $this->actingAs($client)->get(route('client.modules'));

        $reponse->assertDontSee('Feature flags');
    }

    public function test_un_client_ne_peut_pas_ouvrir_la_page_modules_admin(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->get(route('admin.modules.directory'))->assertForbidden();
    }
}
```

- [ ] **Step 2 : Lancer et vérifier l'échec**

Run : `php artisan test tests/Feature/Navigation/PageModulesTest.php`
Expected : FAIL, route `client.modules` non définie.

- [ ] **Step 3 : Écrire le composant**

`app/Livewire/Shared/ModulesDirectory.php` :

```php
<?php

namespace App\Livewire\Shared;

use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Le répertoire des modules d'un tableau de bord.
 *
 * Un seul composant pour les cinq contextes : ce qui change entre eux est une donnée du registre,
 * pas du code. Le contexte arrive par la route, jamais par une entrée utilisateur — chaque route
 * vit déjà dans le groupe de middleware de son rôle, qui reste le seul juge des droits.
 */
class ModulesDirectory extends Component
{
    public string $contexte = 'client';

    public function mount(string $contexte): void
    {
        $this->contexte = $contexte;
    }

    public function render(): View
    {
        return view('livewire.shared.modules-directory', [
            'groupes' => ModuleCatalogue::pourContexte($this->contexte),
        ]);
    }
}
```

- [ ] **Step 4 : Écrire la vue**

`resources/views/livewire/shared/modules-directory.blade.php` :

```blade
<div class="mx-auto max-w-7xl px-4 py-8">
    <h1 class="text-2xl font-black text-slate-900 dark:text-white">Modules</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Tout ce que cet espace sait faire, rangé par fonction.
    </p>

    @foreach ($groupes as $groupe)
        <section class="mt-8">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $groupe['label'] }}</h2>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($groupe['modules'] as $module)
                    @php $heroicon = \App\Support\Navigation\ModuleIcons::heroicon($module['icon']); @endphp
                    <a href="{{ route($module['route']) }}"
                       class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition
                              hover:border-blue-300 hover:shadow-sm
                              dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-500">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                                     bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            @if ($heroicon)
                                <x-ui.icon :name="$heroicon" class="h-5 w-5" />
                            @else
                                <span class="text-lg leading-none">{{ $module['icon'] }}</span>
                            @endif
                        </span>
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                            {{ $module['label'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
```

- [ ] **Step 5 : Déclarer les cinq routes**

Dans chaque fichier de routes, **à l'intérieur du groupe de middleware existant du rôle** :

```php
// routes/client.php
Route::get('/modules', App\Livewire\Shared\ModulesDirectory::class)
    ->defaults('contexte', 'client')
    ->name('client.modules');

// routes/employe.php      -> 'employe',        name 'employe.modules'
// routes/admin.php        -> 'admin',          name 'admin.modules.directory'
// routes/company-dashboards.php : deux routes, 'client-company' et 'provider-company',
//   chacune dans SON groupe de middleware (les gardes d'organisation diffèrent).
```

- [ ] **Step 6 : Vérifier que ça passe**

Run : `php artisan test tests/Feature/Navigation/PageModulesTest.php`
Expected : 3 tests PASS.

- [ ] **Step 7 : Commit**

```bash
git add app/Livewire/Shared resources/views/livewire/shared routes tests/Feature/Navigation/PageModulesTest.php
git commit -m "feat(navigation): une page Modules par tableau de bord"
```

---

### Task 5 : Alléger la navbar

**Files:**
- Modify: `resources/views/navigation-menu.blade.php` (supprimer lignes 27-213 et 242-260 ; consommer le catalogue)
- Test: `tests/Feature/Navigation/NavbarAllegeeTest.php`

**Interfaces:**
- Consumes : `ModuleCatalogue::principaux()`, `ModuleIcons::heroicon()`

- [ ] **Step 1 : Écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarAllegeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_navbar_admin_ne_deverse_plus_ses_soixante_treize_liens(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $reponse = $this->actingAs($admin)->get(route('admin.dashboard'));

        $reponse->assertOk();
        // « Feature flags » vivait dans un menu déroulant de la navbar ; il appartient
        // désormais à la page Modules.
        $reponse->assertDontSee('Feature flags');
        $reponse->assertSee('Modules');
        $reponse->assertSee(route('admin.modules.directory'), false);
    }

    public function test_les_liens_chauds_du_role_restent_dans_la_navbar(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $reponse = $this->actingAs($admin)->get(route('admin.dashboard'));

        $reponse->assertSee(route('admin.planning'), false);
        $reponse->assertSee(route('admin.missions'), false);
    }
}
```

- [ ] **Step 2 : Lancer et vérifier l'échec**

Run : `php artisan test tests/Feature/Navigation/NavbarAllegeeTest.php`
Expected : FAIL sur `assertDontSee('Feature flags')` — la navbar les affiche encore.

- [ ] **Step 3 : Remplacer le bloc `@php` de la navbar**

Supprimer `$clientGroups`, `$employeGroups`, `$adminGroups`, `$filterLinks`, `$iconMap`, `$groups`, `$roleLinks`. Les remplacer par :

```php
$contexte = match (true) {
    // L'ORDRE COMPTE, et il reste celui de `routes/authenticated.php` : ces rôles ne s'excluent
    // pas. Promouvoir un client en administrateur ne lui retire pas son profil client ; tester
    // `isClient()` d'abord laissait `isAdmin()` inatteignable, et le compte gardait le menu
    // client sans le moindre lien vers l'administration.
    $user?->isAdmin() => 'admin',
    $user?->isClient() => 'client',
    $user?->isEmploye() => 'employe',
    default => null,
};

$primaryLinks = $contexte ? \App\Support\Navigation\ModuleCatalogue::principaux($contexte) : collect();

$modulesRoute = match ($contexte) {
    'admin' => 'admin.modules.directory',
    'client' => 'client.modules',
    'employe' => 'employe.modules',
    default => null,
};

$renderIcon = function (string $icon) {
    $name = \App\Support\Navigation\ModuleIcons::heroicon($icon);

    return $name
        ? view('components.ui.icon', ['name' => $name, 'class' => 'w-4 h-4 shrink-0'])->render()
        : '<span class="text-base leading-none">'.e($icon).'</span>';
};
```

Supprimer les menus déroulants de groupes (le `@if($groups->isNotEmpty())` et son contenu) et poser à leur place un lien unique :

```blade
@if ($modulesRoute && Route::has($modulesRoute))
    <x-nav-link :href="route($modulesRoute)" :active="request()->routeIs($modulesRoute)">
        <span class="me-1 inline-flex items-center">{!! $renderIcon('🧩') !!}</span>
        Modules
    </x-nav-link>
@endif
```

Faire la même substitution dans le bloc responsive (à partir de la ligne 492).

- [ ] **Step 4 : Vérifier que ça passe**

Run : `php artisan test tests/Feature/Navigation/NavbarAllegeeTest.php`
Expected : 2 tests PASS.

- [ ] **Step 5 : Vérifier qu'on n'a rien cassé ailleurs**

Run : `php artisan test tests/Feature/Navigation`
Expected : tous verts.

- [ ] **Step 6 : Commit**

```bash
git add resources/views/navigation-menu.blade.php tests/Feature/Navigation/NavbarAllegeeTest.php
git commit -m "feat(navigation): la navbar garde les liens chauds et renvoie au reste"
```

---

### Task 6 : Les deux layouts société consomment le registre

**Files:**
- Modify: `resources/views/layouts/client-company.blade.php` (lignes 30-52)
- Modify: `resources/views/layouts/provider-company.blade.php` (lignes 29-45)
- Test: `tests/Feature/Navigation/NavbarSocieteTest.php`

- [ ] **Step 1 : Écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\Navigation;

use Tests\TestCase;

class NavbarSocieteTest extends TestCase
{
    public function test_les_layouts_societe_ne_declarent_plus_leurs_liens_en_dur(): void
    {
        // Ces liens vivaient inline : deux registres de plus, que personne ne pensait à mettre à
        // jour en ajoutant un module.
        foreach (['client-company', 'provider-company'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringNotContainsString("'label' =>", $source, $layout);
            $this->assertStringContainsString('ModuleCatalogue', $source, $layout);
        }
    }
}
```

- [ ] **Step 2 : Lancer et vérifier l'échec**

Run : `php artisan test tests/Feature/Navigation/NavbarSocieteTest.php`
Expected : FAIL — les `'label' =>` sont toujours là.

- [ ] **Step 3 : Remplacer les boucles inline**

Dans chaque layout, remplacer le tableau `@foreach ([...])` par :

```blade
@foreach (\App\Support\Navigation\ModuleCatalogue::principaux('client-company') as $link)
```

(et `'provider-company'` dans l'autre), en gardant le balisage `<a>` existant tel quel — les classes de style de chaque espace diffèrent et ne sont pas le sujet.

Ajouter dans chaque layout, après la boucle, le lien vers la page Modules du contexte, sur le modèle de la Task 5.

- [ ] **Step 4 : Vérifier que ça passe**

Run : `php artisan test tests/Feature/Navigation/NavbarSocieteTest.php`
Expected : 1 test PASS.

- [ ] **Step 5 : Vérification complète**

```bash
php artisan test
vendor/bin/phpstan analyse --memory-limit=2G
```

Expected : suite verte, PHPStan `[OK] No errors`.

- [ ] **Step 6 : Commit**

```bash
git add resources/views/layouts tests/Feature/Navigation/NavbarSocieteTest.php
git commit -m "feat(navigation): les espaces societe consomment le registre commun"
```

---

## Auto-revue du plan

- **Couverture de la spec** : registre unique (Tasks 1-2), taxonomie (Task 1 Step 1), page par contexte (Task 4), navbar allégée (Task 5), layouts société (Task 6), test d'exhaustivité (Task 1). Les six critères d'acceptation ont chacun leur tâche.
- **Cohérence des noms** : `ModuleCatalogue::pourContexte()` et `::principaux()`, `ModuleIcons::heroicon()` — mêmes signatures en Tasks 3, 4, 5 et 6.
- **Piège relevé** : `admin.modules` est déjà pris par la page de gestion des modules plateforme ; la route du répertoire s'appelle `admin.modules.directory`. Noté en Task 4.
- **Zone d'incertitude assumée** : la répartition en catégories de la Task 2 est un jugement éditorial. Le test ne la vérifie pas — il vérifie qu'une catégorie *connue* est déclarée. Une case mal rangée reste une case atteignable.
