<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Models\Country;
use App\Models\ServiceZone;
use App\Services\Catalog\GeoGuard;
use App\Support\Livewire\Concerns\Admin\ManagesZonesData;
use App\Support\Livewire\Concerns\Admin\PerformsZoneManagementActions;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Le deuxième niveau : les zones d'un pays.
 *
 * IL RÉUTILISE LES TRAITS DE `GestionZones` et n'en réécrit aucun. Cet écran-là fait déjà l'édition
 * des zones, ses filtres, sa réservabilité et sa visibilité ; un second jeu d'actions divergerait,
 * et plus personne ne saurait lequel fait foi.
 *
 * LE CLOISONNEMENT PASSE PAR LA REQUÊTE. `zoneBaseQuery()` est redéfinie ici, et comme
 * `selectZone()` s'en sert pour son `findOrFail`, les ACTIONS sont cloisonnées autant que
 * l'affichage. Un filtre posé à la vue aurait laissé passer un identifiant forgé — et l'erreur
 * n'aurait l'air de rien tant qu'il n'y a qu'un seul pays en base, c'est-à-dire aujourd'hui.
 *
 * LA CRÉATION DE ZONE N'EXISTAIT NULLE PART avant ce composant. `saveZone()`, hérité, n'édite
 * qu'une zone déjà sélectionnée : il exige un `selectedZoneId` et fait `findOrFail`. D'où
 * `creerZone()`.
 *
 * `$selectedZone` vient de `ManagesZonesData::getSelectedZoneProperty()` : c'est une propriété
 * magique de Livewire, invisible à l'analyse statique. On la déclare pour que PHPStan la voie.
 *
 * @property-read ServiceZone|null $selectedZone
 */
#[Layout('layouts.app')]
class ZoneCenter extends Component
{
    use EnforcesAdminAccess;
    use ManagesZonesData;
    use PerformsZoneManagementActions;
    use WithPagination;

    public Country $country;

    /** @var array<string, mixed> */
    public array $nouvelleZone = ['name' => '', 'code' => ''];

    public ?string $blocage = null;

    // ── Propriétés attendues par les traits ────────────────────────────────────────────────
    // Elles sont déclarées par le composant hôte et non par les traits eux-mêmes : c'est la
    // convention du dépôt, `GestionZones` porte exactement le même jeu.

    public string $search = '';

    public string $statusFilter = '';

    public string $regionFilter = '';

    public string $provinceFilter = '';

    public string $bookableFilter = '';

    public string $visibilityFilter = '';

    public string $coverageFilter = '';

    public ?int $selectedZoneId = null;

    public string $name = '';

    public string $code = '';

    public string $coverage_type = 'custom';

    public string $status = 'active';

    public bool $is_bookable = true;

    public bool $is_visible = true;

    public int $priority = 100;

    public int $minimum_notice_hours = 24;

    public ?int $maximum_daily_jobs = null;

    public float $travel_surcharge = 0;

    public int $time_buffer_minutes = 0;

    public string $notes = '';

    public string $employeeToAssign = '';

    public string $assignmentType = 'primary';

    public int $assignmentPriority = 100;

    public string $assignmentNotes = '';

    public string $copyRulesFromZoneId = '';

    /** @var array<int, mixed> */
    public array $serviceRules = [];

    /** @var array<int, mixed> */
    public array $assignmentEdits = [];

    public function mount(Country $country): void
    {
        $this->country = $country;
    }

    /**
     * Le cloisonnement, en un seul endroit.
     *
     * Redéfinir la requête de base plutôt que filtrer à l'affichage : `selectZone()` l'emploie pour
     * son `findOrFail`, donc toute action qui passe par une sélection hérite de la restriction.
     */
    /** @return Builder<ServiceZone> */
    protected function zoneBaseQuery(): Builder
    {
        return ServiceZone::query()
            ->where('country_id', $this->country->id)
            ->with(['region', 'province'])
            ->withCount([
                'postalCodes',
                'organizationSites',
                'employeeAssignments as active_employee_assignments_count' => fn ($query) => $query->where('is_active', true),
                'zoneServiceRules as enabled_service_rules_count' => fn ($query) => $query->where('is_enabled', true),
                'zoneServiceRules as manual_validation_rules_count' => fn ($query) => $query->where('requires_manual_validation', true),
            ]);
    }

    /**
     * Créer une zone dans CE pays.
     *
     * Le pays vient du contexte et n'est pas un champ : le laisser saisissable permettrait de
     * créer, depuis l'écran Belgique, une zone française — une erreur qui ne se verrait qu'en
     * cherchant une zone disparue.
     */
    public function creerZone(): void
    {
        $valide = $this->validate([
            'nouvelleZone.name' => ['required', 'string', 'max:255'],
            'nouvelleZone.code' => ['required', 'string', 'max:32', 'unique:service_zones,code'],
        ])['nouvelleZone'];

        $nom = (string) $valide['name'];

        ServiceZone::create([
            'country_id' => $this->country->id,
            'name' => $nom,
            'code' => strtoupper((string) $valide['code']),
            // Un identifiant lisible et unique, sans demander à l'administrateur de l'inventer.
            'slug' => Str::slug($nom).'-'.Str::lower(Str::random(5)),
            'coverage_type' => 'custom',
            /*
             * Une zone neuve naît FERMÉE.
             *
             * Même raison que pour un pays neuf : créer une zone ne doit pas la rendre commandable
             * avant qu'on ait réglé son catalogue et ses prix. L'ouverture est un geste séparé.
             */
            'status' => 'draft',
            'is_bookable' => false,
            'is_visible' => false,
            'priority' => 100,
            'minimum_notice_hours' => 24,
            'travel_surcharge' => 0,
            'time_buffer_minutes' => 0,
        ]);

        $this->nouvelleZone = ['name' => '', 'code' => ''];
        $this->blocage = null;
        $this->resetPage();
    }

    public function supprimerZone(int $id, GeoGuard $guard): void
    {
        // `findOrFail` sur la requête cloisonnée : un identifiant d'un autre pays rend 404 plutôt
        // que de supprimer la zone d'un marché voisin.
        $zone = ServiceZone::query()
            ->where('country_id', $this->country->id)
            ->findOrFail($id);

        $raisons = $guard->raisonsDeNePasSupprimerZone($zone);

        if ($raisons !== []) {
            $this->blocage = 'Suppression impossible : '.implode(', ', $raisons)
                .'. Désactivez la zone si vous voulez la fermer sans rien perdre.';

            return;
        }

        if ($this->selectedZoneId === $zone->id) {
            $this->selectedZoneId = null;
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
