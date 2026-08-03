# Catalogue géographique — plan d'implémentation (lot 1 : navigation)

> **Pour les exécutants :** ce plan s'exécute tâche par tâche. Chaque case `- [ ]` est une étape de
> 2 à 5 minutes. Le portail de vérification doit être vert avant de passer à la tâche suivante.

**But :** faire de `/admin/catalogue` une descente en trois niveaux — Pays → Zones → Secteurs &
métiers — où chaque niveau se gère indépendamment du précédent.

**Architecture :** trois composants Livewire sous la même racine de route. Le niveau 3 est l'écran
actuel, contextualisé à une zone. Le niveau 2 réutilise `GestionZones` avec le pays verrouillé,
plutôt qu'un second CRUD de zones. Un service pur porte les règles de suppression et d'héritage
d'état, pour qu'elles soient testables sans monter d'interface.

**Pile :** Laravel 12, Livewire 3, PHPUnit, Pint, PHPStan.

## Contraintes globales

- **Activation PAR ZONE uniquement.** Le pays n'organise que les zones. Il n'existe **pas** de
  table `country_trade` : un pays « a » un métier si au moins une de ses zones l'a — c'est un
  calcul, jamais un réglage.
- **`trade_zone_pricing` est la source unique du couple métier × zone.** L'existence de la ligne dit
  « offert ici » ; `is_active` est l'interrupteur. Ne rien écrire dans `trade_zone_settings`.
- **Supprimer n'est pas désactiver.** Aucune cascade sur les pays ni les zones : refus motivé avec
  le compte de ce qui bloque.
- **Désactiver un pays est une LECTURE, pas une écriture.** Aucune mise à jour en chaîne sur les
  zones : la réactivation doit restaurer exactement l'état d'avant.
- **Ce lot ne branche rien côté client.** L'écran doit le DIRE dans l'interface — voir tâche 7.
- Commentaires, libellés et messages en français, expliquant le POURQUOI.
- Portail par tâche : `php artisan test --filter <le test de la tâche>`, puis `vendor/bin/pint` et
  `vendor/bin/phpstan analyse` avant chaque commit.

## Pièges vérifiés sur ce projet, à ne pas redécouvrir

- **`Country::$fillable` n'expose PAS `booking_enabled`, `market_stage` ni `settings`**, alors que
  les colonnes existent. Un `Country::create([... 'booking_enabled' => true])` les perd en silence.
  La tâche 1 les ajoute.
- **`#[Computed]` de Livewire ne met en cache que l'accès PROPRIÉTÉ** (`$this->truc`), jamais
  `$this->truc()`. Un appel en méthode réexécute la requête à chaque fois.
- **La suite tourne sur SQLite, l'application sur MySQL strict.** Ne pas s'appuyer sur l'ordre des
  clés JSON ni sur une contrainte non vérifiée par SQLite.
- **`Livewire::test()` exige un utilisateur** : `$this->actingAs(User::factory()->create(['role' =>
  'admin']))`, comme dans `tests/Feature/OrderEngine/CatalogCenterTest.php`.

---

## Fichiers

**Créés**

| Fichier | Responsabilité |
|---|---|
| `app/Services/Catalog/GeoGuard.php` | Les règles pures : peut-on supprimer ? un objet est-il joignable ? |
| `app/Livewire/Admin/OrderEngine/CountryCenter.php` | Niveau 1 — CRUD des pays |
| `app/Livewire/Admin/OrderEngine/ZoneCenter.php` | Niveau 2 — zones d'un pays (délègue à `GestionZones`) |
| `resources/views/livewire/admin/order-engine/country-center.blade.php` | Vue niveau 1 |
| `resources/views/livewire/admin/order-engine/zone-center.blade.php` | Vue niveau 2 |
| `tests/Feature/OrderEngine/GeoGuardTest.php` | Les règles, sans interface |
| `tests/Feature/OrderEngine/CountryCenterTest.php` | Niveau 1 |
| `tests/Feature/OrderEngine/ZoneCenterTest.php` | Niveau 2 |
| `tests/Feature/OrderEngine/CatalogZoneScopeTest.php` | Niveau 3 contextualisé + activation par zone |

**Modifiés**

| Fichier | Changement |
|---|---|
| `app/Models/Country.php` | `$fillable` complété, `$casts`, relation `zonesActives` |
| `routes/admin.php` | Les trois routes de la descente |
| `app/Livewire/Admin/OrderEngine/CatalogCenter.php` | Prend une zone en paramètre, filtre, active/désactive un métier |
| `resources/views/livewire/admin/order-engine/catalog-center.blade.php` | Fil d'Ariane + bandeau d'honnêteté + interrupteur par métier |
| `tests/Feature/OrderEngine/CatalogCenterTest.php` | Adapté à la signature de `mount()` |

---

### Tâche 1 : le modèle `Country` dit la vérité

**Fichiers :** Modifier `app/Models/Country.php` · Test `tests/Feature/OrderEngine/GeoGuardTest.php`

**Interfaces produites :** `Country::$fillable` accepte `booking_enabled`, `market_stage`,
`settings` ; `$casts` les typent.

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_modele_pays_accepte_les_colonnes_operationnelles(): void
    {
        // `booking_enabled` existait en base sans être `fillable` : un create le perdait en
        // silence, et l'écran d'administration aurait affiché « réservations fermées » sans
        // qu'aucune erreur ne soit levée.
        $pays = Country::create([
            'iso_code' => 'FR',
            'name' => 'France',
            'currency_code' => 'EUR',
            'is_active' => true,
            'booking_enabled' => true,
            'market_stage' => 'pilot',
        ]);

        $this->assertTrue($pays->fresh()->booking_enabled);
        $this->assertSame('pilot', $pays->fresh()->market_stage);
    }
}
```

- [ ] **Étape 2 : lancer, constater l'échec**

Run : `php artisan test --filter test_le_modele_pays_accepte_les_colonnes_operationnelles`
Attendu : ÉCHEC — `booking_enabled` vaut `false` ou `null`.

- [ ] **Étape 3 : compléter le modèle**

Dans `app/Models/Country.php`, ajouter à `$fillable` après `'is_active',` :

```php
        'booking_enabled',
        'market_stage',
        'settings',
