<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Models\Country;
use App\Services\Catalog\GeoGuard;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Le premier niveau du catalogue : les pays.
 *
 * LE PAYS N'ORGANISE QUE LES ZONES. Il ne porte aucun métier : un pays « a » un métier si au moins
 * une de ses zones l'a. C'est un calcul, jamais un réglage — donc rien à tenir à jour, et aucun
 * risque qu'un réglage pays contredise la vérité du terrain.
 *
 * De là découle qu'il n'existe pas de table `country_trade`, et que ce composant ne sait rien des
 * métiers.
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

    /** Ce qui a empêché la dernière suppression. Affiché tant qu'on ne repart pas. */
    public ?string $blocage = null;

    public function mount(): void
    {
        $this->reinitialiserFormulaire();
    }

    /**
     * La liste, avec le nombre de zones de chaque pays.
     *
     * `withCount` plutôt qu'une relation chargée : la liste n'a besoin que du nombre, et charger
     * les zones de chaque pays pour les compter ferait une requête par ligne.
     */
    /** @return LengthAwarePaginator<int, Country> */
    #[Computed]
    public function pays(): LengthAwarePaginator
    {
        return Country::query()
            ->withCount('serviceZones')
            ->orderBy('name')
            ->paginate(20);
    }

    public function nouveau(): void
    {
        $this->editionId = null;
        $this->blocage = null;
        $this->reinitialiserFormulaire();
    }

    public function editer(int $id): void
    {
        $pays = Country::findOrFail($id);

        $this->editionId = $id;
        $this->blocage = null;
        $this->formulaire = $pays->only([
            'iso_code', 'name', 'currency_code', 'default_locale', 'timezone', 'phone_code',
        ]);
    }

    public function enregistrer(): void
    {
        /*
         * La règle d'unicité doit s'ignorer elle-même en édition.
         *
         * Sans le `,{id}`, aucun pays ne serait modifiable une fois créé : son propre code ISO
         * déclencherait le conflit. C'est le genre de défaut qui ne se voit qu'au deuxième usage.
         */
        $unicite = 'unique:countries,iso_code'.($this->editionId ? ','.$this->editionId : '');

        $valide = $this->validate([
            'formulaire.iso_code' => ['required', 'string', 'size:2', $unicite],
            'formulaire.name' => ['required', 'string', 'max:120'],
            'formulaire.currency_code' => ['required', 'string', 'size:3'],
            'formulaire.default_locale' => ['nullable', 'string', 'max:10'],
            'formulaire.timezone' => ['nullable', 'string', 'max:64'],
            'formulaire.phone_code' => ['nullable', 'string', 'max:8'],
        ])['formulaire'];

        $valide['iso_code'] = strtoupper((string) $valide['iso_code']);
        $valide['currency_code'] = strtoupper((string) $valide['currency_code']);

        if ($this->editionId) {
            Country::findOrFail($this->editionId)->update($valide);
            $this->flash = 'Pays mis à jour.';
        } else {
            // Un pays neuf n'ouvre pas les réservations tout seul : une faute de frappe ne doit
            // pas rendre un marché commandable. L'ouverture est un geste séparé et délibéré.
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
         * joignabilité se lit — voir `GeoGuard::zoneEstJoignable()` — elle ne s'écrit pas.
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
            // On dit ce qui bloque, avec le compte : l'administrateur n'a pas accès à la base pour
            // le découvrir autrement.
            $this->blocage = 'Suppression impossible : '.implode(', ', $raisons)
                .'. Désactivez le pays si vous voulez le fermer sans rien perdre.';

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
