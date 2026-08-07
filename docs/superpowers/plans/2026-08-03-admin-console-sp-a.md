# Console d'administration mobile — Sous-projet A

> **Pour les exécutants :** ce plan s'exécute tâche par tâche. Chaque case `- [ ]` est une étape de
> 2 à 5 minutes. Le portail de vérification doit être vert avant de passer à la tâche suivante.

**But :** fermer le trou d'autorisation sur `/api/admin/*`, puis faire entrer un administrateur
dans `mobile/provider` avec un accueil chiffré et l'annuaire honnête des 99 routes d'administration.

**Architecture :** un middleware d'API dédié garde le groupe admin ; un registre
`config/admin_console.php` recense les 99 routes admin et l'état de leur couverture mobile ; deux
endpoints (`/api/admin/catalog`, `/api/admin/overview`) alimentent une coquille native
`AdminNavigator` montée à la place du parcours prestataire quand le compte est administrateur.

**Pile :** Laravel 12 / Sanctum / PHPUnit côté serveur ; Expo SDK 56, React Native 0.85,
React Navigation 7, Jest + Testing Library côté mobile.

## Contraintes globales

- Expo a changé : consulter <https://docs.expo.dev/versions/v56.0.0/> avant d'écrire du code mobile
  (`mobile/client/AGENTS.md`).
- Commentaires et libellés en français, à la manière du code existant : expliquer **pourquoi**,
  jamais paraphraser le code.
- `vendor/bin/phpstan analyse` se lance **en entier**, jamais limité à un chemin — un run
  path-scopé a déjà masqué 12 erreurs sur ce projet.
- La suite PHPUnit tourne sur SQLite alors que l'application tourne sur MySQL strict : toute
  requête nouvelle est revérifiée contre MySQL en fin de sous-projet.
- Le modèle des litiges est `App\Models\CustomerClaim` (table `customer_claims`, clé
  `customer_user_id`). Il n'existe **pas** de modèle `Dispute` ni de table `disputes`.
- Ne jamais monter `usePresenceHeartbeat` dans l'espace admin : le battement de présence est un
  signal de terrain prestataire.

---

### Tâche 1 : fermer `/api/admin/*`

**Fichiers :**
- Créer : `app/Http/Middleware/EnsureApiAdmin.php`
- Modifier : `app/Http/Kernel.php` (tableau `$middlewareAliases`)
- Modifier : `routes/api/admin.php` (groupe racine)
- Test : `tests/Feature/Api/Admin/AdminApiRoleGuardTest.php`

**Interfaces :**
- Produit : alias de middleware `api_admin`, réponse `403 {ok:false, error:'forbidden_not_admin'}`.

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Le groupe /api/admin/* n'était gardé que par `api_scope`. Or le jeton mobile est émis sans
 * liste d'abilities : Sanctum y inscrit '*', et EnforceTokenScope laisse passer tout jeton qui
 * porte '*'. Un client connecté atteignait donc la comptabilité et les jetons d'API.
 */
class AdminApiRoleGuardTest extends TestCase
{
    use RefreshDatabase;