```

et remplacer `$casts` par :

```php
    protected $casts = [
        'is_active' => 'boolean',
        'booking_enabled' => 'boolean',
        'settings' => 'array',
    ];
```

- [ ] **Étape 4 : relancer** — Attendu : PASS.

- [ ] **Étape 5 : commit**

```bash
git add app/Models/Country.php tests/Feature/OrderEngine/GeoGuardTest.php
git commit -m "fix(admin): le modèle pays perdait booking_enabled en silence"
```

---

### Tâche 2 : les règles, avant toute interface

**Fichiers :** Créer `app/Services/Catalog/GeoGuard.php` ·
Test `tests/Feature/OrderEngine/GeoGuardTest.php` (compléter)

**Interfaces produites :**

```php
GeoGuard::raisonsDeNePasSupprimerPays(Country $pays): array   // ['6 zones rattachées', …] ; [] = supprimable
GeoGuard::raisonsDeNePasSupprimerZone(ServiceZone $zone): array
GeoGuard::zoneEstJoignable(ServiceZone $zone): bool           // zone réservable ET pays actif
```

**Pourquoi un service et non des méthodes de modèle :** ces règles seront lues par trois écrans et
par le moteur au lot 2. Les poser dans un objet sans dépendance à Livewire les rend testables sans
monter d'interface — et c'est là qu'on veut les tester, parce qu'une règle de suppression fausse ne
se voit qu'au moment où elle détruit quelque chose.

- [ ] **Étape 1 : écrire les tests qui échouent**

```php
    public function test_un_pays_qui_porte_des_zones_ne_se_supprime_pas(): void
    {
        $pays = Country::factory()->create();
        ServiceZone::factory()->count(3)->create(['country_id' => $pays->id]);

        $raisons = app(GeoGuard::class)->raisonsDeNePasSupprimerPays($pays);

        // Le message porte le COMPTE : « ça ne se supprime pas » sans dire pourquoi oblige à
        // ouvrir la base pour comprendre.
        $this->assertNotEmpty($raisons);
        $this->assertStringContainsString('3', $raisons[0]);
    }

    public function test_un_pays_sans_rien_se_supprime(): void
    {
        $pays = Country::factory()->create();

        $this->assertSame([], app(GeoGuard::class)->raisonsDeNePasSupprimerPays($pays));
    }

    public function test_une_zone_qui_porte_des_reservations_ne_se_supprime_pas(): void
    {
        $zone = ServiceZone::factory()->create();
        Booking::factory()->create(['service_zone_id' => $zone->id]);

        $this->assertNotEmpty(app(GeoGuard::class)->raisonsDeNePasSupprimerZone($zone));
    }

    public function test_eteindre_le_pays_rend_ses_zones_injoignables_sans_les_modifier(): void
    {
        $pays = Country::factory()->create(['is_active' => true]);
        $zone = ServiceZone::factory()->create([
            'country_id' => $pays->id,
            'is_bookable' => true,
            'status' => 'active',
        ]);

        $this->assertTrue(app(GeoGuard::class)->zoneEstJoignable($zone->fresh()));

        $pays->update(['is_active' => false]);

        // La zone devient injoignable — mais SON PROPRE état n'a pas bougé. C'est ce qui permet à
        // la réactivation du pays de restaurer exactement l'état d'avant, y compris les zones qui
        // étaient déjà éteintes pour leur propre raison.
        $this->assertFalse(app(GeoGuard::class)->zoneEstJoignable($zone->fresh()));
        $this->assertTrue($zone->fresh()->is_bookable);
        $this->assertSame('active', $zone->fresh()->status);
    }
```

Ajouter en tête du fichier :

```php
use App\Models\Booking;
use App\Models\ServiceZone;
use App\Services\Catalog\GeoGuard;
```

- [ ] **Étape 2 : lancer, constater l'échec**

Run : `php artisan test --filter GeoGuardTest`
Attendu : ÉCHEC — `Class "App\Services\Catalog\GeoGuard" not found`.

- [ ] **Étape 3 : écrire le service**

```php
<?php

namespace App\Services\Catalog;

use App\Models\Booking;
use App\Models\Country;
use App\Models\ServiceZone;

/**
 * Les règles géographiques du catalogue, hors de toute interface.
 *
 * POURQUOI UN SERVICE. Trois écrans les liront, et le moteur tarifaire au lot suivant. Une règle
 * de suppression fausse ne se manifeste qu'au moment où elle détruit quelque chose : il faut
 * pouvoir la tester sans monter d'écran.
 */
class GeoGuard
{
    /**
     * Ce qui empêche de supprimer un pays. Tableau vide = suppression permise.
     *
     * On rend des RAISONS et non un booléen : « ça ne se supprime pas » sans dire pourquoi oblige
     * à ouvrir la base pour comprendre, et l'administrateur n'y a pas accès.
     */
    public function raisonsDeNePasSupprimerPays(Country $pays): array
    {
        $raisons = [];

        $zones = $pays->serviceZones()->count();

        if ($zones > 0) {
            $raisons[] = "{$zones} zone(s) rattachée(s) à ce pays";
        }

        return $raisons;
    }

