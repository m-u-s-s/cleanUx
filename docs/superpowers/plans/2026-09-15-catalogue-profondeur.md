# Catalogue « Profondeur » — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ouvrir « Intervention immédiate » et « Prendre rendez-vous » sur un écran natif de catalogue (ligne de sonde, feuille du métier) qui ne propose que ce que le moteur de commande accepte.

**Architecture:** Une classe serveur `CatalogueServable` porte seule le filtre « servable » ; `OrderJourney` (web) et une nouvelle route `GET /api/client/catalogue` s'en servent. L'app cliente lit cette route (react-query), construit un modèle pur de la sonde, et rend quatre composants ; « Commander » ouvre la vue web existante sur `/commander/{secteur}/{métier}?mode=`.

**Tech Stack:** Laravel 12 / PHP 8.5 / Livewire 3 / PHPUnit 11 (SQLite mémoire) ; Expo SDK 57, React Native 0.86.2, react-query, react-navigation native-stack, jest-expo + @testing-library/react-native.

**Spec:** `docs/superpowers/specs/2026-09-15-catalogue-profondeur-design.md`

## Global Constraints

- Filtre = moteur de commande : `Sector::active()->ordered()`, métiers `is_active` + `Trade::servableEnMode($mode, $zoneId)` ordonnés par `sort_order` ; un secteur sans métier servable n'est pas renvoyé.
- Zone = `ClientPlaceService::parDefaut($client)?->service_zone_id`, sinon `null`.
- Prix plancher : ligne `trade_zone_pricing` active avec `base_rate_cents > 0`, sinon `trades.base_price_cents > 0`, sinon `null`. Hors taxe. `service_catalogs` n'est jamais lu.
- Devise : `ServiceZone::deviseDeLaZone()`, sinon `config('fx.base_currency', 'EUR')`.
- Route : `GET /api/client/catalogue?mode=asap|scheduled`, groupe `auth:sanctum` + `verified` + préfixe `client` de `routes/api/client.php` (celui de `budget`/`protection`/`order-intent`) ; `bundle` → 422.
- Mobile : aucune couleur ni espacement en dur — `useThemeColors`, `spacing`, `radius`, `typography` ; nuit et jour ; `useReducedMotion` respecté ; aucun texte français en dur (clés i18n dans les SIX catalogues fr, nl, en, es, it, de, jetons `:x` identiques).
- « Plusieurs services » (`bundle`), les indicateurs et les raccourcis de `HomeActionsSheet` ne changent pas.
- Un test de refus a toujours son témoin positif.
- PHP en Git Bash : `/c/laragon/bin/php/php-8.5.5/php.exe` (noté `$PHP` ci-dessous).
- Jest : depuis `mobile/client`, `npx jest <chemin>`.
- La copie de travail contient du travail en cours de l'utilisateur : ne JAMAIS `git add -A` ni `git add .` ; n'ajouter que les fichiers listés dans la tâche ; ne commiter que si l'utilisateur a demandé des commits pour l'exécution.
- Ne jamais éditer un fichier pendant qu'une suite tourne.

## Carte des fichiers

| Fichier | Rôle |
|---|---|
| `app/Services/OrderEngine/CatalogueServable.php` (créer) | Seule définition du filtre, de la zone du client, du prix plancher et de la devise |
| `app/Livewire/OrderEngine/OrderJourney.php` (modifier `sectors()`, `trades()`) | Le web passe par `CatalogueServable` |
| `app/Http/Controllers/Api/Client/CatalogueController.php` (créer) | Valide `mode`, assemble la réponse JSON sans N+1 |
| `routes/api/client.php` (modifier) | Déclare la route |
| `tests/Feature/OrderEngine/CatalogueServableTest.php` (créer) | Filtre, zone, prix, devise |
| `tests/Feature/Api/Client/CatalogueApiTest.php` (créer) | Contrat HTTP, parité avec le web, budget de requêtes |
| `mobile/client/src/catalogue/types.ts` (créer) | Types de la réponse |
| `mobile/client/src/catalogue/useCatalogue.ts` (créer) | Hook react-query |
| `mobile/client/src/catalogue/iconeDuMetier.ts` (créer) | Noms serveur → Ionicons |
| `mobile/client/src/catalogue/modeleDeSonde.ts` (créer) | Modèle pur : repères, côtés, étiquettes, rang, index depuis le défilement |
| `mobile/client/src/catalogue/index.ts` (créer) | Barrique du dossier |
| `mobile/client/src/screens/catalogue/RepereDeSonde.tsx` (créer) | Un repère (nœud, fil, case, étiquette) |
| `mobile/client/src/screens/catalogue/FeuilleDuMetier.tsx` (créer) | Panneau fixe du métier choisi |
| `mobile/client/src/screens/catalogue/LigneDeSonde.tsx` (créer) | Colonne aimantée |
| `mobile/client/src/screens/catalogue/CatalogueScreen.tsx` (créer) | Écran : états et composition |
| `mobile/client/src/navigation/types.ts`, `RootNavigator.tsx` (modifier) | Route `Catalogue` |
| `mobile/client/src/screens/components/HomeActionsSheet.tsx` (modifier) | `asap` et `scheduled` → `Catalogue` |
| `mobile/shared/src/i18n/catalogues/{fr,nl,en,es,it,de}.ts` (modifier) | Clés `catalogue.*` |
| `mobile/client/__tests__/catalogue/*.test.ts(x)` (créer) | Tests mobiles |
| `mobile/client/__tests__/screens/HomeScreen.interaction.test.tsx` (modifier) | Nouvelles destinations |

---

### Task 1: `CatalogueServable` — la seule définition de ce qui est commandable

**Files:**
- Create: `app/Services/OrderEngine/CatalogueServable.php`
- Test: `tests/Feature/OrderEngine/CatalogueServableTest.php`

**Interfaces:**
- Consumes: `Sector::scopeActive/scopeOrdered`, `Sector::trades()`, `Trade::scopeServableEnMode(?string $mode, ?int $zoneId)`, `ClientPlaceService::parDefaut(User): ?ClientPlace`, `ServiceZone::deviseDeLaZone(): string`.
- Produces:
  - `secteurs(?string $mode, ?int $zoneId): Builder` (Sector)
  - `metiers(int $sectorId, ?string $mode, ?int $zoneId): Builder` (Trade, ordonné `sort_order`)
  - `contraindreLesMetiers(Builder $query, ?string $mode, ?int $zoneId): Builder`
  - `zoneDuClient(User $client): ?int`
  - `prixPlancherCents(Trade $trade, ?TradeZonePricing $ligneDeZone): ?int` — reçoit la ligne déjà chargée (et non un id de zone, comme l'écrivait la spec) pour que l'API n'émette pas une requête par métier.
  - `devise(?int $zoneId): string`

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\OrderEngine;

use App\Models\ClientPlace;
use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\OrderEngine\CatalogueServable;
use App\Support\Domain\OrderMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** CE QUE LE MOTEUR ACCEPTE, DIT UNE SEULE FOIS — pour le web ET pour l'application. */
class CatalogueServableTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $zone;

    private Sector $urgent;

    private Sector $lent;

    private Trade $plomberie;

    private Trade $sanitaire;

    private Trade $ravalement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::create([
            'name' => 'Zone sonde', 'slug' => 'zone-sonde', 'code' => 'ZSD',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->urgent = Sector::create(['name' => 'Dépannage', 'slug' => 'depannage-sonde', 'is_active' => true, 'sort_order' => 1]);
        $this->lent = Sector::create(['name' => 'Gros œuvre', 'slug' => 'gros-oeuvre-sonde', 'is_active' => true, 'sort_order' => 2]);

        $this->plomberie = Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => 'plomberie-sonde', 'code' => 'PLB-SD', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_scheduled' => true, 'allows_asap' => true, 'allows_bundle' => true,
            'base_price_cents' => 8500,
        ]);
        $this->sanitaire = Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => 'sanitaire-sonde', 'code' => 'SAN-SD', 'name' => 'Sanitaire',
            'is_active' => true, 'sort_order' => 2, 'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
        ]);
        $this->ravalement = Trade::create([
            'sector_id' => $this->lent->id, 'slug' => 'ravalement-sonde', 'code' => 'RAV-SD', 'name' => 'Ravalement',
            'is_active' => true, 'sort_order' => 1, 'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
        ]);
    }

    private function catalogue(): CatalogueServable
    {
        return app(CatalogueServable::class);
    }

    #[Test]
    public function en_rendez_vous_tous_les_secteurs_actifs_sont_servables(): void
    {
        $this->assertSame(
            [$this->urgent->id, $this->lent->id],
            $this->catalogue()->secteurs(OrderMode::SCHEDULED, null)->pluck('id')->all(),
        );
    }

    #[Test]
    public function en_immediat_un_secteur_sans_metier_immediat_disparait(): void
    {
        // Témoin : le même appel en rendez-vous garde les deux secteurs (test précédent).
        $this->assertSame([$this->urgent->id], $this->catalogue()->secteurs(OrderMode::ASAP, null)->pluck('id')->all());
    }

    #[Test]
    public function les_metiers_suivent_le_mode_et_leur_ordre(): void
    {
        $this->assertSame(
            [$this->plomberie->id, $this->sanitaire->id],
            $this->catalogue()->metiers($this->urgent->id, OrderMode::SCHEDULED, null)->pluck('id')->all(),
        );
        $this->assertSame(
            [$this->plomberie->id],
            $this->catalogue()->metiers($this->urgent->id, OrderMode::ASAP, null)->pluck('id')->all(),
        );
    }

    #[Test]
    public function une_zone_fermee_a_l_immediat_retire_le_metier(): void
    {
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => false,
        ]);

        $this->assertSame([], $this->catalogue()->secteurs(OrderMode::ASAP, $this->zone->id)->pluck('id')->all());
    }

    #[Test]
    public function temoin_la_meme_zone_ouverte_garde_le_metier(): void
    {
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);

        $this->assertSame([$this->urgent->id], $this->catalogue()->secteurs(OrderMode::ASAP, $this->zone->id)->pluck('id')->all());
    }

    #[Test]
    public function la_zone_du_client_vient_de_son_lieu_par_defaut(): void
    {
        $client = User::factory()->client()->create();
        ClientPlace::factory()->create(['user_id' => $client->id, 'service_zone_id' => $this->zone->id, 'is_default' => false]);

        // Refus : un lieu qui n'est pas celui par défaut ne compte pas.
        $this->assertNull($this->catalogue()->zoneDuClient($client));

        // Témoin : le lieu par défaut donne la zone.
        ClientPlace::factory()->parDefaut()->create(['user_id' => $client->id, 'service_zone_id' => $this->zone->id]);
        $this->assertSame($this->zone->id, $this->catalogue()->zoneDuClient($client->fresh()));
    }

    #[Test]
    public function le_tarif_de_zone_passe_avant_le_prix_du_metier(): void
    {
        $ligne = TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);

        $this->assertSame(9900, $this->catalogue()->prixPlancherCents($this->plomberie, $ligne));

        // Une ligne inactive ne compte pas : repli sur le prix du métier.
        $ligne->update(['is_active' => false]);
        $this->assertSame(8500, $this->catalogue()->prixPlancherCents($this->plomberie, $ligne->fresh()));

        // Sans ligne non plus.
        $this->assertSame(8500, $this->catalogue()->prixPlancherCents($this->plomberie, null));
    }

    #[Test]
    public function sans_aucun_prix_positif_le_plancher_est_inconnu(): void
    {
        $this->assertNull($this->catalogue()->prixPlancherCents($this->sanitaire, null));

        $this->sanitaire->update(['base_price_cents' => 0]);
        $this->assertNull($this->catalogue()->prixPlancherCents($this->sanitaire->fresh(), null));
    }

    #[Test]
    public function la_devise_suit_le_pays_de_la_zone(): void
    {
        $maroc = Country::factory()->create(['currency_code' => 'MAD']);
        $this->zone->update(['country_id' => $maroc->id]);

        $this->assertSame('MAD', $this->catalogue()->devise($this->zone->id));

        // Témoin : sans zone, la devise de base.
        config(['fx.base_currency' => 'EUR']);
        $this->assertSame('EUR', $this->catalogue()->devise(null));
    }
}
```

- [ ] **Step 2: Lancer le test et le voir échouer**

Run: `cd /c/Users/mmdar/Desktop/code/work/brio && $PHP artisan test tests/Feature/OrderEngine/CatalogueServableTest.php`
Expected: FAIL — `Target class [App\Services\OrderEngine\CatalogueServable] does not exist.`

- [ ] **Step 3: Écrire l'implémentation minimale**

```php
<?php

namespace App\Services\OrderEngine;

use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\Client\ClientPlaceService;
use Illuminate\Database\Eloquent\Builder;

/**
 * CE QUE LE MOTEUR DE COMMANDE ACCEPTE — DIT UNE SEULE FOIS.
 *
 * Le parcours web (`OrderJourney`) et le catalogue natif de l'application lisent ce filtre ici.
 * Écrit deux fois, il divergerait : l'application proposerait un métier que la commande refuse
 * ensuite, ou cacherait celui qu'elle accepte.
 */
class CatalogueServable
{
    public function __construct(protected ClientPlaceService $lieux) {}

    /** @return Builder<Sector> */
    public function secteurs(?string $mode, ?int $zoneId): Builder
    {
        return Sector::query()
            ->active()
            ->ordered()
            // UN SECTEUR SANS AUCUN MÉTIER SERVABLE N'EST PAS PROPOSÉ.
            ->whereHas('trades', fn (Builder $q) => $this->contraindreLesMetiers($q, $mode, $zoneId));
    }