    /** Un représentant par famille d'endpoints — la garde est posée sur le groupe entier. */
    public static function adminEndpoints(): array
    {
        return [
            'comptabilité' => ['GET', '/api/admin/accounting-v2/entries'],
            'jetons d’API' => ['GET', '/api/admin/api-tokens-v2/tokens'],
            'audit' => ['GET', '/api/admin/audit/events'],
            'flotte' => ['GET', '/api/admin/fleet-v2/vehicles'],
            'webhooks' => ['GET', '/api/admin/webhooks-v2/endpoints'],
            'abonnements' => ['GET', '/api/admin/subscriptions-v2/subscriptions'],
            'risque' => ['GET', '/api/admin/risk/evaluations'],
            'marketing' => ['GET', '/api/admin/marketing/campaigns'],
            'chat' => ['GET', '/api/admin/chat-v2/threads'],
            'géolocalisation' => ['GET', '/api/admin/geolocation-v2/stats'],
            'KYB' => ['GET', '/api/admin/kyb-v2/entities'],
            'assurance' => ['GET', '/api/admin/insurance-v2/claims'],
            'annulations' => ['GET', '/api/admin/cancellations-v2'],
            'contrats' => ['GET', '/api/admin/contracts-v2/templates'],
            'tarification' => ['GET', '/api/admin/pricing-v2/quotes'],
            'onboarding' => ['GET', '/api/admin/onboarding-v2/progress'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminEndpoints')]
    public function test_un_compte_non_admin_est_refuse(string $method, string $uri): void
    {
        $client = User::factory()->create(['platform_role' => 'user']);
        Sanctum::actingAs($client, ['*']);

        $this->json($method, $uri)
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden_not_admin');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminEndpoints')]
    public function test_un_administrateur_passe_la_garde(string $method, string $uri): void
    {
        $admin = User::factory()->create(['platform_role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        // La garde ne dit rien de ce que fait l'endpoint ensuite : on vérifie seulement qu'elle
        // ne l'arrête pas. Un 404/422/500 métier serait un autre sujet, pas une régression ici.
        $this->json($method, $uri)->assertStatus(200);
    }
}
```

- [ ] **Étape 2 : lancer le test, vérifier qu'il échoue**

Lancer : `php artisan test --filter=AdminApiRoleGuardTest`
Attendu : les cas « non admin » échouent en recevant 200 au lieu de 403 — c'est exactement le trou.

- [ ] **Étape 3 : écrire le middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde d'administration pour l'API.
 *
 * POURQUOI UN MIDDLEWARE DÉDIÉ PLUTÔT QUE `role:admin`. Le groupe API répond en JSON de forme
 * `{ok, error}` ; `CheckRole` appelle `abort(403)` et laisse le rendu au gestionnaire
 * d'exceptions, dont la forme dépend des en-têtes de la requête. Une console mobile qui doit
 * distinguer « pas administrateur » de « jeton expiré » a besoin d'un code stable, pas d'une
 * page d'erreur négociée.
 *
 * CE QU'IL CORRIGE. `api_scope` était le seul garde du groupe admin. Le jeton mobile est créé par
 * `createToken()` sans abilities, donc Sanctum y inscrit '*', et `EnforceTokenScope` laisse passer
 * tout jeton portant '*'. Le contrôle de scope ne dit rien du rôle : il dit ce que le jeton a le
 * droit de faire, pas qui le porte.
 */
class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        if (! (method_exists($user, 'isAdmin') && $user->isAdmin())) {
            return response()->json(['ok' => false, 'error' => 'forbidden_not_admin'], 403);
        }

        return $next($request);
    }
}
```

- [ ] **Étape 4 : enregistrer l'alias**

Dans `app/Http/Kernel.php`, à côté de `'api_scope' => EnforceTokenScope::class,` :

```php
'api_admin' => \App\Http\Middleware\EnsureApiAdmin::class,
```

- [ ] **Étape 5 : poser la garde sur le groupe**

Dans `routes/api/admin.php`, remplacer `Route::middleware('auth:sanctum')->group(function () {` par :

```php
// `api_scope` dit ce qu'un jeton a le droit de faire ; il ne dit pas QUI le porte. Le jeton
// mobile est émis avec l'ability '*', donc il satisfaisait tous les scopes admin sans être
// celui d'un administrateur. La garde de rôle est le verrou manquant.
Route::middleware(['auth:sanctum', 'api_admin'])->group(function () {
```

- [ ] **Étape 6 : relancer le test**

Lancer : `php artisan test --filter=AdminApiRoleGuardTest`
Attendu : tout passe.

- [ ] **Étape 7 : vérifier qu'aucun consommateur légitime n'est cassé**

Lancer : `php artisan test --filter="Api"`
Attendu : aucune régression. Si un test échoue parce qu'un compte non-admin appelait une route
`admin/*`, c'est le test qui décrivait le trou : le corriger pour utiliser un administrateur.

- [ ] **Étape 8 : commit**

```bash
git add app/Http/Middleware/EnsureApiAdmin.php app/Http/Kernel.php routes/api/admin.php tests/Feature/Api/Admin/AdminApiRoleGuardTest.php
git commit -m "fix(api): garder /api/admin/* par le rôle, pas seulement par le scope"
```

---

### Tâche 2 : rétablir la résolution des alias mobiles

**Fichiers :**
- Modifier : `mobile/provider/babel.config.js`
- Modifier : `mobile/provider/jest.config.ts` (`moduleNameMapper`)
- Test : `mobile/provider/__tests__/config/aliases.test.ts`

**Interfaces :**
- Produit : `@/parity`, `@/webview`, `@/finance` et `@brio/shared` résolvables à l'exécution.

- [ ] **Étape 1 : écrire le test qui échoue**

```ts
import fs from 'fs';
import path from 'path';

/**
 * Le tsconfig déclarait des alias que le résolveur Babel ignorait. `tsc` passait, l'application
 * plantait à l'import — un mode d'échec qu'aucun typage ne signale. Ce test compare les deux
 * sources et refuse qu'elles divergent.
 */
describe('alias de modules partagés', () => {
  const read = (p: string) => fs.readFileSync(path.join(__dirname, '..', '..', p), 'utf8');

  it('babel déclare tous les alias @/ que le tsconfig fait pointer vers shared', () => {
    const tsconfig = read('tsconfig.json');
    const babel = read('babel.config.js');

    const declared = [...tsconfig.matchAll(/"(@\/[a-zA-Z]+)":\s*\["\.\.\/shared/g)].map((m) => m[1]);

    expect(declared.length).toBeGreaterThan(10);
    for (const alias of declared) {
      expect(babel).toContain(`'${alias}'`);
    }
  });
});
```

- [ ] **Étape 2 : lancer le test, vérifier qu'il échoue**

Lancer : `cd mobile/provider && npx jest __tests__/config/aliases.test.ts`
Attendu : échec sur `@/parity` (premier alias manquant).

- [ ] **Étape 3 : compléter `babel.config.js`**

Ajouter dans l'objet `alias`, avant la ligne `'@': './src',` :

```js
            // Déclarés dans tsconfig.json mais absents ici : le typage passait, l'exécution
            // échouait à l'import. La console admin en dépend.
            '@/parity': '../shared/src/parity',
            '@/webview': '../shared/src/webview',
            '@/finance': '../shared/src/finance',
            '@brio/shared': '../shared/src',
```

- [ ] **Étape 4 : compléter `jest.config.ts`**

Ajouter dans `moduleNameMapper`, **avant** la ligne `'^@/(.*)$'` (l'ordre compte, le motif
générique capturerait sinon) :

```ts
    '^@/parity(.*)$': '<rootDir>/../shared/src/parity$1',
    '^@/webview(.*)$': '<rootDir>/../shared/src/webview$1',
    '^@/finance(.*)$': '<rootDir>/../shared/src/finance$1',
    '^@brio/shared(.*)$': '<rootDir>/../shared/src$1',
```

- [ ] **Étape 5 : relancer**

Lancer : `cd mobile/provider && npx jest __tests__/config/aliases.test.ts && npm run typecheck`
Attendu : test vert, typage vert.

- [ ] **Étape 6 : commit**

```bash
git add mobile/provider/babel.config.js mobile/provider/jest.config.ts mobile/provider/__tests__/config/aliases.test.ts
git commit -m "fix(mobile): aligner les alias Babel sur le tsconfig — le typage passait, l'import non"
```

---

### Tâche 3 : `is_admin` survit au redémarrage

**Fichiers :**
- Modifier : `app/Http/Controllers/Api/AuthMeController.php`
- Modifier : `mobile/shared/src/api/types.ts`
- Test : `tests/Feature/Api/Auth/AuthMeAdminFlagTest.php`

**Interfaces :**
- Produit : `User.is_admin?: boolean` côté mobile, servi par `/api/auth/login` (déjà) **et**
  `/api/auth/me` (nouveau).

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `login` sérialise `is_admin` ; `me` renvoyait `$user->toArray()`, qui ne le porte pas. Au
 * redémarrage de l'application, la session reprise perdait donc la qualité d'administrateur et
 * renvoyait l'admin dans l'espace prestataire.
 */
class AuthMeAdminFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_expose_la_qualite_d_administrateur(): void
    {
        $admin = User::factory()->create(['platform_role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_admin', true)
            ->assertJsonPath('user.is_admin', true);
    }

    public function test_me_ne_promeut_personne(): void
    {
        $client = User::factory()->create(['platform_role' => 'user']);
        Sanctum::actingAs($client, ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_admin', false);
    }
}
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `php artisan test --filter=AuthMeAdminFlagTest`
Attendu : échec, la clé `is_admin` est absente.

- [ ] **Étape 3 : compléter le contrôleur**

Dans `AuthMeController::__invoke`, après la ligne posant `is_premium` :

```php
        /*
         * La reprise de session doit dire la même chose que la connexion.
         *
         * `login` sérialise explicitement `is_admin` ; `me` renvoyait les attributs bruts, qui ne
         * le portent pas. L'application repartait donc en croyant l'administrateur simple
         * prestataire à chaque redémarrage — et l'envoyait dans un espace où rien ne lui répond.
         */
        $payload['is_admin'] = method_exists($user, 'isAdmin') && $user->isAdmin();
```

Poser cette ligne **avant** `$payload['user'] = $payload;`, sinon la forme imbriquée ne la porte pas.

- [ ] **Étape 4 : relancer**

Lancer : `php artisan test --filter=AuthMeAdminFlagTest`
Attendu : vert.

- [ ] **Étape 5 : compléter le type mobile**

Dans `mobile/shared/src/api/types.ts`, ajouter à l'interface `User` :

```ts
  /** Administrateur de plateforme. Sert d'aiguillage d'espace ; l'autorité reste le serveur. */
  is_admin?: boolean;
```

- [ ] **Étape 6 : commit**

```bash
git add app/Http/Controllers/Api/AuthMeController.php mobile/shared/src/api/types.ts tests/Feature/Api/Auth/AuthMeAdminFlagTest.php
git commit -m "fix(api): /auth/me oubliait is_admin — la session reprise dégradait l'admin"
```

---

### Tâche 4 : le registre de couverture et son test d'inventaire

**Fichiers :**
- Créer : `config/admin_console.php`
- Test : `tests/Feature/Admin/AdminConsoleInventoryTest.php`

**Interfaces :**
- Produit : `config('admin_console.groups')` (clé → libellé) et `config('admin_console.modules')`,
  chaque module valant
  `['key', 'title', 'group', 'icon', 'routes' => string[], 'coverage' => 'pending'|'descriptor'|'screen']`.

Le registre recense les **99** routes `admin/*` du routeur. Table de correspondance à saisir
(groupe → modules ; chaque module porte sa route principale, et ses sous-routes quand il en a) :

| Groupe | Modules (clé ← routes) |
|---|---|
| `pilotage` — Pilotage | dashboard ← `admin/dashboard` · home ← `admin/home` · business ← `admin/business-dashboard` · alerts ← `admin/alerts` · analytics ← `admin/analytics` · analytics-v2 ← `admin/analytics-v2` · cancellation-reasons ← `admin/analytics/cancellations` · readiness ← `admin/platform-readiness` · nps ← `admin/nps` · feedbacks ← `admin/feedbacks`, `admin/feedbacks/export`, `admin/feedbacks/export-csv` · modules ← `admin/modules` · outils ← `admin/outils`, `admin/export/csv`, `admin/export/pdf` |
| `operations` — Opérations | missions ← `admin/missions`, `admin/missions/{mission}`, `admin/missions/export/pdf` · planning ← `admin/planning` · calendar ← `admin/calendar`, `admin/calendar/settings` · availability ← `admin/availability` · presence ← `admin/presence` · trip-tracking ← `admin/trip-tracking` · ia-dispatch ← `admin/ia-dispatch` · matching ← `admin/matching` · orchestration ← `admin/orchestration` · quality ← `admin/quality`, `admin/quality/export/incidents.csv`, `admin/quality/export/missions.csv` · safety ← `admin/safety` · realtime ← `admin/realtime` · recurrence ← `admin/recurrence/{rendezVous}/serie`, `admin/rendez-vous/{rendezVous}`, `admin/rendez-vous-series/{series}/edit` · b2b-operations ← `admin/b2b/operations` · automation ← `admin/automation` |
| `personnes` — Personnes et comptes | users ← `admin/utilisateurs`, `admin/users` · entreprises ← `admin/entreprises` · sites ← `admin/sites` · teams ← `admin/teams-partners` · provider-registrations ← `admin/inscriptions-prestataires` · onboarding-providers ← `admin/onboarding-providers` · onboarding-documents ← `admin/onboarding-documents`, `admin/onboarding-documents/{document}/file` · onboarding-v2 ← `admin/onboarding-v2` · enterprise-approvals ← `admin/approbations-entreprises` · kyc ← `admin/kyc` · kyb ← `admin/kyb-v2` · badges ← `admin/badges` · premium ← `admin/premium-clients`, `admin/premium/clients` · stripe-connect ← `admin/stripe-connect-providers` |
| `catalogue` — Catalogue et prix | catalog ← `admin/catalogue`, `admin/parcours/{trade}` · services ← `admin/services` · trades ← `admin/trades`, `admin/trades/{trade}/pricing` · pricing ← `admin/pricing-v2` · bundles ← `admin/bundles` · zones ← `admin/zones` · countries ← `admin/countries` · international ← `admin/international` |
| `argent` — Argent et conformité | finance ← `admin/finance` · accounting ← `admin/accounting-v2` · b2b-invoices ← `admin/b2b/facturation-mensuelle` · credits ← `admin/credits-clients` · tips ← `admin/tips` · fx ← `admin/fx` · stripe ← `admin/stripe` · subscriptions ← `admin/subscriptions-v2` · insurance ← `admin/insurance` · cancellations ← `admin/cancellations-v2` · disputes ← `admin/disputes` · risk ← `admin/risk` · contracts ← `admin/contracts-v2` |
| `croissance` — Croissance | marketing ← `admin/marketing` · promo-codes ← `admin/promotions/codes` · promo-campaigns ← `admin/promotions/campagnes` · referrals ← `admin/promotions/parrainages` · loyalty ← `admin/loyalty`, `admin/loyalty/rewards` · ratings ← `admin/avis` · emails ← `admin/emails` · sms ← `admin/sms` · push ← `admin/push` · notification-preferences ← `admin/notification-preferences` |
| `plateforme` — Plateforme | audit ← `admin/audit`, `admin/audit/logs` · gdpr ← `admin/gdpr` · feature-flags ← `admin/feature-flags` · api-tokens ← `admin/api-tokens-v2` · webhooks ← `admin/webhooks-v2` · geolocation ← `admin/geolocation-v2` · translations ← `admin/translations` · chat ← `admin/chat-v2` · fleet ← `admin/fleet-v2` |

Tous les modules naissent en `'coverage' => 'pending'`. Les icônes sont des noms Ionicons
(`grid-outline`, `people-outline`, `pricetag-outline`, `cash-outline`, `megaphone-outline`,
`construct-outline`, `briefcase-outline`…).

- [ ] **Étape 1 : écrire le test d'inventaire (il échouera : le fichier n'existe pas)**

```php
<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * La garantie « rien n'est oublié » de la console mobile.
 *
 * Ce test — et non un jugement — décide quand le chantier est fini : toute page ajoutée au web
 * sans équivalent déclaré fait rougir la suite.
 */
class AdminConsoleInventoryTest extends TestCase
{
    /** @return list<string> */
    private function webAdminRoutes(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (! str_starts_with($route->uri(), 'admin')) {
                continue;
            }
            $uris[] = $route->uri();
        }

        return array_values(array_unique($uris));
    }

    /** @return list<string> */
    private function declaredRoutes(): array
    {
        $declared = [];

        foreach (config('admin_console.modules') as $module) {
            foreach ($module['routes'] as $uri) {
                $declared[] = $uri;
            }
        }

        return $declared;
    }

    public function test_chaque_page_admin_du_web_est_declaree(): void
    {
        $missing = array_diff($this->webAdminRoutes(), $this->declaredRoutes());

        $this->assertSame([], array_values($missing),
            'Pages admin absentes de config/admin_console.php : '.implode(', ', $missing));
    }

    public function test_aucune_route_declaree_n_est_morte(): void
    {
        $stale = array_diff($this->declaredRoutes(), $this->webAdminRoutes());

        $this->assertSame([], array_values($stale),
            'Routes déclarées qui n’existent plus : '.implode(', ', $stale));
    }

    public function test_aucune_route_n_est_declaree_deux_fois(): void
    {
        $declared = $this->declaredRoutes();
        $duplicates = array_keys(array_filter(array_count_values($declared), fn ($n) => $n > 1));

        $this->assertSame([], $duplicates,
            'Routes déclarées par plusieurs modules : '.implode(', ', $duplicates));
    }

    public function test_chaque_module_est_bien_forme(): void
    {
        $groups = array_keys(config('admin_console.groups'));

        foreach (config('admin_console.modules') as $module) {
            $this->assertArrayHasKey('key', $module);
            $this->assertNotEmpty($module['title']);
            $this->assertContains($module['group'], $groups, "Groupe inconnu pour {$module['key']}");
            $this->assertNotEmpty($module['icon']);
            $this->assertNotEmpty($module['routes']);
            $this->assertContains($module['coverage'], ['pending', 'descriptor', 'screen']);
        }
    }

    public function test_les_cles_de_module_sont_uniques(): void
    {
        $keys = array_column(config('admin_console.modules'), 'key');

        $this->assertSame(count($keys), count(array_unique($keys)));
    }
}
```

- [ ] **Étape 2 : lancer, constater l'échec**

Lancer : `php artisan test --filter=AdminConsoleInventoryTest`
Attendu : erreur — `config('admin_console.modules')` vaut `null`.

- [ ] **Étape 3 : écrire `config/admin_console.php`** selon la table ci-dessus, en-tête compris :

```php
<?php

/**
 * Registre de couverture de la console d'administration mobile.
 *
 * Le web porte 99 routes d'administration. Ce fichier dit, pour chacune, comment mobile la sert :
 *   pending    — pas encore couverte (visible dans l'annuaire, marquée « à venir »)
 *   descriptor — servie par le moteur de console générique
 *   screen     — servie par un écran natif sur-mesure
 *
 * `AdminConsoleInventoryTest` refuse toute divergence entre ce registre et le routeur : une page
 * admin ajoutée au web sans entrée ici fait échouer la suite. C'est volontaire — c'est la seule
 * garantie mécanique que rien n'est oublié.
 */
return [
    'groups' => [
        'pilotage' => 'Pilotage',
        'operations' => 'Opérations',
        'personnes' => 'Personnes et comptes',
        'catalogue' => 'Catalogue et prix',
        'argent' => 'Argent et conformité',
        'croissance' => 'Croissance',
        'plateforme' => 'Plateforme',
    ],

    'modules' => [
        // … une entrée par ligne de la table de correspondance du plan …
    ],
];
```

- [ ] **Étape 4 : relancer jusqu'au vert**

Lancer : `php artisan test --filter=AdminConsoleInventoryTest`
Attendu : cinq tests verts. Les messages d'échec nomment précisément la route manquante ou morte.

- [ ] **Étape 5 : commit**

```bash
git add config/admin_console.php tests/Feature/Admin/AdminConsoleInventoryTest.php
git commit -m "feat(admin-mobile): recenser les 99 pages admin et leur état de couverture"
```

---

### Tâche 5 : `GET /api/admin/catalog`

**Fichiers :**
- Créer : `app/Http/Controllers/Api/Admin/AdminCatalogController.php`
- Modifier : `routes/api/admin.php`
- Test : `tests/Feature/Api/Admin/AdminCatalogEndpointTest.php`

**Interfaces :**
- Produit : `GET /api/admin/catalog` →
  `{ok: true, groups: [{key, title, modules: [{key, title, icon, coverage, route}]}], counts: {total, covered, pending}}`.

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCatalogEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_annuaire_rend_tous_les_modules_groupes(): void
    {
        Sanctum::actingAs(User::factory()->create(['platform_role' => 'admin']), ['*']);

        $res = $this->getJson('/api/admin/catalog')->assertOk();

        $res->assertJsonPath('ok', true);

        $groups = $res->json('groups');
        $this->assertSame(array_keys(config('admin_console.groups')), array_column($groups, 'key'));

        $modules = collect($groups)->flatMap(fn ($g) => $g['modules']);
        $this->assertSame(count(config('admin_console.modules')), $modules->count());
        $this->assertSame($modules->count(), $res->json('counts.total'));
    }

    public function test_l_annuaire_compte_ce_qui_reste_a_couvrir(): void
    {
        Sanctum::actingAs(User::factory()->create(['platform_role' => 'admin']), ['*']);

        $res = $this->getJson('/api/admin/catalog')->assertOk();

        $this->assertSame(
            $res->json('counts.total'),
            $res->json('counts.covered') + $res->json('counts.pending'),
        );
    }

    public function test_un_non_admin_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['platform_role' => 'user']), ['*']);

        $this->getJson('/api/admin/catalog')->assertStatus(403);
    }
}
```

- [ ] **Étape 2 : lancer, constater le 404**

Lancer : `php artisan test --filter=AdminCatalogEndpointTest`

- [ ] **Étape 3 : écrire le contrôleur**

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * L'annuaire que voit l'administrateur sur mobile.
 *
 * Il expose TOUT le registre, y compris les modules encore non couverts — marqués comme tels.
 * Masquer ce qui n'est pas prêt donnerait une application qui a l'air complète et un chantier
 * dont personne ne peut mesurer l'avancement.
 */
class AdminCatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $modules = collect(config('admin_console.modules'));

        $groups = collect(config('admin_console.groups'))
            ->map(fn (string $title, string $key) => [
                'key' => $key,
                'title' => $title,
                'modules' => $modules
                    ->where('group', $key)
                    ->map(fn (array $m) => [
                        'key' => $m['key'],
                        'title' => $m['title'],
                        'icon' => $m['icon'],
                        'coverage' => $m['coverage'],
                        'route' => $m['routes'][0],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'groups' => $groups,
            'counts' => [
                'total' => $modules->count(),
                'covered' => $modules->where('coverage', '!=', 'pending')->count(),
                'pending' => $modules->where('coverage', 'pending')->count(),
            ],
        ]);
    }
}
```