    /** Ce qui empêche de supprimer une zone. Tableau vide = suppression permise. */
    public function raisonsDeNePasSupprimerZone(ServiceZone $zone): array
    {
        $raisons = [];

        $reservations = Booking::query()->where('service_zone_id', $zone->id)->count();

        if ($reservations > 0) {
            // Supprimer emporterait de l'historique de facturation, qui doit rester consultable
            // bien après la fermeture d'une zone.
            $raisons[] = "{$reservations} réservation(s) rattachée(s) à cette zone";
        }

        return $raisons;
    }

    /**
     * Une zone est-elle atteignable par un client ?
     *
     * C'est une LECTURE et jamais une écriture. Éteindre un pays ne doit pas modifier ses zones :
     * sinon la réactivation ne saurait plus lesquelles étaient éteintes pour leur propre raison,
     * et les rallumerait toutes.
     */
    public function zoneEstJoignable(ServiceZone $zone): bool
    {
        if (! $zone->is_bookable || $zone->status !== 'active') {
            return false;
        }

        return (bool) $zone->country?->is_active;
    }
}
```

- [ ] **Étape 4 : ajouter la relation manquante**

`GeoGuard::zoneEstJoignable()` lit `$zone->country`. Vérifier qu'elle existe dans
`app/Models/ServiceZone.php` ; sinon ajouter :

```php
    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
```

- [ ] **Étape 5 : relancer** — Attendu : les 5 tests passent.

- [ ] **Étape 6 : commit**

```bash
git add app/Services/Catalog/GeoGuard.php app/Models/ServiceZone.php tests/Feature/OrderEngine/GeoGuardTest.php
git commit -m "feat(admin): les règles géographiques, testables sans interface"
```

---

### Tâche 3 : niveau 1 — les pays

**Fichiers :** Créer `app/Livewire/Admin/OrderEngine/CountryCenter.php`,
`resources/views/livewire/admin/order-engine/country-center.blade.php` ·
Test `tests/Feature/OrderEngine/CountryCenterTest.php`

**Interfaces consommées :** `GeoGuard::raisonsDeNePasSupprimerPays()`
**Interfaces produites :** composant Livewire monté sur `/admin/catalogue`, méthodes publiques
`enregistrer()`, `basculerActivation(int $id)`, `supprimer(int $id)`, `nouveau()`, `editer(int $id)`

- [ ] **Étape 1 : écrire les tests qui échouent**

```php
<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CountryCenter;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le premier niveau de la descente : les pays.
 *
 * Ce que ces tests protègent surtout, c'est la règle « supprimer n'est pas désactiver ». Une
 * cascade sur un pays emporterait ses zones, et avec elles l'historique de facturation qui s'y
 * rattache — un dégât qu'aucun écran ne rendrait visible avant qu'il soit fait.
 */
class CountryCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_il_liste_les_pays(): void
    {
        Country::factory()->create(['name' => 'Belgique']);
        Country::factory()->create(['name' => 'France']);

        Livewire::test(CountryCenter::class)
            ->assertOk()
            ->assertSee('Belgique')
            ->assertSee('France');
    }

    public function test_il_ajoute_un_pays(): void
    {
        Livewire::test(CountryCenter::class)
            ->call('nouveau')
            ->set('formulaire.name', 'France')
            ->set('formulaire.iso_code', 'FR')
            ->set('formulaire.currency_code', 'EUR')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('countries', ['iso_code' => 'FR', 'name' => 'France']);
    }

    public function test_il_refuse_deux_pays_au_meme_code_iso(): void
    {
        Country::factory()->create(['iso_code' => 'FR']);

        Livewire::test(CountryCenter::class)
            ->call('nouveau')
            ->set('formulaire.name', 'France bis')
            ->set('formulaire.iso_code', 'FR')
            ->set('formulaire.currency_code', 'EUR')
            ->call('enregistrer')
            ->assertHasErrors(['formulaire.iso_code']);
    }

    public function test_il_bascule_l_activation_sans_toucher_aux_zones(): void
    {
        $pays = Country::factory()->create(['is_active' => true]);
        $zone = ServiceZone::factory()->create([
            'country_id' => $pays->id,
            'is_bookable' => true,
            'status' => 'active',
        ]);

        Livewire::test(CountryCenter::class)->call('basculerActivation', $pays->id);

        $this->assertFalse($pays->fresh()->is_active);
        // La règle du chantier : éteindre un pays est une lecture. Les zones ne bougent pas.
        $this->assertTrue($zone->fresh()->is_bookable);
        $this->assertSame('active', $zone->fresh()->status);
    }

    public function test_il_refuse_de_supprimer_un_pays_qui_porte_des_zones(): void
    {
        $pays = Country::factory()->create();
        ServiceZone::factory()->create(['country_id' => $pays->id]);

        Livewire::test(CountryCenter::class)
            ->call('supprimer', $pays->id)
            ->assertSee('zone(s) rattachée(s)');

        $this->assertDatabaseHas('countries', ['id' => $pays->id]);
    }

    public function test_il_supprime_un_pays_sans_rien_dessous(): void
    {
        $pays = Country::factory()->create();

        Livewire::test(CountryCenter::class)->call('supprimer', $pays->id);

        $this->assertDatabaseMissing('countries', ['id' => $pays->id]);
    }
}
```

- [ ] **Étape 2 : lancer, constater l'échec**

Run : `php artisan test --filter CountryCenterTest`
Attendu : ÉCHEC — `Class "App\Livewire\Admin\OrderEngine\CountryCenter" not found`.

- [ ] **Étape 3 : écrire le composant**

```php
<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Models\Country;
use App\Services\Catalog\GeoGuard;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Le premier niveau du catalogue : les pays.
 *
 * LE PAYS N'ORGANISE QUE LES ZONES. Il ne porte aucun métier : un pays « a » un métier si au moins
 * une de ses zones l'a. C'est un calcul, jamais un réglage — donc rien à tenir à jour et rien qui
 * puisse se contredire avec la vérité du terrain.
 */
