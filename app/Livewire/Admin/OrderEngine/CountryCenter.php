<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Models\Country;
use App\Services\Catalog\GeoGuard;
use App\Support\International\DeviseParPays;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Le premier niveau du catalogue : les pays. LE PAYS N'ORGANISE QUE LES ZONES. */
#[Layout('layouts.app')]
class CountryCenter extends Component
{
    /** Le refus vaut au niveau du composant, pas seulement de la route. */
    use EnforcesAdminAccess;

    use WithPagination;

    /**
     * L'ONGLET COURANT. Le catalogue reste la colonne vertebrale — Pays, puis Zones, puis
     * Metiers et prix. Les autres onglets sont des vues transverses, pas des detours.
     * pays | zones | metiers | services | marche
     */
    #[Url(as: 'onglet')]
    public string $onglet = 'pays';

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

    /** La liste, avec le nombre de zones de chaque pays. */
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
            'iso_code', 'iso3_code', 'name', 'currency_code', 'default_locale', 'timezone', 'phone_code',
        ]);
    }

    public function enregistrer(): void
    {
        // La règle d'unicité doit s'ignorer elle-même en édition.
        $unicite = 'unique:countries,iso_code'.($this->editionId ? ','.$this->editionId : '');

        $valide = $this->validate([
            'formulaire.iso_code' => ['required', 'string', 'size:2', $unicite],
            'formulaire.iso3_code' => ['nullable', 'string', 'size:3'],
            'formulaire.name' => ['required', 'string', 'max:120'],
            'formulaire.currency_code' => ['required', 'string', 'size:3'],
            'formulaire.default_locale' => ['nullable', 'string', 'max:10'],
            'formulaire.timezone' => ['nullable', 'string', 'max:64'],
            'formulaire.phone_code' => ['nullable', 'string', 'max:8'],
        ])['formulaire'];

        $valide['iso_code'] = strtoupper((string) $valide['iso_code']);
        $valide['currency_code'] = strtoupper((string) $valide['currency_code']);
        $valide['iso3_code'] = $valide['iso3_code'] === '' || $valide['iso3_code'] === null
            ? null
            : strtoupper((string) $valide['iso3_code']);

        // LA DEVISE DOIT CORRESPONDRE AU PAYS, ET LE FORMULAIRE PROPOSAIT `EUR` A TOUT LE MONDE.
        $attendue = DeviseParPays::pour($valide['iso_code']);

        if ($attendue !== null && $attendue !== $valide['currency_code'] && ! $this->devisePeuDefaut) {
            $this->devisePeuDefaut = true;
            $this->blocage = "La devise de {$valide['iso_code']} est normalement {$attendue}, "
                ."et vous avez saisi {$valide['currency_code']}. Enregistrez de nouveau pour "
                .'confirmer ce choix.';

            return;
        }

        $this->devisePeuDefaut = false;

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

        // On ne touche QUE le pays.
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

    /** Une devise inattendue a-t-elle deja ete signalee pour cette saisie ? */
    #[Locked]
    public bool $devisePeuDefaut = false;

    /** LA DEVISE ATTENDUE POUR LE CODE ISO EN COURS DE SAISIE. */
    public function deduireLaDevise(): void
    {
        if (trim((string) ($this->formulaire['currency_code'] ?? '')) !== '') {
            return;
        }

        $attendue = DeviseParPays::pour($this->formulaire['iso_code'] ?? null);

        if ($attendue !== null) {
            $this->formulaire['currency_code'] = $attendue;
        }
    }

    private function reinitialiserFormulaire(): void
    {
        $this->formulaire = [
            'iso_code' => '',
            // REPRIS DE LA PAGE « PILOTAGE DES PAYS » : le seul champ qu'elle savait editer et
            // que le catalogue ignorait. Facultatif, comme la colonne.
            'iso3_code' => '',
            'name' => '',
            // Vide, et non `EUR` : voir `deduireLaDevise()`. Une valeur pre-remplie qui se trouve
            // etre juste vingt fois sur vingt-cinq est le pire des defauts -- on cesse de la lire.
            'currency_code' => '',
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