- [ ] **Étape 4 : router**

Dans `routes/api/admin.php`, à l'intérieur du groupe gardé :

```php
    // Annuaire de la console mobile — le registre de couverture, servi tel quel.
    Route::get('/admin/catalog', AdminCatalogController::class);
```

- [ ] **Étape 5 : relancer, puis commit**

```bash
php artisan test --filter=AdminCatalogEndpointTest
git add app/Http/Controllers/Api/Admin/AdminCatalogController.php routes/api/admin.php tests/Feature/Api/Admin/AdminCatalogEndpointTest.php
git commit -m "feat(admin-mobile): servir l'annuaire des modules d'administration"
```

---

### Tâche 6 : `GET /api/admin/overview`

**Fichiers :**
- Créer : `app/Http/Controllers/Api/Admin/AdminOverviewController.php`
- Modifier : `routes/api/admin.php`
- Test : `tests/Feature/Api/Admin/AdminOverviewEndpointTest.php`

**Interfaces :**
- Produit : `GET /api/admin/overview` → `{ok: true, kpis: [{key, label, value, hint}]}`.

Sept indicateurs, tous calculés sur des colonnes vérifiées : comptes (`users`), réservations du
jour (`bookings.scheduled_date`), réservations en attente (`bookings.status`), missions en cours
(`missions.status`), litiges ouverts (`customer_claims.status`), vérifications KYC en attente
(`kyc_verifications.status`), prestataires en attente de validation
(`provider_profiles.verification_status`).