#[Layout('layouts.app')]
class CountryCenter extends Component
{
    /** Le refus vaut au niveau du composant, pas seulement de la route. */
    use EnforcesAdminAccess;
    use WithPagination;

    public ?int $editionId = null;

    /** @var array<string, mixed> */
    public array $formulaire = [];

    public ?string $flash = null;

    public ?string $blocage = null;

    public function mount(): void
    {
        $this->reinitialiserFormulaire();
    }

    #[Computed]
    public function pays()
    {
        // `withCount` plutôt qu'une relation chargée : la liste n'a besoin que du nombre, et
        // charger les zones de chaque pays pour les compter ferait N+1 requêtes pour rien.
        return Country::query()
            ->withCount('serviceZones')
            ->orderBy('name')
            ->paginate(20);
    }

    public function nouveau(): void
    {
        $this->editionId = null;
        $this->reinitialiserFormulaire();
    }

    public function editer(int $id): void
    {
        $pays = Country::findOrFail($id);
        $this->editionId = $id;
        $this->formulaire = $pays->only([
            'iso_code', 'name', 'currency_code', 'default_locale', 'timezone', 'phone_code',
        ]);
    }

    public function enregistrer(): void
    {
        $unicite = 'unique:countries,iso_code' . ($this->editionId ? ',' . $this->editionId : '');

        $valide = $this->validate([
            'formulaire.iso_code' => ['required', 'string', 'size:2', $unicite],
            'formulaire.name' => ['required', 'string', 'max:120'],
            'formulaire.currency_code' => ['required', 'string', 'size:3'],
            'formulaire.default_locale' => ['nullable', 'string', 'max:10'],
            'formulaire.timezone' => ['nullable', 'string', 'max:64'],
            'formulaire.phone_code' => ['nullable', 'string', 'max:8'],
        ])['formulaire'];

        $valide['iso_code'] = strtoupper($valide['iso_code']);

        if ($this->editionId) {
            Country::findOrFail($this->editionId)->update($valide);
            $this->flash = 'Pays mis à jour.';
        } else {
            // Un pays neuf n'ouvre pas les réservations tout seul : on ne veut pas qu'une faute de
            // frappe rende un marché commandable.
            Country::create($valide + ['is_active' => false, 'booking_enabled' => false]);
            $this->flash = 'Pays ajouté. Il reste inactif tant que vous ne l’activez pas.';
        }

        $this->nouveau();
        unset($this->pays);
    }

    public function basculerActivation(int $id): void
    {
        $pays = Country::findOrFail($id);

        /*
         * On ne touche QUE le pays.
         *
         * Propager l'extinction aux zones ferait perdre l'information de celles qui étaient déjà
         * éteintes pour leur propre raison : la réactivation les rallumerait toutes. La
         * joignabilité se lit (`GeoGuard::zoneEstJoignable`), elle ne s'écrit pas.
         */
        $pays->update(['is_active' => ! $pays->is_active]);

        $this->flash = $pays->is_active
            ? "{$pays->name} est actif."
            : "{$pays->name} est désactivé — ses zones ne sont plus joignables, mais leur réglage propre est conservé.";

        unset($this->pays);
    }

    public function supprimer(int $id, GeoGuard $guard): void
    {
        $pays = Country::findOrFail($id);
        $raisons = $guard->raisonsDeNePasSupprimerPays($pays);

        if ($raisons !== []) {
            // On dit ce qui bloque, avec le compte. L'administrateur n'a pas accès à la base pour
            // le découvrir autrement.
            $this->blocage = 'Suppression impossible : ' . implode(', ', $raisons)
                . '. Désactivez le pays si vous voulez le fermer sans rien perdre.';

            return;
        }

        $pays->delete();
        $this->blocage = null;
        $this->flash = 'Pays supprimé.';
        unset($this->pays);
    }

    private function reinitialiserFormulaire(): void
    {
        $this->formulaire = [
            'iso_code' => '',
            'name' => '',
            'currency_code' => 'EUR',
            'default_locale' => '',
            'timezone' => '',
            'phone_code' => '',
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.order-engine.country-center');
    }
}
```

- [ ] **Étape 4 : écrire la vue**

`resources/views/livewire/admin/order-engine/country-center.blade.php` — reprendre la structure de
`catalog-center.blade.php` (mêmes classes, même mise en page) avec :

- un tableau des pays : drapeau/code ISO, nom, devise, **nombre de zones**, état ;
- par ligne : « Gérer les zones » (lien vers le niveau 2), « Modifier », « Activer/Désactiver »,
  « Supprimer » ;
- le formulaire d'ajout/édition ;
- l'affichage de `$flash` et de `$blocage`, ce dernier bien visible.

Le lien du niveau 2 : `<a href="{{ route('admin.order-engine.zones', $p) }}">`.

- [ ] **Étape 5 : relancer** — Attendu : les 6 tests passent.

- [ ] **Étape 6 : Pint, PHPStan, commit**

```bash
vendor/bin/pint app/Livewire/Admin/OrderEngine/CountryCenter.php
vendor/bin/phpstan analyse app/Livewire/Admin/OrderEngine/CountryCenter.php
git add app/Livewire/Admin/OrderEngine/CountryCenter.php resources/views/livewire/admin/order-engine/country-center.blade.php tests/Feature/OrderEngine/CountryCenterTest.php
git commit -m "feat(admin): les pays, premier niveau du catalogue"
```

---

### Tâche 4 : les routes de la descente

**Fichiers :** Modifier `routes/admin.php`

**Interfaces produites :** `admin.order-engine.catalog` (pays), `admin.order-engine.zones` (zones
d'un pays), `admin.order-engine.catalog.zone` (catalogue d'une zone)

- [ ] **Étape 1 : écrire le test qui échoue**

Ajouter dans `tests/Feature/OrderEngine/CountryCenterTest.php` :

```php
    public function test_la_descente_a_trois_niveaux_repond(): void
    {
        $pays = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $pays->id]);

        $this->get(route('admin.order-engine.catalog'))->assertOk();
        $this->get(route('admin.order-engine.zones', $pays))->assertOk();
        $this->get(route('admin.order-engine.catalog.zone', [$pays, $zone]))->assertOk();
    }