    /** @return Builder<Trade> */
    public function metiers(int $sectorId, ?string $mode, ?int $zoneId): Builder
    {
        return $this->contraindreLesMetiers(Trade::query()->where('sector_id', $sectorId), $mode, $zoneId)
            ->orderBy('sort_order');
    }

    /** La contrainte seule, pour un `withCount` ou un `whereHas`. */
    public function contraindreLesMetiers(Builder $query, ?string $mode, ?int $zoneId): Builder
    {
        return $query->where('is_active', true)->servableEnMode($mode, $zoneId);
    }

    /** La zone du lieu par défaut — la même source que le pré-remplissage du parcours web. */
    public function zoneDuClient(User $client): ?int
    {
        $zone = $this->lieux->parDefaut($client)?->service_zone_id;

        return $zone === null ? null : (int) $zone;
    }

    /** Le plancher annoncé avant devis : tarif de zone, sinon prix du métier, sinon inconnu. */
    public function prixPlancherCents(Trade $trade, ?TradeZonePricing $ligneDeZone): ?int
    {
        $tarifDeZone = $ligneDeZone !== null && $ligneDeZone->is_active ? (int) $ligneDeZone->base_rate_cents : 0;

        if ($tarifDeZone > 0) {
            return $tarifDeZone;
        }

        $prixDuMetier = (int) ($trade->base_price_cents ?? 0);

        return $prixDuMetier > 0 ? $prixDuMetier : null;
    }

    public function devise(?int $zoneId): string
    {
        $zone = $zoneId === null ? null : ServiceZone::query()->with('country')->find($zoneId);

        return $zone?->deviseDeLaZone() ?? strtoupper((string) config('fx.base_currency', 'EUR'));
    }
}
```

- [ ] **Step 4: Relancer et voir passer**

Run: `$PHP artisan test tests/Feature/OrderEngine/CatalogueServableTest.php`
Expected: PASS — 9 tests.

- [ ] **Step 5: Commit** (seulement si les commits ont été demandés)

```bash
git add app/Services/OrderEngine/CatalogueServable.php tests/Feature/OrderEngine/CatalogueServableTest.php
git commit -m "feat(commande): une seule definition de ce que le moteur accepte, pour le web et l'application"
```

---

### Task 2: `OrderJourney` passe par `CatalogueServable`

**Files:**
- Modify: `app/Livewire/OrderEngine/OrderJourney.php` — méthodes `sectors()` (≈ lignes 458-479) et `trades()` (≈ lignes 506-520)
- Test (existants, caractérisation) : `tests/Feature/OrderEngine/TroisModesWebTest.php`, `tests/Feature/OrderEngine/OrderJourneyTest.php`, `tests/Feature/OrderEngine/SectorLiveSignalTest.php`, `tests/Feature/OrderEngine/CatalogueTraductionTest.php`

**Interfaces:**
- Consumes: `CatalogueServable::secteurs`, `::metiers`, `::contraindreLesMetiers` (Task 1).
- Produces: aucun changement visible — mêmes secteurs, mêmes métiers, même ordre, mêmes `trades_count` et `active_providers_count`.

`TroisModesWebTest` fige déjà le comportement à préserver : l'immédiat ne montre que les métiers qui l'acceptent, une zone fermée à l'immédiat les retire, et sans zone rien n'est filtré.

- [ ] **Step 1: Vérifier que la caractérisation passe AVANT toute modification**

Run: `$PHP artisan test tests/Feature/OrderEngine/TroisModesWebTest.php tests/Feature/OrderEngine/OrderJourneyTest.php tests/Feature/OrderEngine/SectorLiveSignalTest.php tests/Feature/OrderEngine/CatalogueTraductionTest.php`
Expected: PASS. Si un test échoue déjà, s'arrêter et le signaler — ne pas refactorer sur une base rouge.

- [ ] **Step 2: Remplacer le corps de `sectors()`**

Avant :

```php
        $sectors = Sector::query()
            ->active()
            ->ordered()
            // Les traductions viennent AVEC, comme celles des questions plus bas (ligne 655).
            ->with('translations')
            ->withCount(['trades' => fn ($q) => $q->where('is_active', true)
                ->servableEnMode($this->intendedMode, $this->serviceZoneId)])
            // UN SECTEUR SANS AUCUN MÉTIER SERVABLE N'EST PAS PROPOSÉ.
            ->whereHas('trades', fn ($q) => $q->where('is_active', true)
                ->servableEnMode($this->intendedMode, $this->serviceZoneId))
            ->get();
```

Après :

```php
        // LE FILTRE VIT DANS `CatalogueServable` : l'application native lit le même.
        $catalogue = app(CatalogueServable::class);

        $sectors = $catalogue->secteurs($this->intendedMode, $this->serviceZoneId)
            // Les traductions viennent AVEC, comme celles des questions plus bas.
            ->with('translations')
            ->withCount(['trades' => fn ($q) => $catalogue->contraindreLesMetiers($q, $this->intendedMode, $this->serviceZoneId)])
            ->get();
```

- [ ] **Step 3: Remplacer le corps de `trades()`**

Avant :

```php
        return Trade::query()
            ->where('sector_id', $this->sectorId)
            ->where('is_active', true)
            ->servableEnMode($this->intendedMode, $this->serviceZoneId)
            ->orderBy('sort_order')
            ->with('translations')
            ->get();
```

Après :

```php
        return app(CatalogueServable::class)
            ->metiers($this->sectorId, $this->intendedMode, $this->serviceZoneId)
            ->with('translations')
            ->get();
```

Ajouter en tête du fichier : `use App\Services\OrderEngine\CatalogueServable;` (à côté des autres `use App\Services\OrderEngine\...`). Laisser l'import `Sector` s'il sert encore ailleurs dans le fichier (`sector()` l'emploie).

- [ ] **Step 4: Relancer la caractérisation, puis tout le dossier du moteur**

Run: `$PHP artisan test tests/Feature/OrderEngine/TroisModesWebTest.php tests/Feature/OrderEngine/OrderJourneyTest.php tests/Feature/OrderEngine/SectorLiveSignalTest.php tests/Feature/OrderEngine/CatalogueTraductionTest.php tests/Feature/OrderEngine/CatalogueServableTest.php`
Expected: PASS, mêmes nombres qu'au Step 1 plus les 9 tests de Task 1.

Run: `$PHP artisan test tests/Feature/OrderEngine`
Expected: PASS (le budget de requêtes du parcours reste tenu).

- [ ] **Step 5: Commit** (seulement si les commits ont été demandés)

```bash
git add app/Livewire/OrderEngine/OrderJourney.php
git commit -m "refactor(commande): le parcours web lit le filtre servable depuis sa source unique"
```

---

### Task 3: `GET /api/client/catalogue`

**Files:**
- Create: `app/Http/Controllers/Api/Client/CatalogueController.php`
- Modify: `routes/api/client.php` — import en tête, route dans le groupe `Route::middleware(['auth:sanctum', 'verified'])->prefix('client')` qui porte `budget`, `protection`, `order-intent` (≈ ligne 401)
- Test: `tests/Feature/Api/Client/CatalogueApiTest.php`

**Interfaces:**
- Consumes: `CatalogueServable` (Task 1) ; `App\Services\I18n\LocaleResolver::resolveFromRequest(Request): string` ; `HasCatalogTranslations::translate(string $field, ?string $locale)`.
- Produces (contrat lu par Task 4) :

```json
{
  "mode": "asap|scheduled",
  "zone_known": true,
  "currency": "EUR",
  "sectors": [
    { "slug": "string", "name": "string", "icon": "string|null",
      "trades": [ { "slug": "string", "name": "string", "icon": "string|null",
                    "short_description": "string|null", "floor_price_cents": 8500, "hourly": false } ] }
  ]
}
```

La langue est résolue DANS le contrôleur (`LocaleResolver`) : `SetLocale` n'est pas déclaré sur la pile API dans `bootstrap/app.php`, l'appel à `translate()` ne peut donc pas compter sur `App::getLocale()`.

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

namespace Tests\Feature\Api\Client;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\ClientPlace;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Support\Domain\OrderMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LE CATALOGUE NATIF LIT CE QUE LE MOTEUR ACCEPTE — ni plus, ni moins, ni à un autre prix. */
class CatalogueApiTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $zone;

    private Sector $urgent;

    private Sector $lent;

    private Trade $plomberie;

    private Trade $ravalement;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fx.base_currency' => 'EUR']);

        $this->zone = ServiceZone::create([
            'name' => 'Zone api', 'slug' => 'zone-api', 'code' => 'ZAP',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->urgent = Sector::create(['name' => 'Dépannage', 'slug' => 'depannage-api', 'icon' => 'hammer', 'is_active' => true, 'sort_order' => 1]);
        $this->lent = Sector::create(['name' => 'Gros œuvre', 'slug' => 'gros-oeuvre-api', 'icon' => 'hammer', 'is_active' => true, 'sort_order' => 2]);

        $this->plomberie = Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => 'plomberie-api', 'code' => 'PLB-AP', 'name' => 'Plomberie',
            'icon' => 'wrench', 'short_description' => 'Fuites, débouchages, sanitaires',
            'is_active' => true, 'sort_order' => 1, 'allows_scheduled' => true, 'allows_asap' => true, 'allows_bundle' => true,
            'base_price_cents' => 8500, 'hourly_billing' => false,
        ]);
        $this->ravalement = Trade::create([
            'sector_id' => $this->lent->id, 'slug' => 'ravalement-api', 'code' => 'RAV-AP', 'name' => 'Ravalement',
            'icon' => 'home', 'is_active' => true, 'sort_order' => 1,
            'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
        ]);
    }

    private function client(?string $langue = null): User
    {
        $client = User::factory()->client()->create();

        if ($langue !== null) {
            $client->forceFill(['locale' => $langue])->save();
        }

        return $client->fresh();
    }

    private function lieuParDefaut(User $client): void
    {
        ClientPlace::factory()->parDefaut()->create(['user_id' => $client->id, 'service_zone_id' => $this->zone->id]);
    }

    #[Test]
    public function sans_jeton_la_route_refuse(): void
    {
        $this->getJson('/api/client/catalogue?mode=asap')->assertUnauthorized();
    }

    #[Test]
    public function temoin_un_client_authentifie_recoit_le_catalogue(): void
    {
        $this->actingAs($this->client(), 'sanctum')->getJson('/api/client/catalogue?mode=asap')->assertOk();
    }

    #[Test]
    public function seuls_l_immediat_et_le_rendez_vous_ont_un_catalogue_natif(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=bundle')->assertStatus(422);
        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=n-importe-quoi')->assertStatus(422);
        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue')->assertStatus(422);

        // Témoin : le rendez-vous est accepté.
        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=scheduled')->assertOk();
    }

    #[Test]
    public function le_contrat_de_reponse_sans_lieu_par_defaut(): void
    {
        $reponse = $this->actingAs($this->client(), 'sanctum')->getJson('/api/client/catalogue?mode=asap');

        $reponse->assertOk()->assertExactJson([
            'mode' => 'asap',
            'zone_known' => false,
            'currency' => 'EUR',
            'sectors' => [[
                'slug' => 'depannage-api',
                'name' => 'Dépannage',
                'icon' => 'hammer',
                'trades' => [[
                    'slug' => 'plomberie-api',
                    'name' => 'Plomberie',
                    'icon' => 'wrench',
                    'short_description' => 'Fuites, débouchages, sanitaires',
                    'floor_price_cents' => 8500,
                    'hourly' => false,
                ]],
            ]],
        ]);
    }

    #[Test]
    public function le_lieu_par_defaut_fixe_la_zone_et_son_tarif(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);

        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertOk()
            ->assertJsonPath('zone_known', true)
            ->assertJsonPath('sectors.0.trades.0.floor_price_cents', 9900);
    }

    #[Test]
    public function une_zone_fermee_a_l_immediat_vide_le_catalogue_immediat(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => false,
        ]);

        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertOk()->assertJsonPath('sectors', []);

        // Témoin : le rendez-vous, lui, garde les deux secteurs.
        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=scheduled')
            ->assertOk()->assertJsonCount(2, 'sectors');
    }

    #[Test]
    public function les_libelles_suivent_la_langue_du_compte(): void
    {
        $this->plomberie->setTranslation('name', 'nl', 'Loodgieter');

        $this->actingAs($this->client('nl'), 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertJsonPath('sectors.0.trades.0.name', 'Loodgieter');

        // Témoin : un compte francophone garde le libellé d'origine.
        $this->actingAs($this->client('fr'), 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertJsonPath('sectors.0.trades.0.name', 'Plomberie');
    }

    #[Test]
    public function l_application_voit_exactement_ce_que_propose_le_web(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);

        foreach ([OrderMode::ASAP, OrderMode::SCHEDULED] as $mode) {
            $api = $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode='.$mode)->json('sectors');

            $web = Livewire::actingAs($client)->test(OrderJourney::class)->call('chooseIntent', $mode);

            $this->assertSame(
                $web->instance()->sectors->pluck('slug')->all(),
                array_column($api, 'slug'),
                "Secteurs divergents en mode $mode",
            );

            foreach ($web->instance()->sectors as $index => $secteur) {
                $web->set('sectorId', $secteur->id);
                $this->assertSame(
                    $web->instance()->trades->pluck('slug')->all(),
                    array_column($api[$index]['trades'], 'slug'),
                    "Métiers divergents en mode $mode pour {$secteur->slug}",
                );
            }
        }
    }

    #[Test]
    public function le_nombre_de_requetes_ne_depend_pas_du_nombre_de_metiers(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);

        $compter = function () use ($client): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=scheduled')->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $avant = $compter();

        foreach (range(1, 6) as $n) {
            Trade::create([
                'sector_id' => $this->urgent->id, 'slug' => "metier-$n-api", 'code' => "M$n-AP", 'name' => "Métier $n",
                'is_active' => true, 'sort_order' => 10 + $n, 'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
            ]);
        }

        $this->assertSame($avant, $compter());
    }
}
```