- [ ] **Étape 1 : vérifier les valeurs de statut réelles avant d'écrire quoi que ce soit**

```bash
php artisan tinker --execute="
echo 'bookings.status: '.implode(',', App\Models\Booking::query()->distinct()->pluck('status')->all()).PHP_EOL;
echo 'missions.status: '.implode(',', App\Models\Mission::query()->distinct()->pluck('status')->all()).PHP_EOL;
echo 'claims.status: '.implode(',', App\Models\CustomerClaim::query()->distinct()->pluck('status')->all()).PHP_EOL;
echo 'kyc.status: '.implode(',', App\Models\KycVerification::query()->distinct()->pluck('status')->all()).PHP_EOL;
"
```

Les constantes de statut du projet priment sur ce relevé : s'il existe une énumération ou des
constantes de classe, les utiliser plutôt que des chaînes en dur.

- [ ] **Étape 2 : écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOverviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_accueil_rend_sept_indicateurs_chiffres(): void
    {
        Sanctum::actingAs(User::factory()->create(['platform_role' => 'admin']), ['*']);

        $res = $this->getJson('/api/admin/overview')->assertOk();

        $kpis = $res->json('kpis');
        $this->assertCount(7, $kpis);

        foreach ($kpis as $kpi) {
            $this->assertArrayHasKey('key', $kpi);
            $this->assertNotEmpty($kpi['label']);
            $this->assertIsInt($kpi['value']);
        }
    }

    public function test_l_accueil_compte_les_comptes_existants(): void
    {
        $admin = User::factory()->create(['platform_role' => 'admin']);
        User::factory()->count(3)->create();
        Sanctum::actingAs($admin, ['*']);

        $users = collect($this->getJson('/api/admin/overview')->json('kpis'))
            ->firstWhere('key', 'users');

        $this->assertSame(User::count(), $users['value']);
    }

    public function test_un_non_admin_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['platform_role' => 'user']), ['*']);

        $this->getJson('/api/admin/overview')->assertStatus(403);
    }
}
```

- [ ] **Étape 3 : écrire le contrôleur** en n'employant que les statuts relevés à l'étape 1, chaque
  indicateur enveloppé pour qu'une table absente rende `0` plutôt que de faire tomber l'accueil
  entier (`Schema::hasTable` en garde, comme le fait déjà `ChannelPolicy`).

- [ ] **Étape 4 : router, relancer, commit**

```bash
php artisan test --filter=AdminOverviewEndpointTest
git add app/Http/Controllers/Api/Admin/AdminOverviewController.php routes/api/admin.php tests/Feature/Api/Admin/AdminOverviewEndpointTest.php
git commit -m "feat(admin-mobile): servir les indicateurs d'accueil de la console"
```

---

### Tâche 7 : aiguiller l'administrateur vers son espace

**Fichiers :**
- Créer : `mobile/provider/src/admin/AdminNavigator.tsx`
- Créer : `mobile/provider/src/admin/types.ts`
- Créer : `mobile/provider/src/screens/SpaceSwitcherScreen.tsx`
- Modifier : `mobile/provider/src/navigation/RootNavigator.tsx`
- Modifier : `mobile/provider/src/navigation/types.ts`
- Test : `mobile/provider/__tests__/admin/RootNavigator.routing.test.tsx`

**Interfaces :**
- Consomme : `useAuth().user.is_admin` (tâche 3).
- Produit : `AdminTabParamList = { AdminHome: undefined; AdminDirectory: undefined; AdminProfile: undefined }`
  et l'entrée `AdminSpace: NavigatorScreenParams<AdminTabParamList> | undefined` dans
  `RootStackParamList`.

- [ ] **Étape 1 : écrire les tests qui échouent**

```tsx
import React from 'react';
import { render, screen } from '@testing-library/react-native';