```

- [ ] **Étape 2 : lancer, constater l'échec** — Attendu : `Route [admin.order-engine.zones] not defined`.

- [ ] **Étape 3 : remplacer le bloc de routes**

Dans `routes/admin.php`, remplacer :

```php
        Route::get('/catalogue', CatalogCenter::class)
            ->name('order-engine.catalog');
```

par :

```php
        /*
         * La descente : Pays → Zones → Secteurs & métiers.
         *
         * L'ancien écran n'est pas remplacé, il DESCEND d'un cran. Les liens existants vers
         * `/admin/catalogue` arrivent désormais sur la liste des pays — c'est voulu : un métier
         * s'active par zone, il n'y a donc pas de catalogue « en général » à afficher.
         */
        Route::get('/catalogue', CountryCenter::class)
            ->name('order-engine.catalog');

        Route::get('/catalogue/{country}', ZoneCenter::class)
            ->name('order-engine.zones');

        Route::get('/catalogue/{country}/{zone}', CatalogCenter::class)
            ->name('order-engine.catalog.zone');
```

et ajouter en tête du fichier, près des autres `use` :

```php
use App\Livewire\Admin\OrderEngine\CountryCenter;
use App\Livewire\Admin\OrderEngine\ZoneCenter;
```

- [ ] **Étape 4 : relancer**

Attendu : les deux premières assertions passent, la troisième échoue tant que `ZoneCenter`
n'existe pas et que `CatalogCenter::mount()` n'accepte pas de paramètres. C'est l'ordre normal :
les tâches 5 et 6 la referment.

- [ ] **Étape 5 : commit**

```bash
git add routes/admin.php tests/Feature/OrderEngine/CountryCenterTest.php
git commit -m "feat(admin): les trois routes de la descente géographique"
```

---

### Tâche 5 : niveau 2 — les zones d'un pays

**Fichiers :** Créer `app/Livewire/Admin/OrderEngine/ZoneCenter.php`,
`resources/views/livewire/admin/order-engine/zone-center.blade.php` ·
Test `tests/Feature/OrderEngine/ZoneCenterTest.php`

**Interfaces consommées :** `GeoGuard::raisonsDeNePasSupprimerZone()`, les traits de `GestionZones`
**Interfaces produites :** `ZoneCenter::mount(Country $country)`, `supprimerZone(int $id)`

**Pourquoi réutiliser les traits de `GestionZones` :** ce composant fait déjà le CRUD des zones avec
filtres, réservabilité et visibilité. Écrire un second écran de zones garantirait qu'ils divergent —
l'un recevrait un filtre, l'autre un réglage, et personne ne saurait plus lequel fait foi.

- [ ] **Étape 1 : écrire les tests qui échouent**

```php
<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\ZoneCenter;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le deuxième niveau : les zones d'UN pays.
 *
 * Le test le plus important est celui du cloisonnement : un écran qui laisserait fuir les zones
 * d'un autre pays n'aurait l'air de rien tant qu'il n'y a qu'un seul pays en base — c'est-à-dire
 * exactement aujourd'hui.
 */
class ZoneCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_il_ne_montre_que_les_zones_du_pays(): void
    {
        $belgique = Country::factory()->create(['name' => 'Belgique']);
        $france = Country::factory()->create(['name' => 'France']);
        ServiceZone::factory()->create(['country_id' => $belgique->id, 'name' => 'Bruxelles']);
        ServiceZone::factory()->create(['country_id' => $france->id, 'name' => 'Paris']);

        Livewire::test(ZoneCenter::class, ['country' => $belgique])
            ->assertOk()
            ->assertSee('Bruxelles')
            ->assertDontSee('Paris');
    }

    public function test_il_cree_la_zone_dans_le_bon_pays(): void
    {
        $pays = Country::factory()->create();

        Livewire::test(ZoneCenter::class, ['country' => $pays])
            ->set('zoneForm.name', 'Anvers')
            ->set('zoneForm.slug', 'anvers')
            ->call('saveZone')
            ->assertHasNoErrors();

        // Le pays n'est PAS un champ du formulaire : il vient du contexte. Le laisser saisissable
        // permettrait de créer, depuis l'écran Belgique, une zone française.
        $this->assertDatabaseHas('service_zones', ['name' => 'Anvers', 'country_id' => $pays->id]);
    }

    public function test_desactiver_une_zone_ne_touche_pas_au_pays(): void
    {
        $pays = Country::factory()->create(['is_active' => true]);
        $zone = ServiceZone::factory()->create(['country_id' => $pays->id, 'is_bookable' => true]);

        Livewire::test(ZoneCenter::class, ['country' => $pays])
            ->call('selectZone', $zone->id)
            ->call('toggleZoneBookability');

        $this->assertFalse($zone->fresh()->is_bookable);
        $this->assertTrue($pays->fresh()->is_active);
    }

    public function test_il_refuse_de_supprimer_une_zone_qui_porte_des_reservations(): void
    {
        $pays = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $pays->id]);
        \App\Models\Booking::factory()->create(['service_zone_id' => $zone->id]);

        Livewire::test(ZoneCenter::class, ['country' => $pays])
            ->call('supprimerZone', $zone->id)
            ->assertSee('réservation(s) rattachée(s)');

        $this->assertDatabaseHas('service_zones', ['id' => $zone->id]);
    }
}
```

- [ ] **Étape 2 : lancer, constater l'échec** — Attendu : classe introuvable.

- [ ] **Étape 3 : écrire le composant**

```php
<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Models\Country;
use App\Models\ServiceZone;
use App\Services\Catalog\GeoGuard;
use App\Support\Livewire\Concerns\Admin\ManagesZonesData;
use App\Support\Livewire\Concerns\Admin\PerformsZoneManagementActions;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Le deuxième niveau : les zones d'un pays.
 *
 * IL RÉUTILISE LES TRAITS DE `GestionZones` et n'en réécrit aucun. Cet écran-là fait déjà le CRUD
 * des zones avec ses filtres, sa réservabilité et sa visibilité ; un second écran de zones
 * divergerait, et plus personne ne saurait lequel fait foi.
 *
 * LE PAYS VIENT DU CONTEXTE, jamais du formulaire. Le laisser saisissable permettrait de créer,
 * depuis l'écran Belgique, une zone française — une erreur qui ne se verrait qu'en cherchant une
 * zone disparue.
 */