- [ ] **Step 2: Lancer le test et le voir échouer**

Run: `$PHP artisan test tests/Feature/Api/Client/CatalogueApiTest.php`
Expected: FAIL — la route n'existe pas (404 là où 200/401/422 sont attendus).

- [ ] **Step 3: Écrire le contrôleur**

```php
<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Services\I18n\LocaleResolver;
use App\Services\OrderEngine\CatalogueServable;
use App\Support\Domain\OrderMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * LE CATALOGUE NATIF DE L'APPLICATION CLIENTE.
 *
 * Il ne décide rien : ce qui est commandable, la zone et le prix plancher viennent de
 * `CatalogueServable`, que le parcours web lit aussi. « Plusieurs services » n'a pas de catalogue
 * natif — il reste servi par la vue web.
 */
class CatalogueController extends Controller
{
    public function __invoke(Request $request, CatalogueServable $catalogue, LocaleResolver $langues): JsonResponse
    {
        $mode = $request->validate([
            'mode' => ['required', 'string', Rule::in([OrderMode::ASAP, OrderMode::SCHEDULED])],
        ])['mode'];

        $zoneId = $catalogue->zoneDuClient($request->user());
        $langue = $langues->resolveFromRequest($request);

        $secteurs = $catalogue->secteurs($mode, $zoneId)
            ->with([
                'translations',
                // Contrainte d'un chargement anticipé : la fermeture reçoit la RELATION.
                'trades' => fn ($relation) => $catalogue
                    ->contraindreLesMetiers($relation->getQuery(), $mode, $zoneId)
                    ->orderBy('sort_order')
                    ->with('translations'),
            ])
            ->get();

        // Les lignes de zone en UNE requête, jamais une par métier.
        $lignes = $zoneId === null
            ? collect()
            : TradeZonePricing::query()
                ->where('service_zone_id', $zoneId)
                ->whereIn('trade_id', $secteurs->flatMap->trades->pluck('id'))
                ->get()
                ->keyBy('trade_id');

        return response()->json([
            'mode' => $mode,
            'zone_known' => $zoneId !== null,
            'currency' => $catalogue->devise($zoneId),
            'sectors' => $secteurs->map(fn (Sector $secteur) => [
                'slug' => $secteur->slug,
                'name' => $secteur->translate('name', $langue),
                'icon' => $secteur->icon,
                'trades' => $secteur->trades->map(fn (Trade $metier) => [
                    'slug' => $metier->slug,
                    'name' => $metier->translate('name', $langue),
                    'icon' => $metier->icon,
                    'short_description' => $metier->translate('short_description', $langue),
                    'floor_price_cents' => $catalogue->prixPlancherCents($metier, $lignes->get($metier->id)),
                    'hourly' => (bool) $metier->hourly_billing,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }
}
```

- [ ] **Step 4: Déclarer la route**

Dans `routes/api/client.php`, ajouter l'import par ordre alphabétique parmi les `use App\Http\Controllers\Api\Client\...` :

```php
use App\Http\Controllers\Api\Client\CatalogueController;
```

Puis, dans le groupe existant :

```php
Route::middleware(['auth:sanctum', 'verified'])->prefix('client')->group(function () {
    Route::get('/budget', [HomeInsightsController::class, 'budget']);
    Route::get('/protection', [HomeInsightsController::class, 'protection']);
    // Sous drapeau : coupé, ce point répond 404 plutôt qu'une interprétation vide que
    // l'application lirait comme « l'assistant n'a rien compris ».
    Route::post('/order-intent', [HomeInsightsController::class, 'interpret']);
    // Le catalogue natif (immédiat, rendez-vous) — le même filtre que le parcours web.
    Route::get('/catalogue', CatalogueController::class)->name('api.client.catalogue');
});
```

- [ ] **Step 5: Relancer et voir passer**

Run: `$PHP artisan test tests/Feature/Api/Client/CatalogueApiTest.php tests/Feature/OrderEngine/CatalogueServableTest.php`
Expected: PASS — 9 + 9 tests.

Si `le_nombre_de_requetes_ne_depend_pas_du_nombre_de_metiers` échoue, un chargement n'est pas anticipé : relire les `with()` du contrôleur, ne pas relâcher l'assertion.

- [ ] **Step 6: Lancer les suites voisines**

Run: `$PHP artisan test tests/Feature/Api tests/Feature/OrderEngine`
Expected: PASS.

- [ ] **Step 7: Commit** (seulement si les commits ont été demandés)

```bash
git add app/Http/Controllers/Api/Client/CatalogueController.php routes/api/client.php tests/Feature/Api/Client/CatalogueApiTest.php
git commit -m "feat(api): le catalogue natif lit ce que le moteur de commande accepte, a son prix plancher"
```

---

### Task 4: Données et modèle pur de la sonde (mobile)

**Files:**
- Create: `mobile/client/src/catalogue/types.ts`
- Create: `mobile/client/src/catalogue/useCatalogue.ts`
- Create: `mobile/client/src/catalogue/iconeDuMetier.ts`
- Create: `mobile/client/src/catalogue/modeleDeSonde.ts`
- Create: `mobile/client/src/catalogue/index.ts`
- Test: `mobile/client/__tests__/catalogue/modeleDeSonde.test.ts`, `iconeDuMetier.test.ts`, `useCatalogue.test.tsx`

**Interfaces:**
- Consumes: le contrat JSON de Task 3 ; `apiClient` de `@/api`.
- Produces (utilisé par Tasks 5 à 7) :
  - types `ModeCatalogue = 'asap' | 'scheduled'`, `MetierDuCatalogue`, `SecteurDuCatalogue`, `ReponseCatalogue`, `Cote = 'droite' | 'gauche'`, `RepereDeSonde`
  - `useCatalogue(mode: ModeCatalogue)` → `UseQueryResult<ReponseCatalogue>`
  - `iconeDuMetier(nom: string | null | undefined): NomIonicons`, `ICONES_DU_CATALOGUE`, `ICONE_PAR_DEFAUT`
  - `HAUTEUR_DE_REPERE = 88`, `construireLaSonde(sectors): RepereDeSonde[]`, `indexDepuisDecalage(y, total): number`, `decalageDeIndex(index): number`, `cheminDeCommande(repere, mode): string`

- [ ] **Step 1: Écrire les tests qui échouent**

`mobile/client/__tests__/catalogue/modeleDeSonde.test.ts` :

```ts
import {
  HAUTEUR_DE_REPERE,
  cheminDeCommande,
  construireLaSonde,
  decalageDeIndex,
  indexDepuisDecalage,
} from '@/catalogue';
import type { SecteurDuCatalogue } from '@/catalogue';

const metier = (slug: string) => ({
  slug, name: slug, icon: null, short_description: null, floor_price_cents: null, hourly: false,
});

const secteurs: SecteurDuCatalogue[] = [
  { slug: 'batiment', name: 'Bâtiment', icon: 'hammer', trades: [metier('peinture'), metier('plomberie')] },
  { slug: 'nettoyage', name: 'Nettoyage', icon: 'sparkles', trades: [metier('vitres')] },
  { slug: 'verts', name: 'Espaces verts', icon: 'tree', trades: [metier('jardinage')] },
];

describe('construireLaSonde', () => {
  const sonde = construireLaSonde(secteurs);

  it('met tous les métiers bout à bout, avec leur rang sur le total', () => {
    expect(sonde.map(r => r.metier.slug)).toEqual(['peinture', 'plomberie', 'vitres', 'jardinage']);
    expect(sonde.map(r => `${r.rang}/${r.total}`)).toEqual(['1/4', '2/4', '3/4', '4/4']);
  });

  it('change de côté à chaque secteur', () => {
    expect(sonde.map(r => r.cote)).toEqual(['droite', 'droite', 'gauche', 'droite']);
  });

  it("témoin : deux métiers d'un même secteur restent du même côté", () => {
    expect(sonde[0].cote).toBe(sonde[1].cote);
  });

  it("porte l'étiquette du secteur sur son seul premier métier", () => {
    expect(sonde.map(r => r.etiquetteSecteur)).toEqual(['Bâtiment', null, 'Nettoyage', 'Espaces verts']);
  });

  it('une réponse vide donne une sonde vide', () => {
    expect(construireLaSonde([])).toEqual([]);
  });
});

describe('indexDepuisDecalage', () => {
  it('arrondit au repère le plus proche', () => {
    expect(indexDepuisDecalage(3 * HAUTEUR_DE_REPERE, 10)).toBe(3);
    expect(indexDepuisDecalage(3 * HAUTEUR_DE_REPERE + 43, 10)).toBe(3);
    expect(indexDepuisDecalage(3 * HAUTEUR_DE_REPERE + 45, 10)).toBe(4);
  });

  it('reste dans les bornes, même quand le rebond dépasse', () => {
    expect(indexDepuisDecalage(-120, 10)).toBe(0);
    expect(indexDepuisDecalage(99999, 10)).toBe(9);
    expect(indexDepuisDecalage(500, 0)).toBe(0);
  });

  it('est l’inverse de decalageDeIndex', () => {
    expect(indexDepuisDecalage(decalageDeIndex(7), 10)).toBe(7);
  });
});

describe('cheminDeCommande', () => {
  it('ouvre le moteur sur le secteur et le métier, avec le mode', () => {
    const [premier] = construireLaSonde(secteurs);
    expect(cheminDeCommande(premier, 'asap')).toBe('/commander/batiment/peinture?mode=asap');
  });

  it("encode un slug inattendu plutôt que de casser l'URL", () => {
    const [bizarre] = construireLaSonde([{ ...secteurs[0], slug: 'a b', trades: [metier('c/d')] }]);
    expect(cheminDeCommande(bizarre, 'scheduled')).toBe('/commander/a%20b/c%2Fd?mode=scheduled');
  });
});
```

`mobile/client/__tests__/catalogue/iconeDuMetier.test.ts` :

```ts
import fs from 'fs';
import path from 'path';
import { ICONES_DU_CATALOGUE, ICONE_PAR_DEFAUT, iconeDuMetier } from '@/catalogue';

/*
 * LE VRAI FICHIER DE GLYPHES, PAS LE BOUCHON.
 *
 * `__mocks__/@expo/vector-icons.tsx` répond « présent » pour n'importe quel nom : un test appuyé
 * dessus passerait avec une icône qui n'existe pas. npm place le paquet côté client ou à la racine
 * de l'espace de travail selon les versions : les deux emplacements sont essayés.
 */
const CANDIDATS = [
  path.resolve(__dirname, '../../node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/glyphmaps/Ionicons.json'),
  path.resolve(__dirname, '../../../node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/glyphmaps/Ionicons.json'),
];
const fichier = CANDIDATS.find(p => fs.existsSync(p));
const glyphes: Record<string, number> = fichier ? JSON.parse(fs.readFileSync(fichier, 'utf8')) : {};

describe('iconeDuMetier', () => {
  it('trouve le fichier de glyphes Ionicons', () => {
    expect(fichier).toBeDefined();
  });

  it('témoin : un nom inventé est absent du vrai fichier', () => {
    expect('icone-qui-n-existe-pas' in glyphes).toBe(false);
    expect('briefcase-outline' in glyphes).toBe(true);
  });

  it.each(Object.entries(ICONES_DU_CATALOGUE))('%s → %s existe dans Ionicons', (_serveur, ionicons) => {
    expect(ionicons in glyphes).toBe(true);
  });

  it('traduit les noms du serveur', () => {
    expect(iconeDuMetier('wrench')).toBe('build-outline');
    expect(iconeDuMetier('shield-check')).toBe('shield-checkmark-outline');
  });

  it('retombe sur la mallette pour un nom absent, vide ou piégé', () => {
    expect(iconeDuMetier('inconnu')).toBe(ICONE_PAR_DEFAUT);
    expect(iconeDuMetier(null)).toBe(ICONE_PAR_DEFAUT);
    expect(iconeDuMetier('')).toBe(ICONE_PAR_DEFAUT);
    expect(iconeDuMetier('constructor')).toBe(ICONE_PAR_DEFAUT);
  });
});
```

`mobile/client/__tests__/catalogue/useCatalogue.test.tsx` :