// Les trois cas d'aiguillage. Le troisième est celui qui casse aujourd'hui : un administrateur
// traverse le gate d'onboarding prestataire, puis atterrit sur des écrans gardés role:employe.
describe('aiguillage d’espace au démarrage', () => {
  it('un administrateur entre dans l’espace admin', async () => {
    mockUser({ is_admin: true, is_provider: false });
    renderRoot();
    expect(await screen.findByTestId('admin-navigator')).toBeTruthy();
  });

  it('un prestataire garde son espace', async () => {
    mockUser({ is_admin: false, is_provider: true });
    renderRoot();
    expect(await screen.findByTestId('root-navigator')).toBeTruthy();
    expect(screen.queryByTestId('admin-navigator')).toBeNull();
  });

  it('une double casquette choisit son espace', async () => {
    mockUser({ is_admin: true, is_provider: true });
    renderRoot();
    expect(await screen.findByTestId('space-switcher')).toBeTruthy();
  });

  it('l’administrateur ne passe pas par le parcours prestataire', async () => {
    mockUser({ is_admin: true, is_provider: false });
    mockOnboarding({ complete: false });
    renderRoot();
    expect(await screen.findByTestId('admin-navigator')).toBeTruthy();
  });
});
```

Les aides `mockUser`, `mockOnboarding` et `renderRoot` se calquent sur les tests existants de
`mobile/provider/__tests__/screens/` (mêmes `jest.mock` sur `@/auth` et `@/onboarding`).

- [ ] **Étape 2 : lancer, constater l'échec**

Lancer : `cd mobile/provider && npx jest __tests__/admin/RootNavigator.routing.test.tsx`

- [ ] **Étape 3 : écrire `AdminNavigator`** — trois onglets (`AdminHome`, `AdminDirectory`,
  `AdminProfile`), `testID="admin-navigator"` sur le conteneur, **sans** `usePresenceHeartbeat`.

- [ ] **Étape 4 : brancher l'aiguillage dans `RootNavigator`**

L'ordre des conditions est le point sensible : la qualité d'administrateur se teste **avant** le
gate d'onboarding prestataire, sinon un admin sans dossier reste enfermé dans le parcours.

- [ ] **Étape 5 : relancer, puis commit**

```bash
cd mobile/provider && npx jest __tests__/admin && npm run typecheck
git add mobile/provider/src/admin mobile/provider/src/screens/SpaceSwitcherScreen.tsx mobile/provider/src/navigation mobile/provider/__tests__/admin
git commit -m "feat(admin-mobile): ouvrir l'espace admin dans l'application prestataire"
```

---

### Tâche 8 : l'accueil chiffré

**Fichiers :**
- Créer : `mobile/provider/src/admin/AdminHomeScreen.tsx`, `mobile/provider/src/admin/hooks.ts`
- Test : `mobile/provider/__tests__/admin/AdminHomeScreen.test.tsx`

**Interfaces :**
- Consomme : `GET /api/admin/overview` (tâche 6).
- Produit : `useAdminOverview(): { data, isLoading, isError, refetch }` (React Query).

- [ ] **Étape 1 : écrire les tests** — chargement, sept cartes rendues, état d'erreur avec
  possibilité de réessayer, pull-to-refresh. Réutiliser `KPICard` de `@/ui`.
- [ ] **Étape 2 : lancer, constater l'échec.**
- [ ] **Étape 3 : écrire le hook puis l'écran.**
- [ ] **Étape 4 : relancer, commit.**

```bash
git commit -m "feat(admin-mobile): accueil chiffré de la console"
```

---

### Tâche 9 : l'annuaire honnête

**Fichiers :**
- Créer : `mobile/provider/src/admin/AdminDirectoryScreen.tsx`
- Test : `mobile/provider/__tests__/admin/AdminDirectoryScreen.test.tsx`

**Interfaces :**
- Consomme : `GET /api/admin/catalog` (tâche 5).

- [ ] **Étape 1 : écrire les tests** — les sept groupes sont rendus ; la recherche filtre par
  titre ; un module `pending` porte une marque « à venir » et n'est pas navigable ; un module
  `screen` ou `descriptor` l'est ; le compteur d'avancement (`couverts / total`) est affiché.
- [ ] **Étape 2 : lancer, constater l'échec.**
- [ ] **Étape 3 : écrire l'écran** — `SectionList` virtualisée, en-têtes de groupe collants.
- [ ] **Étape 4 : relancer, commit.**

```bash
git commit -m "feat(admin-mobile): annuaire des 99 modules, avancement compris"
```

---

### Tâche 10 : portail de sous-projet

- [ ] **Étape 1 : style et analyse statique**

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

- [ ] **Étape 2 : suite PHP complète**

```bash
php artisan test
```

Attendu : aucune régression. Le seul échec toléré est un échec pré-existant identifié comme tel
sur `main` — le vérifier en le rejouant sur `main` avant de conclure.

- [ ] **Étape 3 : mobile**

```bash
cd mobile/provider && npm run typecheck && npm test
```

- [ ] **Étape 4 : contre-épreuve MySQL**

Rejouer les tests API nouveaux contre MySQL (la suite tourne sur SQLite, qui accepte des requêtes
que MySQL strict refuse) :

```bash
DB_CONNECTION=mysql php artisan test --filter="AdminApiRoleGuardTest|AdminCatalogEndpointTest|AdminOverviewEndpointTest|AdminConsoleInventoryTest"
```

- [ ] **Étape 5 : commit du portail et bilan**

```bash
git commit --allow-empty -m "chore(admin-mobile): sous-projet A vert — sécurité, coquille, annuaire"
```

## Auto-revue du plan

- **Couverture de la spec.** Prérequis sécurité → tâche 1. Alias Babel → tâche 2. Coquille admin,
  contournement du gate prestataire, sélecteur d'espace, absence de battement de présence →
  tâche 7. Accueil KPI → tâches 6 et 8. Annuaire des 91 pages (99 routes) → tâches 4, 5 et 9.
  Garantie d'exhaustivité → tâche 4. Portail de vérification → tâche 10. Le registre de parité
  n'est pas touché, conformément à la spec.
- **Cohérence des noms.** `coverage` prend les mêmes trois valeurs dans le config, le test
  d'inventaire, le contrôleur d'annuaire et l'écran. `is_admin` est produit en tâche 3 et consommé
  en tâche 7. `AdminTabParamList` est déclaré en tâche 7 et utilisé en tâches 8 et 9.
- **Point ouvert assumé.** `Enforce2FA` garde le web ; l'API n'a pas d'équivalent. Si
  `auth.enforce_2fa_for_admins` est actif, un administrateur sans double authentification est
  bloqué sur le web mais servi sur mobile. À trancher en fin de sous-projet A, avec l'utilisateur.