#[Layout('layouts.app')]
class ZoneCenter extends Component
{
    use EnforcesAdminAccess;
    use ManagesZonesData;
    use PerformsZoneManagementActions;
    use WithPagination;

    public Country $country;

    public ?string $blocage = null;

    public function mount(Country $country): void
    {
        $this->country = $country;
    }

    /**
     * Le cloisonnement, en un seul endroit.
     *
     * `ManagesZonesData::zoneBaseQuery()` sert les deux écrans ; on la restreint ici plutôt que de
     * filtrer à l'affichage — un filtre de vue laisse passer les actions.
     */
    protected function zoneBaseQuery()
    {
        return ServiceZone::query()->where('country_id', $this->country->id);
    }

    public function supprimerZone(int $id, GeoGuard $guard): void
    {
        $zone = ServiceZone::query()
            ->where('country_id', $this->country->id)
            ->findOrFail($id);

        $raisons = $guard->raisonsDeNePasSupprimerZone($zone);

        if ($raisons !== []) {
            $this->blocage = 'Suppression impossible : ' . implode(', ', $raisons)
                . '. Désactivez la zone si vous voulez la fermer sans rien perdre.';

            return;
        }

        $zone->delete();
        $this->blocage = null;
    }

    public function render(): View
    {
        $zones = $this->applyZoneFilters($this->zoneBaseQuery())
            ->orderBy('priority')
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.admin.order-engine.zone-center', [
            'zones' => $zones,
            'selectedZone' => $this->selectedZone,
        ]);
    }
}
```

- [ ] **Étape 4 : forcer le pays à la création**

Lire `app/Support/Livewire/Concerns/Admin/PerformsZoneManagementActions.php`, méthode `saveZone()`.
Si elle prend `country_id` depuis `$this->zoneForm`, ajouter dans `ZoneCenter` une surcharge qui
impose le contexte **avant** l'appel parent :

```php
    public function saveZone(): void
    {
        // Le contexte gagne toujours sur le formulaire.
        $this->zoneForm['country_id'] = $this->country->id;

        parent::saveZone();
    }
```

(Un trait ne se surcharge pas avec `parent::` — utiliser l'aliasing `use PerformsZoneManagementActions { saveZone as protected saveZoneBase; }`
et appeler `$this->saveZoneBase()`, comme `GestionZones` le fait déjà pour `selectZone`.)

- [ ] **Étape 5 : écrire la vue**

`zone-center.blade.php` — reprendre `gestion-zones.blade.php` en retirant le filtre pays et en
ajoutant :

- un fil d'Ariane `Catalogue › {{ $country->name }}` ;
- par zone, un lien « Ouvrir le catalogue »
  `route('admin.order-engine.catalog.zone', [$country, $zone])` ;
- l'affichage de `$blocage`.

- [ ] **Étape 6 : relancer** — Attendu : les 4 tests passent.

- [ ] **Étape 7 : Pint, PHPStan, commit**

```bash
vendor/bin/pint app/Livewire/Admin/OrderEngine/ZoneCenter.php
vendor/bin/phpstan analyse app/Livewire/Admin/OrderEngine/ZoneCenter.php
git add app/Livewire/Admin/OrderEngine/ZoneCenter.php resources/views/livewire/admin/order-engine/zone-center.blade.php tests/Feature/OrderEngine/ZoneCenterTest.php
git commit -m "feat(admin): les zones d'un pays, cloisonnées par la requête"
```

---

### Tâche 6 : niveau 3 — le catalogue d'une zone

**Fichiers :** Modifier `app/Livewire/Admin/OrderEngine/CatalogCenter.php`,
`resources/views/livewire/admin/order-engine/catalog-center.blade.php`,
`tests/Feature/OrderEngine/CatalogCenterTest.php` ·
Test `tests/Feature/OrderEngine/CatalogZoneScopeTest.php`

**Interfaces consommées :** `TradeZonePricing`
**Interfaces produites :** `CatalogCenter::mount(Country $country, ServiceZone $zone)`,
`basculerMetierDansLaZone(int $tradeId)`

- [ ] **Étape 1 : écrire les tests qui échouent**

```php
<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le catalogue vu depuis UNE zone.
 *
 * L'activation d'un métier et son prix sont la MÊME chose : une ligne `(métier, zone)` dans
 * `trade_zone_pricing`. C'est ce qui rend possible qu'un même métier coûte plus cher dans une zone
 * très demandée — il n'y a pas deux réglages à garder cohérents.
 */