```tsx
import React from 'react';
import { renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const mockGet = jest.fn();

jest.mock('@/api', () => ({
  __esModule: true,
  apiClient: { get: (...args: unknown[]) => mockGet(...args) },
}));

import { useCatalogue } from '@/catalogue';

const reponse = { mode: 'asap', zone_known: false, currency: 'EUR', sectors: [] };

const enveloppe = ({ children }: { children: React.ReactNode }) => (
  <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
    {children}
  </QueryClientProvider>
);

beforeEach(() => {
  mockGet.mockReset();
  mockGet.mockResolvedValue({ data: reponse });
});

describe('useCatalogue', () => {
  it('demande le catalogue du mode choisi', async () => {
    const { result } = renderHook(() => useCatalogue('asap'), { wrapper: enveloppe });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockGet).toHaveBeenCalledWith('/client/catalogue', { params: { mode: 'asap' } });
    expect(result.current.data).toEqual(reponse);
  });

  it('témoin : le rendez-vous envoie son propre mode', async () => {
    const { result } = renderHook(() => useCatalogue('scheduled'), { wrapper: enveloppe });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockGet).toHaveBeenCalledWith('/client/catalogue', { params: { mode: 'scheduled' } });
  });
});
```

- [ ] **Step 2: Lancer les tests et les voir échouer**

Run: `cd /c/Users/mmdar/Desktop/code/work/brio/mobile/client && npx jest __tests__/catalogue`
Expected: FAIL — `Cannot find module '@/catalogue'`.

- [ ] **Step 3: Écrire `types.ts`**

```ts
/** Le catalogue servi par `GET /api/client/catalogue` — ce que le moteur de commande accepte. */
export type ModeCatalogue = 'asap' | 'scheduled';

export interface MetierDuCatalogue {
  slug: string;
  name: string;
  icon: string | null;
  short_description: string | null;
  /** Hors taxe. `null` : aucun prix plancher, le prix sort des réponses au questionnaire. */
  floor_price_cents: number | null;
  /** Le montant se lit par heure. */
  hourly: boolean;
}

export interface SecteurDuCatalogue {
  slug: string;
  name: string;
  icon: string | null;
  trades: MetierDuCatalogue[];
}

export interface ReponseCatalogue {
  mode: ModeCatalogue;
  zone_known: boolean;
  currency: string;
  sectors: SecteurDuCatalogue[];
}
```

- [ ] **Step 4: Écrire `useCatalogue.ts`**

```ts
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';
import type { ModeCatalogue, ReponseCatalogue } from './types';

/**
 * LE CATALOGUE NE SE DÉCIDE PAS ICI.
 *
 * Le serveur applique le filtre du moteur de commande (mode, zone du lieu par défaut) : une liste
 * tenue en dur dans l'application proposerait des métiers que la commande refuserait ensuite.
 */
export function useCatalogue(mode: ModeCatalogue) {
  return useQuery<ReponseCatalogue>({
    queryKey: ['catalogue', mode],
    queryFn: async () => {
      const { data } = await apiClient.get<ReponseCatalogue>('/client/catalogue', { params: { mode } });

      return data;
    },
    // Le catalogue change au rythme de l'administration, pas de la session.
    staleTime: 5 * 60 * 1000,
  });
}
```

- [ ] **Step 5: Écrire `iconeDuMetier.ts`**

```ts
import type { Ionicons } from '@expo/vector-icons';

export type NomIonicons = keyof typeof Ionicons.glyphMap;

/**
 * Les icônes du catalogue sont nommées par le web (Heroicons). Le mobile dessine en Ionicons :
 * chaque cible est vérifiée contre le vrai fichier de glyphes par `iconeDuMetier.test.ts`.
 */
export const ICONES_DU_CATALOGUE: Readonly<Record<string, NomIonicons>> = {
  hammer: 'hammer-outline',
  sparkles: 'sparkles-outline',
  tree: 'leaf-outline',
  leaf: 'leaf-outline',
  users: 'people-outline',
  'user-group': 'people-outline',
  'shield-check': 'shield-checkmark-outline',
  car: 'car-outline',
  'paint-roller': 'brush-outline',
  broom: 'trash-bin-outline',
  wrench: 'build-outline',
  bolt: 'flash-outline',
  window: 'grid-outline',
  home: 'home-outline',
  truck: 'cube-outline',
  'arrow-up': 'arrow-up-outline',
  'pencil-square': 'create-outline',
};

/** Le repli du modèle `Trade` est `briefcase` : on garde la même idée. */
export const ICONE_PAR_DEFAUT: NomIonicons = 'briefcase-outline';

export function iconeDuMetier(nom: string | null | undefined): NomIonicons {
  // `hasOwnProperty` : un nom comme « constructor » atteindrait sinon le prototype de l'objet.
  if (nom && Object.prototype.hasOwnProperty.call(ICONES_DU_CATALOGUE, nom)) {
    return ICONES_DU_CATALOGUE[nom];
  }

  return ICONE_PAR_DEFAUT;
}
```

- [ ] **Step 6: Écrire `modeleDeSonde.ts`**

```ts
import type { MetierDuCatalogue, ModeCatalogue, SecteurDuCatalogue } from './types';

/** La hauteur d'un repère : le pas de l'aimantation et de la sélection. */
export const HAUTEUR_DE_REPERE = 88;

export type Cote = 'droite' | 'gauche';

export interface RepereDeSonde {
  cle: string;
  secteur: SecteurDuCatalogue;
  metier: MetierDuCatalogue;
  /** Rang dans la liste complète du mode, à partir de 1. */
  rang: number;
  total: number;
  /** Les métiers d'un secteur de rang pair à droite, impair à gauche. */
  cote: Cote;
  /** Le nom du secteur sur son premier métier seulement. */
  etiquetteSecteur: string | null;
}

export function construireLaSonde(secteurs: SecteurDuCatalogue[]): RepereDeSonde[] {
  const total = secteurs.reduce((somme, secteur) => somme + secteur.trades.length, 0);
  const reperes: RepereDeSonde[] = [];

  secteurs.forEach((secteur, rangSecteur) => {
    secteur.trades.forEach((metier, rangMetier) => {
      reperes.push({
        cle: `${secteur.slug}/${metier.slug}`,
        secteur,
        metier,
        rang: reperes.length + 1,
        total,
        cote: rangSecteur % 2 === 0 ? 'droite' : 'gauche',
        etiquetteSecteur: rangMetier === 0 ? secteur.name : null,
      });
    });
  });

  return reperes;
}

/** Le repère centré pour un décalage de défilement — borné, le rebond dépasse souvent. */
export function indexDepuisDecalage(decalageY: number, total: number): number {
  if (total <= 0) {
    return 0;
  }

  return Math.min(Math.max(Math.round(decalageY / HAUTEUR_DE_REPERE), 0), total - 1);
}

export function decalageDeIndex(index: number): number {
  return index * HAUTEUR_DE_REPERE;
}

/** Le moteur de commande web, ouvert sur le métier : `OrderJourney::mount($sector, $trade)` lit ces slugs. */
export function cheminDeCommande(repere: RepereDeSonde, mode: ModeCatalogue): string {
  return `/commander/${encodeURIComponent(repere.secteur.slug)}/${encodeURIComponent(repere.metier.slug)}?mode=${mode}`;
}
```

- [ ] **Step 7: Écrire `index.ts`**

```ts
export * from './types';
export { useCatalogue } from './useCatalogue';
export { ICONES_DU_CATALOGUE, ICONE_PAR_DEFAUT, iconeDuMetier } from './iconeDuMetier';
export type { NomIonicons } from './iconeDuMetier';
export {
  HAUTEUR_DE_REPERE,
  cheminDeCommande,
  construireLaSonde,
  decalageDeIndex,
  indexDepuisDecalage,
} from './modeleDeSonde';
export type { Cote, RepereDeSonde } from './modeleDeSonde';
```

- [ ] **Step 8: Relancer et voir passer**

Run: `npx jest __tests__/catalogue`
Expected: PASS — trois fichiers.

- [ ] **Step 9: Commit** (seulement si les commits ont été demandés)

```bash
git add mobile/client/src/catalogue mobile/client/__tests__/catalogue
git commit -m "feat(mobile): le catalogue natif et le modele pur de la sonde"
```

---

### Task 5: Jeton « argent », clés de traduction et libellé du prix

**Files:**
- Modify: `mobile/shared/src/theme/useThemeColors.ts` (après `textOnAccent`)
- Modify: `mobile/shared/src/theme/__tests__/lisibilite.test.ts` (tableau `JETONS`)
- Modify: `mobile/shared/src/i18n/catalogues/fr.ts`, `nl.ts`, `en.ts`, `es.ts`, `it.ts`, `de.ts` (avant le `};` final)
- Create: `mobile/client/src/screens/catalogue/libelleDuPrix.ts`
- Test: `mobile/client/__tests__/catalogue/libelleDuPrix.test.ts`