class CatalogZoneScopeTest extends TestCase
{
    use RefreshDatabase;

    private Country $pays;

    private ServiceZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->pays = Country::factory()->create();
        $this->zone = ServiceZone::factory()->create(['country_id' => $this->pays->id, 'name' => 'Bruxelles']);
    }

    public function test_il_annonce_la_zone_qu_il_montre(): void
    {
        Livewire::test(CatalogCenter::class, ['country' => $this->pays, 'zone' => $this->zone])
            ->assertOk()
            ->assertSee('Bruxelles');
    }

    public function test_activer_un_metier_cree_la_ligne_du_couple(): void
    {
        $metier = Trade::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, ['country' => $this->pays, 'zone' => $this->zone])
            ->call('basculerMetierDansLaZone', $metier->id);

        $this->assertDatabaseHas('trade_zone_pricing', [
            'trade_id' => $metier->id,
            'service_zone_id' => $this->zone->id,
            'is_active' => true,
        ]);
    }

    public function test_desactiver_conserve_la_grille(): void
    {
        $metier = Trade::query()->firstOrFail();
        TradeZonePricing::create([
            'trade_id' => $metier->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 4500,
            'is_active' => true,
        ]);

        Livewire::test(CatalogCenter::class, ['country' => $this->pays, 'zone' => $this->zone])
            ->call('basculerMetierDansLaZone', $metier->id);

        $ligne = TradeZonePricing::where('trade_id', $metier->id)
            ->where('service_zone_id', $this->zone->id)
            ->firstOrFail();

        // Éteindre n'efface pas : rallumer doit retrouver le tarif saisi, pas repartir de zéro.
        $this->assertFalse((bool) $ligne->is_active);
        $this->assertSame(4500, (int) $ligne->base_rate_cents);
    }

    public function test_l_activation_est_propre_a_la_zone(): void
    {
        $metier = Trade::query()->firstOrFail();
        $autre = ServiceZone::factory()->create(['country_id' => $this->pays->id]);

        Livewire::test(CatalogCenter::class, ['country' => $this->pays, 'zone' => $this->zone])
            ->call('basculerMetierDansLaZone', $metier->id);

        // Toute la raison d'être du chantier : Bruxelles et Liège ne partagent rien.
        $this->assertDatabaseMissing('trade_zone_pricing', [
            'trade_id' => $metier->id,
            'service_zone_id' => $autre->id,
        ]);
    }

    public function test_il_ecrit_dans_la_table_qui_fait_foi(): void
    {
        $metier = Trade::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, ['country' => $this->pays, 'zone' => $this->zone])
            ->call('basculerMetierDansLaZone', $metier->id);

        // `trade_zone_settings` est le doublon condamné. Y écrire recréerait deux sources de
        // vérité pour le même couple, et un prix que personne ne saurait expliquer.
        $this->assertDatabaseCount('trade_zone_settings', 0);
    }
}
```

- [ ] **Étape 2 : lancer, constater l'échec** — Attendu : `mount()` n'accepte pas ces paramètres.

- [ ] **Étape 3 : modifier le composant**

Dans `app/Livewire/Admin/OrderEngine/CatalogCenter.php`, remplacer `mount()` :

```php
    public Country $country;

    public ServiceZone $zone;

    public function mount(Country $country, ServiceZone $zone): void
    {
        /*
         * Le pays est dans la signature bien qu'on ne s'en serve que pour le fil d'Ariane : il
         * rend l'URL lisible et vérifiable. Sans lui, `/admin/catalogue/12` ne dirait pas si 12
         * est un pays ou une zone.
         */
        abort_unless($zone->country_id === $country->id, 404);

        $this->country = $country;
        $this->zone = $zone;
        $this->resetSectorForm();
    }
```

Ajouter la bascule :

```php
    /**
     * Ouvrir ou fermer un métier DANS CETTE ZONE.
     *
     * L'activation et le prix sont la même ligne : `trade_zone_pricing` porte les deux. On ne
     * supprime jamais la ligne en éteignant — rallumer doit retrouver le tarif saisi plutôt que
     * de repartir de zéro.
     */
    public function basculerMetierDansLaZone(int $tradeId): void
    {
        $ligne = TradeZonePricing::query()->firstOrNew([
            'trade_id' => $tradeId,
            'service_zone_id' => $this->zone->id,
        ]);

        if (! $ligne->exists) {
            // Première ouverture : on part du prix du métier, faute de mieux. L'admin l'ajustera
            // au lot suivant, quand les champs de grille seront éditables.
            $ligne->base_rate_cents = (int) (Trade::find($tradeId)?->base_price_cents ?? 0);
            $ligne->surge_multiplier = 1.0;
            $ligne->is_active = true;
        } else {
            $ligne->is_active = ! $ligne->is_active;
        }

        $ligne->save();

        unset($this->tradeStatuses);
    }

    /** @return array<int, bool> métier → actif dans cette zone */
    #[Computed]
    public function metiersActifsDansLaZone(): array
    {
        return TradeZonePricing::query()
            ->where('service_zone_id', $this->zone->id)
            ->pluck('is_active', 'trade_id')
            ->map(fn ($v) => (bool) $v)
            ->all();
    }
```

Ajouter les `use` : `App\Models\Country`, `App\Models\ServiceZone`, `App\Models\TradeZonePricing`.

- [ ] **Étape 4 : adapter les tests existants**

`tests/Feature/OrderEngine/CatalogCenterTest.php` monte `CatalogCenter::class` sans paramètres.
Ajouter dans son `setUp()` :

```php
        $this->pays = Country::factory()->create();
        $this->zone = ServiceZone::factory()->create(['country_id' => $this->pays->id]);
```

et remplacer chaque `Livewire::test(CatalogCenter::class)` par
`Livewire::test(CatalogCenter::class, ['country' => $this->pays, 'zone' => $this->zone])`.

- [ ] **Étape 5 : mettre à jour la vue**

Dans `catalog-center.blade.php` :

- fil d'Ariane `Catalogue › {{ $country->name }} › {{ $zone->name }}`, chaque niveau cliquable ;
- par métier, un interrupteur appelant `basculerMetierDansLaZone({{ $trade->id }})`, dont l'état
  vient de `$this->metiersActifsDansLaZone`.

- [ ] **Étape 6 : relancer les DEUX fichiers de test**

```bash
php artisan test --filter "CatalogZoneScopeTest|CatalogCenterTest"
```

- [ ] **Étape 7 : Pint, PHPStan, commit**

```bash
vendor/bin/pint app/Livewire/Admin/OrderEngine/CatalogCenter.php
vendor/bin/phpstan analyse app/Livewire/Admin/OrderEngine/CatalogCenter.php
git add -A app/Livewire resources/views/livewire/admin/order-engine tests/Feature/OrderEngine
git commit -m "feat(admin): le catalogue devient celui d'une zone"
```

---

### Tâche 7 : dire dans l'interface ce qui n'est pas branché

**Fichiers :** Modifier `resources/views/livewire/admin/order-engine/catalog-center.blade.php` ·
Test `tests/Feature/OrderEngine/CatalogZoneScopeTest.php` (compléter)

**Pourquoi cette tâche existe :** c'est le mode d'échec le plus probable de tout le chantier. Livrer
un bel écran d'activation par zone, et croire la fonctionnalité acquise. Elle ne l'est qu'au lot 3 :
le moteur ne lit pas encore `trade_zone_pricing`, et le brouillon de commande ne résout pas de
zone. Un avertissement dans un document ne sera pas lu ; dans l'écran, si.

- [ ] **Étape 1 : écrire le test qui échoue**

```php
    public function test_il_avertit_que_l_activation_n_est_pas_encore_branchee(): void
    {
        Livewire::test(CatalogCenter::class, ['country' => $this->pays, 'zone' => $this->zone])
            ->assertSee('n’a pas encore d’effet sur ce que voit un client');
    }
```

- [ ] **Étape 2 : lancer, constater l'échec.**

- [ ] **Étape 3 : ajouter le bandeau**

En haut de la liste des métiers :

```blade
{{--
    Ce bandeau disparaîtra au lot 3, quand le brouillon de commande résoudra une zone et que le
    moteur lira `trade_zone_pricing`. Tant qu'il est là, il dit la vérité : l'écran est exact, il
    n'est pas branché.
--}}
<div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
    <strong>Réglage préparatoire.</strong>
    L’activation d’un métier dans cette zone est enregistrée, mais elle
    <strong>n’a pas encore d’effet sur ce que voit un client</strong> : le parcours de commande ne
    détermine pas encore la zone d’une adresse. Ce branchement est prévu et suivi séparément.
</div>
```

- [ ] **Étape 4 : relancer, commit**

```bash
git add resources/views/livewire/admin/order-engine/catalog-center.blade.php tests/Feature/OrderEngine/CatalogZoneScopeTest.php
git commit -m "feat(admin): l'écran dit lui-même ce qui n'est pas encore branché"
```

---

### Tâche 8 : portail

- [ ] `php artisan test --filter "OrderEngine"` — vert
- [ ] `php artisan test` — la suite complète, vert. **Chercher en particulier les tests qui
      appelaient `route('admin.order-engine.catalog')` en attendant les secteurs** : ils arrivent
      désormais sur les pays.
- [ ] `vendor/bin/pint --test`
- [ ] `vendor/bin/phpstan analyse`
- [ ] Vérification à l'œil : `/admin/catalogue` → Belgique → une zone → le catalogue. Vérifier que
      le fil d'Ariane remonte, que le bandeau d'avertissement est visible, et qu'un métier activé
      dans une zone ne l'est pas dans l'autre.
- [ ] Commit du portail.

---

## Auto-revue du plan

**Couverture de la spec.** Les six points du lot 1 sont couverts : CRUD pays (tâche 3), clic vers
les zones (tâche 4), CRUD zones sans effet sur le pays (tâche 5), clic vers le catalogue de la zone
(tâches 4 et 6), activation par zone dans `trade_zone_pricing` (tâche 6). Les règles « supprimer
n'est pas désactiver » et « éteindre un pays est une lecture » sont dans la tâche 2, testées avant
toute interface. L'honnêteté de l'écran, exigée par la spec, est la tâche 7.

**Cohérence des types.** `GeoGuard` rend des `array` de chaînes partout ; `zoneEstJoignable` rend
un `bool`. `mount(Country $country, ServiceZone $zone)` est la même signature en tâche 4, 6 et dans
les tests.

**Faits vérifiés en écrivant ce plan**, pour que personne n'ait à les redécouvrir :
`trades.base_price_cents` existe ; les fabriques `CountryFactory`, `ServiceZoneFactory`,
`BookingFactory` et `TradeFactory` existent ; le trait d'accès est bien
`App\Support\Livewire\Concerns\EnforcesAdminAccess`.

**Un seul point que l'exécutant doit vérifier plutôt que supposer**, marqué comme tel : la façon
dont `PerformsZoneManagementActions::saveZone()` reçoit `country_id` (tâche 5, étape 4). C'est le
seul endroit où le plan touche du code existant qu'il n'a pas lu en entier.

**Hors lot, rappelé ici pour mémoire :** le câblage tarifaire (lot 2, avec sa migration de sécurité
qui doit semer 15 × 6 lignes sans faire bouger un prix) et la résolution code postal → zone
(lot 3). Sans le lot 3, ce que produit ce plan reste un réglage préparatoire.