**Interfaces:**
- Consumes: `MetierDuCatalogue` (Task 4) ; `formatMontant(montant, devise, decimales)` de `@/format/money` ; `useTraduction` de `@/i18n`.
- Produces:
  - jeton `theme.argent: string` (sombre `colors.accent.amber`, clair `colors.warning[800]`)
  - clés `catalogue.des_montant`, `catalogue.des_montant_par_heure`, `catalogue.prix_selon_vos_reponses`, `catalogue.commander`, `catalogue.position`, `catalogue.repere_accessible`, `catalogue.aucun_metier_immediat`, `catalogue.prendre_rendez_vous_plutot`, `catalogue.chargement_impossible`, `catalogue.aucun_metier` (catalogue vide hors immédiat — ajoutée au plan : le message de l'immédiat y serait faux)
  - `type Traducteur = ReturnType<typeof useTraduction>['t']`
  - `libelleDuPrix(metier: MetierDuCatalogue, devise: string, tr: Traducteur): string`

Pourquoi un jeton : l'ambre est « réservé à l'argent » par le thème, mais `accent` vaut `#ffb648` dans les deux thèmes et tombe sous 2:1 sur le verre clair. `colors.warning[800]` est déjà « le seul ambre en texte sur le verre clair » (`colors.ts`). Aucun écran ne colore encore un montant : il n'existe pas de jeton à réutiliser.

- [ ] **Step 1: Écrire les tests qui échouent**

Dans `lisibilite.test.ts`, ajouter la ligne au tableau `JETONS` (après `accent`) :

```ts
  ['argent', colors.warning[800], colors.accent.amber],
```

`mobile/client/__tests__/catalogue/libelleDuPrix.test.ts` :

```ts
import { renderHook } from '@testing-library/react-native';
import { useTraduction } from '@/i18n';
import { useThemeColors } from '@/theme/useThemeColors';
import { libelleDuPrix } from '@/screens/catalogue/libelleDuPrix';

const metier = (floor_price_cents: number | null, hourly = false) => ({
  slug: 'plomberie', name: 'Plomberie', icon: 'wrench', short_description: null, floor_price_cents, hourly,
});

describe('libelleDuPrix', () => {
  const { result } = renderHook(() => useTraduction());
  const tr = result.current.t;

  it('annonce un plancher hors taxe, arrondi à l’euro', () => {
    expect(libelleDuPrix(metier(8500), 'EUR', tr)).toMatch(/^dès 85\s€ hors taxe$/u);
  });

  it('lit un tarif horaire par heure', () => {
    expect(libelleDuPrix(metier(4500, true), 'EUR', tr)).toMatch(/^dès 45\s€\/h hors taxe$/u);
  });

  it('témoin : un tarif au forfait ne porte pas « /h »', () => {
    expect(libelleDuPrix(metier(4500, false), 'EUR', tr)).not.toContain('/h');
  });

  it('sans plancher, le prix sort des réponses', () => {
    expect(libelleDuPrix(metier(null), 'EUR', tr)).toBe('Prix selon vos réponses');
  });

  it('suit la devise de la zone', () => {
    expect(libelleDuPrix(metier(8500), 'MAD', tr)).toContain('MAD');
  });
});

describe('le jeton argent', () => {
  it('existe dans le thème', () => {
    const { result } = renderHook(() => useThemeColors());
    expect(typeof result.current.argent).toBe('string');
  });
});
```

- [ ] **Step 2: Lancer et voir échouer**

Run: `cd /c/Users/mmdar/Desktop/code/work/brio/mobile/client && npx jest __tests__/catalogue/libelleDuPrix.test.ts ../shared/src/theme/__tests__/lisibilite.test.ts`
Expected: FAIL — module `@/screens/catalogue/libelleDuPrix` introuvable ; `lisibilite` peut déjà passer (les deux valeurs tiennent le seuil) — c'est attendu, la ligne garde le jeton sous contrôle.

- [ ] **Step 3: Ajouter le jeton dans `useThemeColors.ts`**

Juste après `textOnAccent: '#241603',` :

```ts
    /*
     * L'ARGENT EN TEXTE — la seule couleur chaude, et elle ne sert qu'aux montants.
     *
     * `accent` garde le même ambre dans les deux thèmes : lisible sur la nuit, il tombe sous 2:1
     * sur le verre clair. Le jour prend le cran 800, que `colors.warning` réserve déjà à « l'ambre
     * en texte sur le verre clair ». `lisibilite.test.ts` tient les deux valeurs.
     */
    argent: isDark ? colors.accent.amber : colors.warning[800],
```

- [ ] **Step 4: Ajouter les clés dans les six catalogues**

Avant le `};` final de chaque fichier. Les jetons `:montant`, `:secteur`, `:rang`, `:total`, `:metier`, `:prix` sont identiques d'une langue à l'autre (`sixLangues.test.ts` le vérifie).

`fr.ts` :

```ts
  // Le catalogue natif (immédiat, rendez-vous).
  'catalogue.des_montant': 'dès :montant hors taxe',
  'catalogue.des_montant_par_heure': 'dès :montant/h hors taxe',
  'catalogue.prix_selon_vos_reponses': 'Prix selon vos réponses',
  'catalogue.commander': 'Commander',
  'catalogue.position': ':secteur · :rang sur :total',
  'catalogue.repere_accessible': ':metier, :prix',
  'catalogue.aucun_metier_immediat': 'Aucun métier n’accepte l’intervention immédiate pour votre adresse.',
  'catalogue.prendre_rendez_vous_plutot': 'Prendre rendez-vous',
  'catalogue.chargement_impossible': 'Le catalogue n’a pas pu être chargé.',
  'catalogue.aucun_metier': 'Aucun métier n’est proposé pour le moment.',
```

`nl.ts` :

```ts
  'catalogue.des_montant': 'vanaf :montant excl. btw',
  'catalogue.des_montant_par_heure': 'vanaf :montant/u excl. btw',
  'catalogue.prix_selon_vos_reponses': 'Prijs volgens uw antwoorden',
  'catalogue.commander': 'Bestellen',
  'catalogue.position': ':secteur · :rang van :total',
  'catalogue.repere_accessible': ':metier, :prix',
  'catalogue.aucun_metier_immediat': 'Geen enkel vak aanvaardt een onmiddellijke interventie op uw adres.',
  'catalogue.prendre_rendez_vous_plutot': 'Een afspraak maken',
  'catalogue.chargement_impossible': 'De catalogus kon niet worden geladen.',
  'catalogue.aucun_metier': 'Er wordt momenteel geen enkel vak aangeboden.',
```

`en.ts` :

```ts
  'catalogue.des_montant': 'from :montant excl. tax',
  'catalogue.des_montant_par_heure': 'from :montant/h excl. tax',
  'catalogue.prix_selon_vos_reponses': 'Price based on your answers',
  'catalogue.commander': 'Order',
  'catalogue.position': ':secteur · :rang of :total',
  'catalogue.repere_accessible': ':metier, :prix',
  'catalogue.aucun_metier_immediat': 'No trade accepts an immediate call-out at your address.',
  'catalogue.prendre_rendez_vous_plutot': 'Book an appointment',
  'catalogue.chargement_impossible': 'The catalogue could not be loaded.',
  'catalogue.aucun_metier': 'No trade is available right now.',
```

`es.ts` :

```ts
  'catalogue.des_montant': 'desde :montant sin impuestos',
  'catalogue.des_montant_par_heure': 'desde :montant/h sin impuestos',
  'catalogue.prix_selon_vos_reponses': 'Precio según sus respuestas',
  'catalogue.commander': 'Pedir',
  'catalogue.position': ':secteur · :rang de :total',
  'catalogue.repere_accessible': ':metier, :prix',
  'catalogue.aucun_metier_immediat': 'Ningún oficio acepta una intervención inmediata en su dirección.',
  'catalogue.prendre_rendez_vous_plutot': 'Pedir cita',
  'catalogue.chargement_impossible': 'No se ha podido cargar el catálogo.',
  'catalogue.aucun_metier': 'No hay ningún oficio disponible por el momento.',
```

`it.ts` :

```ts
  'catalogue.des_montant': 'da :montant IVA esclusa',
  'catalogue.des_montant_par_heure': 'da :montant/h IVA esclusa',
  'catalogue.prix_selon_vos_reponses': 'Prezzo in base alle Sue risposte',
  'catalogue.commander': 'Ordinare',
  'catalogue.position': ':secteur · :rang su :total',
  'catalogue.repere_accessible': ':metier, :prix',
  'catalogue.aucun_metier_immediat': 'Nessun mestiere accetta un intervento immediato al Suo indirizzo.',
  'catalogue.prendre_rendez_vous_plutot': 'Prendere appuntamento',
  'catalogue.chargement_impossible': 'Non è stato possibile caricare il catalogo.',
  'catalogue.aucun_metier': 'Al momento non è disponibile alcun mestiere.',
```

`de.ts` :

```ts
  'catalogue.des_montant': 'ab :montant zzgl. MwSt.',
  'catalogue.des_montant_par_heure': 'ab :montant/Std. zzgl. MwSt.',
  'catalogue.prix_selon_vos_reponses': 'Preis je nach Ihren Antworten',
  'catalogue.commander': 'Bestellen',
  'catalogue.position': ':secteur · :rang von :total',
  'catalogue.repere_accessible': ':metier, :prix',
  'catalogue.aucun_metier_immediat': 'Kein Gewerk nimmt an Ihrer Adresse einen Soforteinsatz an.',
  'catalogue.prendre_rendez_vous_plutot': 'Einen Termin vereinbaren',
  'catalogue.chargement_impossible': 'Der Katalog konnte nicht geladen werden.',
  'catalogue.aucun_metier': 'Derzeit wird kein Gewerk angeboten.',
```

- [ ] **Step 5: Écrire `libelleDuPrix.ts`**

```ts
import { formatMontant } from '@/format/money';
import type { useTraduction } from '@/i18n';
import type { MetierDuCatalogue } from '@/catalogue';

export type Traducteur = ReturnType<typeof useTraduction>['t'];

/**
 * Le plancher annoncé avant devis. Arrondi à l'unité (`formatMontant(..., 0)`, comme les autres
 * fourchettes de l'application) et TOUJOURS dit hors taxe : la TVA s'ajoute à la commande.
 */
export function libelleDuPrix(metier: MetierDuCatalogue, devise: string, tr: Traducteur): string {
  if (metier.floor_price_cents === null) {
    return tr('catalogue.prix_selon_vos_reponses');
  }

  const montant = formatMontant(metier.floor_price_cents / 100, devise, 0);

  return metier.hourly
    ? tr('catalogue.des_montant_par_heure', { montant })
    : tr('catalogue.des_montant', { montant });
}
```

- [ ] **Step 6: Relancer, puis les garde-fous de traduction et de thème**

Run: `npx jest __tests__/catalogue/libelleDuPrix.test.ts ../shared/src/theme/__tests__ ../shared/src/i18n/__tests__`
Expected: PASS — y compris `sixLangues`, `plusDeFrancaisEnDur`, `aucuneCleOrpheline`, `catalogues`.

Si `aucuneCleOrpheline` signale « le catalogue dérive » (clés non employées), c'est normal tant que les écrans des Tasks 6-7 n'existent pas : noter le nombre, et vérifier qu'il redescend à la Task 7.

- [ ] **Step 7: Commit** (seulement si les commits ont été demandés)

```bash
git add mobile/shared/src/theme/useThemeColors.ts mobile/shared/src/theme/__tests__/lisibilite.test.ts mobile/shared/src/i18n/catalogues/fr.ts mobile/shared/src/i18n/catalogues/nl.ts mobile/shared/src/i18n/catalogues/en.ts mobile/shared/src/i18n/catalogues/es.ts mobile/shared/src/i18n/catalogues/it.ts mobile/shared/src/i18n/catalogues/de.ts mobile/client/src/screens/catalogue/libelleDuPrix.ts mobile/client/__tests__/catalogue/libelleDuPrix.test.ts
git commit -m "feat(mobile): l'argent a son jeton de texte, et le prix plancher se dit hors taxe dans six langues"
```

---

### Task 6: `RepereDeSonde` et `FeuilleDuMetier`

**Files:**
- Create: `mobile/client/src/screens/catalogue/RepereDeSonde.tsx`
- Create: `mobile/client/src/screens/catalogue/FeuilleDuMetier.tsx`
- Test: `mobile/client/__tests__/catalogue/RepereDeSonde.test.tsx`, `mobile/client/__tests__/catalogue/FeuilleDuMetier.test.tsx`

**Interfaces:**
- Consumes: `RepereDeSonde` (type), `HAUTEUR_DE_REPERE`, `iconeDuMetier` (Task 4) ; `theme.argent` (Task 5) ; `GlassSurface`, `Icon`, `Button` de `@/ui`.
- Produces:
  - `RepereDeSonde(props: { repere: RepereDeSonde; choisi: boolean; libellePrix: string; mouvementReduit: boolean; onChoisir: () => void })`
  - `FeuilleDuMetier(props: { repere: RepereDeSonde; libellePrix: string; onCommander: () => void })`
  - testIDs : `repere-{slug}`, `moitie-gauche-{slug}`, `moitie-droite-{slug}`, `case-{slug}`, `etiquette-{slug}`, `feuille-du-metier`, `feuille-titre`, `feuille-position`, `feuille-prix`, `feuille-commander`

La rangée est faite de trois colonnes : une moitié gauche et une moitié droite (`flex: 1`), et un axe central de largeur fixe. L'axe tombe donc exactement au milieu de l'écran, là où `LigneDeSonde` (Task 7) trace le pointillé — sans calcul de position absolue.

- [ ] **Step 1: Écrire les tests qui échouent**

`mobile/client/__tests__/catalogue/RepereDeSonde.test.tsx` :

```tsx
import React from 'react';
import { fireEvent, render, within } from '@testing-library/react-native';

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    GlassSurface: ({ children, testID, style }: any) => <View testID={testID} style={style}>{children}</View>,
    Icon: ({ name }: any) => <Text>{name}</Text>,
    Button: ({ label, onPress, testID }: any) => <Text onPress={onPress} testID={testID}>{label}</Text>,
  };
});

import { construireLaSonde } from '@/catalogue';
import { RepereDeSonde } from '@/screens/catalogue/RepereDeSonde';

const metier = (slug: string, name: string) => ({
  slug, name, icon: null, short_description: null, floor_price_cents: 12000, hourly: false,
});

const [peinture, plomberie, vitres] = construireLaSonde([
  { slug: 'batiment', name: 'Bâtiment', icon: 'hammer', trades: [metier('peinture', 'Peinture'), metier('plomberie', 'Plomberie')] },
  { slug: 'nettoyage', name: 'Nettoyage', icon: 'sparkles', trades: [metier('vitres', 'Vitres')] },
]);

const afficher = (repere = peinture, choisi = false, onChoisir = jest.fn()) =>
  render(
    <RepereDeSonde repere={repere} choisi={choisi} libellePrix="dès 120 € hors taxe" mouvementReduit={false} onChoisir={onChoisir} />,
  );

describe('RepereDeSonde', () => {
  it('pose la case du premier secteur à droite de la ligne', () => {
    const ecran = afficher(peinture);
    expect(within(ecran.getByTestId('moitie-droite-peinture')).getByTestId('case-peinture')).toBeTruthy();
    expect(within(ecran.getByTestId('moitie-gauche-peinture')).queryByTestId('case-peinture')).toBeNull();
  });

  it('pose la case du secteur suivant à gauche', () => {
    const ecran = afficher(vitres);
    expect(within(ecran.getByTestId('moitie-gauche-vitres')).getByTestId('case-vitres')).toBeTruthy();
  });

  it("met l'étiquette du secteur en face de ses cases", () => {
    const ecran = afficher(peinture);
    expect(within(ecran.getByTestId('moitie-gauche-peinture')).getByTestId('etiquette-peinture')).toBeTruthy();
  });

  it("témoin : le second métier du secteur n'a pas d'étiquette", () => {
    expect(afficher(plomberie).queryByTestId('etiquette-plomberie')).toBeNull();
  });

  it('se présente comme un bouton sélectionnable, avec son prix', () => {
    const repere = afficher(peinture, true).getByTestId('repere-peinture');
    expect(repere.props.accessibilityRole).toBe('button');
    expect(repere.props.accessibilityState).toEqual({ selected: true });
    expect(repere.props.accessibilityLabel).toBe('Peinture, dès 120 € hors taxe');
  });

  it('témoin : un repère non choisi le dit aussi', () => {
    expect(afficher(peinture, false).getByTestId('repere-peinture').props.accessibilityState).toEqual({ selected: false });
  });

  it('se choisit au toucher', () => {
    const onChoisir = jest.fn();
    fireEvent.press(afficher(peinture, false, onChoisir).getByTestId('repere-peinture'));
    expect(onChoisir).toHaveBeenCalledTimes(1);
  });
});
```

`mobile/client/__tests__/catalogue/FeuilleDuMetier.test.tsx` :

```tsx
import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    GlassSurface: ({ children, testID, style }: any) => <View testID={testID} style={style}>{children}</View>,
    Icon: ({ name }: any) => <Text testID="feuille-icone">{name}</Text>,
    Button: ({ label, onPress, testID }: any) => <Text onPress={onPress} testID={testID}>{label}</Text>,
  };
});

import { construireLaSonde } from '@/catalogue';
import { FeuilleDuMetier } from '@/screens/catalogue/FeuilleDuMetier';

const sonde = construireLaSonde([
  {
    slug: 'batiment', name: 'Bâtiment', icon: 'hammer',
    trades: [
      { slug: 'peinture', name: 'Peinture', icon: 'paint-roller', short_description: null, floor_price_cents: 12000, hourly: false },
      { slug: 'plomberie', name: 'Plomberie', icon: 'wrench', short_description: 'Fuites, débouchages', floor_price_cents: 8500, hourly: false },
    ],
  },
  { slug: 'nettoyage', name: 'Nettoyage', icon: 'sparkles', trades: [
    { slug: 'vitres', name: 'Vitres', icon: 'window', short_description: null, floor_price_cents: null, hourly: false },
  ] },
]);

describe('FeuilleDuMetier', () => {
  it('dit le métier, sa place dans la liste et son prix', () => {
    const ecran = render(<FeuilleDuMetier repere={sonde[1]} libellePrix="dès 85 € hors taxe" onCommander={jest.fn()} />);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Plomberie');
    expect(ecran.getByTestId('feuille-position').props.children).toBe('Bâtiment · 2 sur 3');
    expect(ecran.getByTestId('feuille-prix').props.children).toBe('dès 85 € hors taxe');
    expect(ecran.getByTestId('feuille-icone').props.children).toBe('build-outline');
    expect(ecran.getByText('Fuites, débouchages')).toBeTruthy();
  });

  it('témoin : sans description, aucune ligne vide', () => {
    const ecran = render(<FeuilleDuMetier repere={sonde[0]} libellePrix="dès 120 € hors taxe" onCommander={jest.fn()} />);
    expect(ecran.queryByText('Fuites, débouchages')).toBeNull();
  });

  it('commande au toucher du bouton', () => {
    const onCommander = jest.fn();
    const ecran = render(<FeuilleDuMetier repere={sonde[2]} libellePrix="Prix selon vos réponses" onCommander={onCommander} />);

    fireEvent.press(ecran.getByTestId('feuille-commander'));

    expect(onCommander).toHaveBeenCalledTimes(1);
    expect(ecran.getByTestId('feuille-commander').props.children).toBe('Commander');
  });
});
```

- [ ] **Step 2: Lancer et voir échouer**

Run: `npx jest __tests__/catalogue/RepereDeSonde.test.tsx __tests__/catalogue/FeuilleDuMetier.test.tsx`
Expected: FAIL — modules introuvables.

- [ ] **Step 3: Écrire `RepereDeSonde.tsx`**

```tsx
import React, { useEffect } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';
import { GlassSurface } from '@/ui';
import { animation, radius, spacing, typography } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';
import { HAUTEUR_DE_REPERE } from '@/catalogue';
import type { RepereDeSonde as Repere } from '@/catalogue';

/** La largeur de l'axe central : le pointillé de `LigneDeSonde` passe en son milieu. */
const AXE = spacing.xl;

type Moitie = 'gauche' | 'droite';

export interface RepereDeSondeProps {
  repere: Repere;
  choisi: boolean;
  libellePrix: string;
  mouvementReduit: boolean;
  onChoisir: () => void;
}

/**
 * UN REPÈRE DE LA SONDE : le nœud sur la ligne, le fil, la case de verre.
 *
 * Les cases d'un secteur restent du même côté et le secteur suivant passe de l'autre ; son nom se
 * pose en face, sur la moitié libre. Toute la rangée est la cible tactile : 88 pt de haut.
 */
export function RepereDeSonde({ repere, choisi, libellePrix, mouvementReduit, onChoisir }: RepereDeSondeProps) {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const { metier, cote, etiquetteSecteur } = repere;

  const moitieDeLaCase: Moitie = cote;
  const moitieDeLEtiquette: Moitie = cote === 'droite' ? 'gauche' : 'droite';

  // Le halo respire autour du repère choisi — et se tait quand l'appareil réduit les mouvements.
  const echelle = useSharedValue(1);

  useEffect(() => {
    echelle.value = choisi && !mouvementReduit
      ? withRepeat(withTiming(1.8, { duration: animation.duration.slow * 3 }), -1, true)
      : 1;
  }, [choisi, mouvementReduit, echelle]);

  const halo = useAnimatedStyle(() => ({ transform: [{ scale: echelle.value }] }));

  const contreLAxe = (moitie: Moitie) => (moitie === 'droite' ? styles.contreAxeDroit : styles.contreAxeGauche);

  const laCase = (
    <View style={contreLAxe(moitieDeLaCase)}>
      {moitieDeLaCase === 'droite' ? <View style={styles.fil} /> : null}
      <GlassSurface
        strong={choisi}
        radius={radius.md}
        style={[styles.case, choisi && styles.caseChoisie]}
        testID={`case-${metier.slug}`}
      >
        <Text style={styles.nom} numberOfLines={2}>{metier.name}</Text>
        <Text
          style={[styles.prix, metier.floor_price_cents !== null ? styles.prixConnu : styles.prixInconnu]}
          numberOfLines={1}
        >
          {libellePrix}
        </Text>
      </GlassSurface>
      {moitieDeLaCase === 'gauche' ? <View style={styles.fil} /> : null}
    </View>
  );

  const lEtiquette = etiquetteSecteur ? (
    <View style={contreLAxe(moitieDeLEtiquette)}>
      <Text style={styles.etiquette} numberOfLines={1} testID={`etiquette-${metier.slug}`}>
        {etiquetteSecteur}
      </Text>
    </View>
  ) : null;

  const contenu = (moitie: Moitie) => (moitie === moitieDeLaCase ? laCase : lEtiquette);

  return (
    <Pressable
      onPress={onChoisir}
      accessibilityRole="button"
      accessibilityState={{ selected: choisi }}
      accessibilityLabel={tr('catalogue.repere_accessible', { metier: metier.name, prix: libellePrix })}
      style={styles.rangee}
      testID={`repere-${metier.slug}`}
    >
      <View style={styles.moitie} testID={`moitie-gauche-${metier.slug}`}>{contenu('gauche')}</View>

      <View style={styles.axe} pointerEvents="none">
        {choisi ? <Animated.View style={[styles.halo, halo]} /> : null}
        <View style={choisi ? styles.noeudChoisi : styles.noeud} />
      </View>

      <View style={styles.moitie} testID={`moitie-droite-${metier.slug}`}>{contenu('droite')}</View>
    </Pressable>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  rangee: { height: HAUTEUR_DE_REPERE, flexDirection: 'row', alignItems: 'center' },
  moitie: { flex: 1, height: '100%', justifyContent: 'center' },
  contreAxeDroit: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-start', paddingRight: spacing.md },
  contreAxeGauche: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', paddingLeft: spacing.md },
  axe: { width: AXE, height: '100%', alignItems: 'center', justifyContent: 'center' },
  noeud: {
    width: 12, height: 12, borderRadius: radius.pill,
    borderWidth: 1.5, borderColor: t.action, backgroundColor: t.glassStrong,
  },
  noeudChoisi: { width: 16, height: 16, borderRadius: radius.pill, backgroundColor: t.action },
  halo: { position: 'absolute', width: 16, height: 16, borderRadius: radius.pill, backgroundColor: t.glow },
  fil: { width: spacing.sm + spacing.xs, height: 2, backgroundColor: t.action, opacity: 0.75 },
  case: { flexShrink: 1, paddingVertical: spacing.sm, paddingHorizontal: spacing.sm + spacing.xs, gap: spacing['2xs'] },
  caseChoisie: { borderColor: t.action, borderWidth: 1 },
  nom: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold, color: t.textOnGlass },
  prix: { fontSize: typography.fontSize.xs },
  prixConnu: { color: t.argent, fontWeight: typography.fontWeight.semibold },
  prixInconnu: { color: t.mutedOnGlass },
  etiquette: {
    maxWidth: '100%',
    fontSize: typography.fontSize.xs,
    color: t.textOnGlass,
    backgroundColor: t.glassStrong,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs - 1,
    borderRadius: radius.pill,
    overflow: 'hidden',
  },
});
```

- [ ] **Step 4: Écrire `FeuilleDuMetier.tsx`**

```tsx
import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Button, GlassSurface, Icon } from '@/ui';
import { radius, spacing, typography } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';
import { iconeDuMetier } from '@/catalogue';
import type { RepereDeSonde } from '@/catalogue';

export interface FeuilleDuMetierProps {
  repere: RepereDeSonde;
  libellePrix: string;
  onCommander: () => void;
}

/**
 * LA FEUILLE DU MÉTIER CHOISI — fixe, sans geste de glissement.
 *
 * Elle ne montre qu'un prix : le plancher que le moteur de commande utilisera. Un catalogue de
 * « services » avec ses propres prix contredirait le devis.
 */
export function FeuilleDuMetier({ repere, libellePrix, onCommander }: FeuilleDuMetierProps) {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const insets = useSafeAreaInsets();
  const styles = stylesFor(theme);
  const { metier, secteur, rang, total } = repere;

  return (
    <GlassSurface
      strong
      radius={radius.lg}
      style={[styles.feuille, { paddingBottom: spacing.md + insets.bottom }]}
      testID="feuille-du-metier"
    >
      <View style={styles.entete}>
        <View style={styles.pastilleIcone}>
          <Icon name={iconeDuMetier(metier.icon)} size={22} color={theme.action} />
        </View>
        <View style={styles.textes}>
          <Text style={styles.titre} numberOfLines={1} testID="feuille-titre">{metier.name}</Text>
          <Text style={styles.position} numberOfLines={1} testID="feuille-position">
            {tr('catalogue.position', { secteur: secteur.name, rang, total })}
          </Text>
        </View>
      </View>

      {metier.short_description ? (
        <Text style={styles.description} numberOfLines={2}>{metier.short_description}</Text>
      ) : null}

      <Text
        style={[styles.prix, metier.floor_price_cents !== null ? styles.prixConnu : styles.prixInconnu]}
        testID="feuille-prix"
      >
        {libellePrix}
      </Text>

      <Button label={tr('catalogue.commander')} onPress={onCommander} fullWidth size="lg" testID="feuille-commander" />
    </GlassSurface>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  feuille: {
    borderBottomLeftRadius: 0,
    borderBottomRightRadius: 0,
    paddingTop: spacing.md,
    paddingHorizontal: spacing.md,
    gap: spacing.sm,
  },
  entete: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm + spacing.xs },
  pastilleIcone: {
    width: 40, height: 40, borderRadius: radius.md,
    alignItems: 'center', justifyContent: 'center', backgroundColor: t.tint.brand,
  },
  textes: { flex: 1 },
  titre: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, color: t.textOnGlass },
  position: { fontSize: typography.fontSize.xs, color: t.mutedOnGlass },
  description: { fontSize: typography.fontSize.xs, color: t.mutedOnGlass },
  prix: { fontSize: typography.fontSize.base },
  prixConnu: { color: t.argent, fontWeight: typography.fontWeight.semibold },
  prixInconnu: { color: t.mutedOnGlass },
});
```

- [ ] **Step 5: Relancer et voir passer**

Run: `npx jest __tests__/catalogue/RepereDeSonde.test.tsx __tests__/catalogue/FeuilleDuMetier.test.tsx`
Expected: PASS.

- [ ] **Step 6: Commit** (seulement si les commits ont été demandés)

```bash
git add mobile/client/src/screens/catalogue/RepereDeSonde.tsx mobile/client/src/screens/catalogue/FeuilleDuMetier.tsx mobile/client/__tests__/catalogue/RepereDeSonde.test.tsx mobile/client/__tests__/catalogue/FeuilleDuMetier.test.tsx
git commit -m "feat(mobile): le repere de sonde change de cote par secteur, la feuille dit le vrai plancher"
```

---

### Task 7: `LigneDeSonde` et `CatalogueScreen`

**Files:**
- Modify: `mobile/client/src/navigation/types.ts` — ajouter la route après `Modules: undefined;`
- Create: `mobile/client/src/screens/catalogue/LigneDeSonde.tsx`
- Create: `mobile/client/src/screens/catalogue/CatalogueScreen.tsx`
- Test: `mobile/client/__tests__/catalogue/CatalogueScreen.test.tsx`

**Interfaces:**
- Consumes: Tasks 4, 5, 6 ; `Screen`, `GlassSurface`, `Button`, `Skeleton`, `ErrorState`, `useReducedMotion` de `@/ui` ; `DEVISE_PAR_DEFAUT` de `@/format/money`.
- Produces:
  - `RootStackParamList.Catalogue: { mode: 'asap' | 'scheduled' }`
  - `LigneDeSonde(props: { reperes: RepereDeSonde[]; index: number; onIndex: (index: number) => void; libelleDuRepere: (repere: RepereDeSonde) => string; mouvementReduit: boolean })`, testID `ligne-de-sonde`
  - `CatalogueScreen()` (lit `route.params.mode`), testIDs `catalogue-screen`, `catalogue-chargement`, `catalogue-vide`, `catalogue-vers-rendez-vous`

Mesuré avant d'écrire ce plan : sous jest-expo, la ref d'une `ScrollView` expose bien `scrollTo`, et `jest.spyOn(ScrollView.prototype, 'scrollTo')` capture l'appel et ses arguments.

- [ ] **Step 1: Écrire le test qui échoue**

```tsx
import React from 'react';
import { ScrollView } from 'react-native';
import { fireEvent, render } from '@testing-library/react-native';

const mockNavigate = jest.fn();
const mockReplace = jest.fn();
let mockMode: 'asap' | 'scheduled' = 'asap';
let mockResultat: Record<string, unknown> = {};
let mockReduit = false;

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate, replace: mockReplace }),
  useRoute: () => ({ params: { mode: mockMode } }),
}));

jest.mock('@/catalogue', () => ({
  ...jest.requireActual('@/catalogue'),
  useCatalogue: () => mockResultat,
}));

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    Screen: ({ children, testID }: any) => <View testID={testID}>{children}</View>,
    GlassSurface: ({ children, testID, style }: any) => <View testID={testID} style={style}>{children}</View>,
    Icon: ({ name }: any) => <Text>{name}</Text>,
    Button: ({ label, onPress, testID }: any) => <Text onPress={onPress} testID={testID}>{label}</Text>,
    Skeleton: () => <View testID="squelette" />,
    ErrorState: ({ message, onRetry }: any) => <Text onPress={onRetry} testID="erreur">{message}</Text>,
    useReducedMotion: () => mockReduit,
  };
});

import { CatalogueScreen } from '@/screens/catalogue/CatalogueScreen';

const metier = (slug: string, name: string, floor_price_cents: number | null) => ({
  slug, name, icon: null, short_description: null, floor_price_cents, hourly: false,
});

const catalogue = (currency = 'EUR', sectors?: unknown[]) => ({
  mode: mockMode,
  zone_known: true,
  currency,
  sectors: sectors ?? [
    { slug: 'batiment', name: 'Bâtiment', icon: 'hammer', trades: [
      metier('peinture', 'Peinture', 12000),
      metier('plomberie', 'Plomberie', 8500),
      metier('electricite', 'Électricité', 9000),
      metier('toiture', 'Toiture', null),
    ] },
    { slug: 'nettoyage', name: 'Nettoyage', icon: 'sparkles', trades: [metier('vitres', 'Vitres', 6000)] },
  ],
});

const refetch = jest.fn();

const defiler = (y: number, ecran: ReturnType<typeof render>) =>
  fireEvent.scroll(ecran.getByTestId('ligne-de-sonde'), {
    nativeEvent: {
      contentOffset: { x: 0, y },
      contentSize: { width: 390, height: 1000 },
      layoutMeasurement: { width: 390, height: 484 },
    },
  });

beforeEach(() => {
  jest.clearAllMocks();
  mockMode = 'asap';
  mockReduit = false;
  mockResultat = { data: catalogue(), isLoading: false, isError: false, refetch };
});

afterEach(() => jest.restoreAllMocks());

describe('CatalogueScreen', () => {
  it('pose tous les métiers sur la ligne et ouvre la feuille sur le premier', () => {
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getAllByTestId(/^repere-/)).toHaveLength(5);
    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Peinture');
    expect(ecran.getByTestId('feuille-position').props.children).toBe('Bâtiment · 1 sur 5');
  });

  it('défiler de trois crans choisit le quatrième métier', () => {
    const ecran = render(<CatalogueScreen />);

    defiler(3 * 88, ecran);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Toiture');
    expect(ecran.getByTestId('feuille-prix').props.children).toBe('Prix selon vos réponses');
  });

  it('témoin : un petit mouvement garde le métier choisi', () => {
    const ecran = render(<CatalogueScreen />);

    defiler(30, ecran);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Peinture');
  });

  it('toucher un repère le choisit et le ramène au centre', () => {
    const recentrer = jest.spyOn(ScrollView.prototype as unknown as { scrollTo: (o: unknown) => void }, 'scrollTo');
    const ecran = render(<CatalogueScreen />);

    fireEvent.press(ecran.getByTestId('repere-electricite'));

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Électricité');
    expect(recentrer).toHaveBeenCalledWith({ y: 2 * 88, animated: true });
  });

  it('en mouvement réduit, le recentrage ne s’anime pas', () => {
    mockReduit = true;
    const recentrer = jest.spyOn(ScrollView.prototype as unknown as { scrollTo: (o: unknown) => void }, 'scrollTo');
    const ecran = render(<CatalogueScreen />);

    fireEvent.press(ecran.getByTestId('repere-electricite'));

    expect(recentrer).toHaveBeenCalledWith({ y: 2 * 88, animated: false });
  });

  it('Commander ouvre le moteur de commande sur le métier choisi, dans le mode', () => {
    const ecran = render(<CatalogueScreen />);

    fireEvent.press(ecran.getByTestId('repere-plomberie'));
    fireEvent.press(ecran.getByTestId('feuille-commander'));

    expect(mockNavigate).toHaveBeenCalledWith('EmbeddedModule', {
      path: '/commander/batiment/plomberie?mode=asap',
      title: 'Plomberie',
    });
  });

  it('le prix suit la devise du catalogue', () => {
    mockResultat = { data: catalogue('MAD'), isLoading: false, isError: false, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByTestId('feuille-prix').props.children).toContain('MAD');
  });

  it('en immédiat, un catalogue vide propose de prendre rendez-vous', () => {
    mockResultat = { data: catalogue('EUR', []), isLoading: false, isError: false, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByText('Aucun métier n’accepte l’intervention immédiate pour votre adresse.')).toBeTruthy();
    fireEvent.press(ecran.getByTestId('catalogue-vers-rendez-vous'));
    expect(mockReplace).toHaveBeenCalledWith('Catalogue', { mode: 'scheduled' });
  });

  it('témoin : en rendez-vous, un catalogue vide ne renvoie vers aucun autre mode', () => {
    mockMode = 'scheduled';
    mockResultat = { data: catalogue('EUR', []), isLoading: false, isError: false, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByText('Aucun métier n’est proposé pour le moment.')).toBeTruthy();
    expect(ecran.queryByTestId('catalogue-vers-rendez-vous')).toBeNull();
  });

  it('montre des squelettes pendant le chargement', () => {
    mockResultat = { data: undefined, isLoading: true, isError: false, refetch };

    expect(render(<CatalogueScreen />).getAllByTestId('squelette').length).toBeGreaterThan(0);
  });

  it('en erreur, dit que le catalogue n’a pas pu être chargé et permet de réessayer', () => {
    mockResultat = { data: undefined, isLoading: false, isError: true, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByTestId('erreur').props.children).toBe('Le catalogue n’a pas pu être chargé.');
    fireEvent.press(ecran.getByTestId('erreur'));
    expect(refetch).toHaveBeenCalledTimes(1);
  });
});
```

- [ ] **Step 2: Lancer et voir échouer**

Run: `npx jest __tests__/catalogue/CatalogueScreen.test.tsx`
Expected: FAIL — module `@/screens/catalogue/CatalogueScreen` introuvable.

- [ ] **Step 3: Déclarer la route dans `navigation/types.ts`**

Après `Modules: undefined;` :

```ts
  /**
   * Le catalogue natif — ouvert par « Intervention immédiate » et « Prendre rendez-vous ».
   * « Plusieurs services » n'y passe pas : il reste servi par la vue web.
   */
  Catalogue: { mode: 'asap' | 'scheduled' };
```

- [ ] **Step 4: Écrire `LigneDeSonde.tsx`**

```tsx
import React, { useRef, useState } from 'react';
import { ScrollView, StyleSheet, View, type NativeScrollEvent, type NativeSyntheticEvent } from 'react-native';
import { spacing } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { HAUTEUR_DE_REPERE, decalageDeIndex, indexDepuisDecalage } from '@/catalogue';
import type { RepereDeSonde as Repere } from '@/catalogue';
import { RepereDeSonde } from './RepereDeSonde';

const TIRET = spacing.xs;
const INTERVALLE = spacing.xs + 1;

export interface LigneDeSondeProps {
  reperes: Repere[];
  index: number;
  onIndex: (index: number) => void;
  libelleDuRepere: (repere: Repere) => string;
  mouvementReduit: boolean;
}

/**
 * LA COLONNE DE REPÈRES — la seule chose qui défile.
 *
 * L'aimantation se fait tous les `HAUTEUR_DE_REPERE` points. Les marges haute et basse valent la
 * moitié de la hauteur visible moins un repère : le premier et le dernier peuvent ainsi se poser
 * au centre, là où la lueur marque le niveau de l'eau.
 */
export function LigneDeSonde({ reperes, index, onIndex, libelleDuRepere, mouvementReduit }: LigneDeSondeProps) {
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const defilement = useRef<ScrollView>(null);
  const [hauteur, setHauteur] = useState(0);

  const marge = Math.max(0, (hauteur - HAUTEUR_DE_REPERE) / 2);
  const longueurDuTrait = Math.max(0, (reperes.length - 1) * HAUTEUR_DE_REPERE);

  const suivre = (evenement: NativeSyntheticEvent<NativeScrollEvent>) => {
    const suivant = indexDepuisDecalage(evenement.nativeEvent.contentOffset.y, reperes.length);

    if (suivant !== index) {
      onIndex(suivant);
    }
  };

  const choisir = (suivant: number) => {
    defilement.current?.scrollTo({ y: decalageDeIndex(suivant), animated: !mouvementReduit });

    if (suivant !== index) {
      onIndex(suivant);
    }
  };

  return (
    <View style={styles.cadre} onLayout={e => setHauteur(e.nativeEvent.layout.height)}>
      <View pointerEvents="none" style={[styles.lentille, { top: marge }]} />

      <ScrollView
        ref={defilement}
        testID="ligne-de-sonde"
        showsVerticalScrollIndicator={false}
        snapToInterval={HAUTEUR_DE_REPERE}
        decelerationRate="fast"
        scrollEventThrottle={16}
        onScroll={suivre}
        onMomentumScrollEnd={suivre}
        contentContainerStyle={{ paddingVertical: marge }}
      >
        <View
          pointerEvents="none"
          style={[styles.trait, { top: marge + HAUTEUR_DE_REPERE / 2, height: longueurDuTrait }]}
        >
          {Array.from({ length: Math.ceil(longueurDuTrait / (TIRET + INTERVALLE)) }).map((_, k) => (
            <View key={k} style={styles.tiret} />
          ))}
        </View>

        {reperes.map((repere, i) => (
          <RepereDeSonde
            key={repere.cle}
            repere={repere}
            choisi={i === index}
            libellePrix={libelleDuRepere(repere)}
            mouvementReduit={mouvementReduit}
            onChoisir={() => choisir(i)}
          />
        ))}
      </ScrollView>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  cadre: { flex: 1 },
  lentille: { position: 'absolute', left: 0, right: 0, height: HAUTEUR_DE_REPERE, backgroundColor: t.glow },
  trait: { position: 'absolute', left: '50%', marginLeft: -1, width: 2, overflow: 'hidden' },
  tiret: { width: 2, height: TIRET, marginBottom: INTERVALLE, backgroundColor: t.action, opacity: 0.75 },
});
```

- [ ] **Step 5: Écrire `CatalogueScreen.tsx`**

```tsx
import React, { useMemo, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useNavigation, useRoute, type RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, ErrorState, GlassSurface, Screen, Skeleton, useReducedMotion } from '@/ui';
import { radius, spacing, typography } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';
import { DEVISE_PAR_DEFAUT } from '@/format/money';
import { HAUTEUR_DE_REPERE, cheminDeCommande, construireLaSonde, useCatalogue } from '@/catalogue';
import type { RepereDeSonde } from '@/catalogue';
import type { RootStackParamList } from '@/navigation/types';
import { FeuilleDuMetier } from './FeuilleDuMetier';
import { LigneDeSonde } from './LigneDeSonde';
import { libelleDuPrix } from './libelleDuPrix';

/**
 * LE CATALOGUE NATIF — la ligne de sonde sur l'iceberg.
 *
 * `Screen toile` : aucune plaque de fond, l'iceberg reste visible ; chaque texte est porté par du
 * verre (cases, étiquettes, feuille). La commande elle-même reste celle du web : le bouton ouvre le
 * moteur sur le métier choisi, qui pose ses questions et calcule le devis.
 */
export function CatalogueScreen() {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { params } = useRoute<RouteProp<RootStackParamList, 'Catalogue'>>();
  const mode = params.mode;
  const mouvementReduit = useReducedMotion();

  const { data, isLoading, isError, refetch } = useCatalogue(mode);
  const reperes = useMemo(() => construireLaSonde(data?.sectors ?? []), [data]);
  const [index, setIndex] = useState(0);

  if (isLoading) {
    return (
      <Screen toile testID="catalogue-chargement">
        <View style={styles.squelettes}>
          {[0, 1, 2, 3].map(k => (
            <Skeleton key={k} width="100%" height={HAUTEUR_DE_REPERE - spacing.md} borderRadius={radius.md} />
          ))}
        </View>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen toile>
        <ErrorState message={tr('catalogue.chargement_impossible')} onRetry={() => void refetch()} />
      </Screen>
    );
  }

  const choisi = reperes[Math.min(index, reperes.length - 1)];

  if (!choisi) {
    return (
      <Screen toile testID="catalogue-vide">
        <GlassSurface strong radius={radius.lg} style={styles.vide}>
          <Text style={styles.videTexte}>
            {mode === 'asap' ? tr('catalogue.aucun_metier_immediat') : tr('catalogue.aucun_metier')}
          </Text>
          {mode === 'asap' ? (
            <Button
              label={tr('catalogue.prendre_rendez_vous_plutot')}
              onPress={() => navigation.replace('Catalogue', { mode: 'scheduled' })}
              variant="secondary"
              testID="catalogue-vers-rendez-vous"
            />
          ) : null}
        </GlassSurface>
      </Screen>
    );
  }

  const devise = data?.currency ?? DEVISE_PAR_DEFAUT;
  const libelleDuRepere = (repere: RepereDeSonde) => libelleDuPrix(repere.metier, devise, tr);

  return (
    <Screen toile testID="catalogue-screen" edges={['left', 'right']} style={styles.contenu}>
      <View style={styles.colonne}>
        <LigneDeSonde
          reperes={reperes}
          index={index}
          onIndex={setIndex}
          libelleDuRepere={libelleDuRepere}
          mouvementReduit={mouvementReduit}
        />
      </View>

      <FeuilleDuMetier
        repere={choisi}
        libellePrix={libelleDuRepere(choisi)}
        onCommander={() =>
          navigation.navigate('EmbeddedModule', { path: cheminDeCommande(choisi, mode), title: choisi.metier.name })
        }
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  // La feuille va bord à bord : on retire la marge latérale que `Screen` pose par défaut.
  contenu: { paddingHorizontal: 0 },
  colonne: { flex: 1 },
  squelettes: { paddingTop: spacing.md, gap: spacing.sm },
  vide: { marginTop: spacing.lg, padding: spacing.lg, gap: spacing.md, alignItems: 'center' },
  videTexte: { fontSize: typography.fontSize.sm, color: t.textOnGlass, textAlign: 'center' },
});
```

- [ ] **Step 6: Relancer, puis tout le dossier du catalogue et les garde-fous i18n**

Run: `npx jest __tests__/catalogue ../shared/src/i18n/__tests__`
Expected: PASS — les 10 clés `catalogue.*` sont désormais toutes employées.

- [ ] **Step 7: Vérifier les types**

Run: `npm run typecheck`
Expected: aucune erreur.

- [ ] **Step 8: Commit** (seulement si les commits ont été demandés)

```bash
git add mobile/client/src/navigation/types.ts mobile/client/src/screens/catalogue/LigneDeSonde.tsx mobile/client/src/screens/catalogue/CatalogueScreen.tsx mobile/client/__tests__/catalogue/CatalogueScreen.test.tsx
git commit -m "feat(mobile): la ligne de sonde defile seule et la feuille suit le metier centre"
```

---

### Task 8: Brancher la route et la feuille d'accueil

**Files:**
- Modify: `mobile/client/src/navigation/RootNavigator.tsx` — import (à côté de `import { ModulesRoute } ...`, ≈ ligne 60) et écran dans la pile personnelle, juste après son `Modules`
- Modify: `mobile/client/src/screens/components/HomeActionsSheet.tsx` — cartes `asap` et `scheduled` de `BOOKING_MODES`
- Modify: `mobile/client/__tests__/screens/HomeScreen.interaction.test.tsx` — trois attentes et l'en-tête
- Create: `mobile/client/src/navigation/__tests__/LeCatalogueEstMonte.test.ts`

**Interfaces:**
- Consumes: `CatalogueScreen` et `RootStackParamList.Catalogue` (Task 7).
- Produces: « Intervention immédiate » → `Catalogue { mode: 'asap' }` ; « Prendre rendez-vous » → `Catalogue { mode: 'scheduled' }` ; « Plusieurs services » inchangé.

`UneCibleViseeDoitEtreMontee.test.ts` ne garde que l'espace société cliente : la pile personnelle n'a pas de garde. Le nouveau test en pose une, limitée à ce que cette tâche ajoute.

- [ ] **Step 1: Mettre les tests à la nouvelle destination (ils échouent)**

Dans `HomeScreen.interaction.test.tsx`, remplacer la ligne d'en-tête :

```tsx
 *  - Tap "Intervention immédiate" -> navigate('EmbeddedModule', /commander?mode=asap)
 *    (les trois cartes ouvrent le MÊME parcours ; l'ancien assistant natif n'est plus joignable)
```

par :

```tsx
 *  - Tap "Intervention immédiate" -> navigate('Catalogue', { mode: 'asap' })
 *    (immédiat et rendez-vous passent par le catalogue natif ; « Plusieurs services » reste web)
```

Remplacer le bloc des deux premiers tests de mode (commentaire compris) :

```tsx
  /**
   * Immédiat et rendez-vous ouvrent le CATALOGUE NATIF, avec leur mode.
   *
   * Le catalogue ne montre que ce que le moteur de commande accepte dans ce mode, puis ouvre le
   * moteur sur le métier choisi : le mode voyage jusqu'à la commande. Sans lui, le client
   * demanderait « immédiat » et devrait le redemander.
   */
  it('le mode immédiat ouvre le catalogue natif en immédiat', () => {
    render(<HomeScreen />);
    fireEvent.press(screen.getByTestId('booking-mode-asap'));
    expect(mockNavigate).toHaveBeenCalledWith('Catalogue', { mode: 'asap' });
  });

  it('le mode rendez-vous ouvre le catalogue natif en rendez-vous', () => {
    render(<HomeScreen />);
    fireEvent.press(screen.getByTestId('booking-mode-scheduled'));
    expect(mockNavigate).toHaveBeenCalledWith('Catalogue', { mode: 'scheduled' });
  });
```

Le test `le mode multi-services ouvre la page dédiée` ne change PAS : c'est le témoin que la troisième carte reste web.

Dans le test `shows welcome card and a single booking entry point when no bookings`, remplacer l'attente finale par :

```tsx
    fireEvent.press(screen.getByTestId('booking-mode-scheduled'));
    expect(mockNavigate).toHaveBeenCalledWith('Catalogue', { mode: 'scheduled' });
```

Créer `mobile/client/src/navigation/__tests__/LeCatalogueEstMonte.test.ts` :

```ts
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const RACINE = join(__dirname, '..', '..');
const lire = (chemin: string): string => readFileSync(join(RACINE, chemin), 'utf8');

/** La pile personnelle commence à ses onglets : tout ce qui suit y est monté. */
const racine = lire('navigation/RootNavigator.tsx');
const pilePersonnelle = racine.slice(racine.indexOf('component={TabNavigator}'));
const montees = new Set([...pilePersonnelle.matchAll(/name="([A-Za-z]+)"/g)].map(m => m[1]));

describe('le catalogue natif est joignable depuis la pile personnelle', () => {
  it('témoin : le découpage voit la pile personnelle', () => {
    expect(racine.indexOf('component={TabNavigator}')).toBeGreaterThan(-1);
    expect(montees.has('Modules')).toBe(true);
    expect(montees.has('EmbeddedModule')).toBe(true);
  });

  it('la route Catalogue y est montée', () => {
    expect(montees.has('Catalogue')).toBe(true);
  });

  it("la feuille d'accueil y mène pour l'immédiat et le rendez-vous", () => {
    const feuille = lire('screens/components/HomeActionsSheet.tsx');
    expect(feuille).toContain("go('Catalogue', { mode: 'asap' })");
    expect(feuille).toContain("go('Catalogue', { mode: 'scheduled' })");
  });

  it('le catalogue ne vise que des routes montées', () => {
    const ecran = lire('screens/catalogue/CatalogueScreen.tsx');
    const cibles = [...ecran.matchAll(/\.(?:navigate|replace)\(\s*'([A-Za-z]+)'/g)].map(m => m[1]);

    expect(cibles.length).toBeGreaterThan(0);
    expect(cibles.filter(c => !montees.has(c))).toEqual([]);
  });
});
```

- [ ] **Step 2: Lancer et voir échouer**

Run: `npx jest __tests__/screens/HomeScreen.interaction.test.tsx src/navigation/__tests__`
Expected: FAIL — les cartes naviguent encore vers `EmbeddedModule` ; `Catalogue` n'est pas monté.

- [ ] **Step 3: Monter la route**

Import dans `RootNavigator.tsx`, sous `import { ModulesRoute } from '@/screens/ModulesRoute';` :

```tsx
import { CatalogueScreen } from '@/screens/catalogue/CatalogueScreen';
```

Dans la pile personnelle, juste après son écran `Modules` (celui suivi du commentaire « L'assistant de réservation en cinq étapes N'EST PLUS MONTÉ ») :

```tsx
            <Stack.Screen
              name="Catalogue"
              component={CatalogueScreen}
              options={({ route }) => ({
                headerShown: true,
                title: route.params.mode === 'asap'
                  ? tr('home_actions.intervention_immediate')
                  : tr('home_actions.prendre_rendez_vous'),
              })}
            />
```

- [ ] **Step 4: Changer la destination des deux cartes**

Dans `HomeActionsSheet.tsx`, remplacer le paragraphe du commentaire au-dessus de `BookingMode` :

```ts
 * Elles ouvrent toutes LE MÊME parcours — le moteur de commande, servi par la vue embarquée —
 * avec leur intention dans l'URL.
```

par :

```ts
 * Immédiat et rendez-vous passent d'abord par le CATALOGUE NATIF, qui ne montre que ce que le
 * moteur de commande accepte dans ce mode, puis ouvre ce moteur sur le métier choisi. « Plusieurs
 * services » ouvre directement le moteur, servi par la vue embarquée.
```

Carte `asap` — remplacer :

```ts
    navigate: go => go('EmbeddedModule', {
      path: '/commander?mode=asap',
      title: traduireMaintenant('home_actions.intervention_immediate'),
    }),
```

par :

```ts
    navigate: go => go('Catalogue', { mode: 'asap' }),
```

Carte `scheduled` — remplacer :

```ts
    navigate: go => go('EmbeddedModule', {
      path: '/commander?mode=scheduled',
      title: traduireMaintenant('home_actions.prendre_rendez_vous'),
    }),
```

par :

```ts
    navigate: go => go('Catalogue', { mode: 'scheduled' }),
```

La carte `bundle` ne change pas. `traduireMaintenant` reste importé : les titres et indications des cartes l'emploient toujours.

- [ ] **Step 5: Relancer et voir passer**

Run: `npx jest __tests__/screens src/navigation/__tests__ __tests__/catalogue`
Expected: PASS — y compris `le mode multi-services ouvre la page dédiée` (témoin) et `UneCibleViseeDoitEtreMontee`.

- [ ] **Step 6: Commit** (seulement si les commits ont été demandés)

```bash
git add mobile/client/src/navigation/RootNavigator.tsx mobile/client/src/screens/components/HomeActionsSheet.tsx mobile/client/__tests__/screens/HomeScreen.interaction.test.tsx mobile/client/src/navigation/__tests__/LeCatalogueEstMonte.test.ts
git commit -m "feat(mobile): l'immediat et le rendez-vous ouvrent le catalogue natif"
```

---

### Task 9: Vérification complète, appareil et revue croisée

**Files:** aucun fichier nouveau — cette tâche ne modifie rien ; si une vérification échoue, revenir à la tâche concernée.

- [ ] **Step 1: Suites serveur**

Run: `cd /c/Users/mmdar/Desktop/code/work/brio && $PHP artisan test tests/Feature/OrderEngine tests/Feature/Api tests/Feature/Dispatch tests/Feature/I18n`
Expected: PASS.

Run: `$PHP artisan test --parallel --no-coverage`
Expected: PASS, à l'exception éventuelle des échecs déjà présents sur `main` avant ce travail (la CI y signalait 11 échecs, dont `UploadImageSvgXssTest` et `DateLisiblePourLeClientTest`). Tout échec se rapporte avec sa sortie ; un échec préexistant se prouve en relançant le test sur `main` sans ces changements.

- [ ] **Step 2: Suites mobiles et types**

Run: `cd /c/Users/mmdar/Desktop/code/work/brio/mobile && npm run test:security`
Expected: PASS.

Run: `cd client && npx jest` puis `cd ../provider && npx jest`
Expected: PASS dans les deux applications (le prestataire lit les mêmes catalogues de traduction et le même thème).

Run: `npm --prefix /c/Users/mmdar/Desktop/code/work/brio/mobile/client run typecheck` puis `npm --prefix /c/Users/mmdar/Desktop/code/work/brio/mobile/provider run typecheck`
Expected: aucune erreur.

- [ ] **Step 3: Vérifier sur l'émulateur Android (`Pixel_10_Pro_XL`)**

Les tests jest ne simulent ni l'inertie ni l'aimantation. Serveur local sur `0.0.0.0:8000`, Metro relancé avec `npx expo start --clear` dans `mobile/client`, compte client connecté.

1. Accueil → « Réserver un service » → « Intervention immédiate » : l'en-tête dit « Intervention immédiate » ; seuls les métiers qui acceptent l'immédiat apparaissent (avec la base locale actuelle : Plomberie, Électricité, Nettoyage à domicile, Course).
2. Faire glisser la colonne : elle s'aimante sur chaque repère ; l'iceberg, l'en-tête et la feuille ne bougent pas ; la feuille suit le repère centré.
3. Les cases changent de côté à chaque secteur, l'étiquette du secteur est en face.
4. Toucher un repère éloigné : il revient au centre en douceur.
5. « Commander » : la vue web s'ouvre sur `/commander/{secteur}/{métier}?mode=asap`, métier déjà choisi.
6. Retour → « Prendre rendez-vous » : les 16 métiers, en-tête « Prendre rendez-vous ».
7. Profil → Apparence → Clair : relire les cases, les étiquettes et les prix sur l'iceberg émergé.
8. Paramètres Android → Accessibilité → « Supprimer les animations » : halo immobile, recentrage sans animation.

Capturer chaque étape (`adb exec-out screencap -p`) et joindre les captures au compte rendu.

- [ ] **Step 4: Revue croisée avant livraison**

Confier une relecture à froid (relecteur + auditeur sécurité) du diff complet : parité web/API, absence de N+1, garde de la route, textes et couleurs du système de design, accessibilité. Corriger ce qui est retenu, relancer les suites touchées, puis rendre le verdict du chef d'équipe.

---

## Écarts assumés par rapport à la spec

- `CatalogueServable::prixPlancherCents` reçoit la ligne de zone déjà chargée (`?TradeZonePricing`) plutôt qu'un identifiant de zone : sans cela, l'API émettrait une requête par métier.
- Clé `catalogue.aucun_metier` ajoutée : le message de l'immédiat serait faux pour un catalogue vide en rendez-vous.
- Jeton de thème `argent` ajouté : aucun jeton existant n'offrait un ambre lisible sur le verre clair, et le thème réserve l'ambre à l'argent.
- Le test de caractérisation d'`OrderJourney` existe déjà (`TroisModesWebTest`) : la Task 2 s'appuie sur lui au lieu d'en écrire un doublon.
