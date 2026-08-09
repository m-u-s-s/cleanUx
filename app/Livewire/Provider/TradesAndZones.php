<?php

namespace App\Livewire\Provider;

use App\Models\User;
use App\Services\Catalog\ProviderCoverageWriter;
use App\Services\Catalog\RegistrationOptionsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « CE QUE JE FAIS, ET OÙ » — l'écran qui décide de ce qu'un prestataire reçoit.
 *
 * Il n'existait pas. Le métier se déclarait une fois à l'inscription et ne se modifiait plus ; les
 * zones ne se déclaraient nulle part. Un prestataire qui déménageait, ou qui ajoutait une corde à
 * son arc, devait écrire au support — et attendre qu'un administrateur touche la base.
 *
 * CE QUI EST COCHÉ ICI EST EXACTEMENT CE QUE LIT LE DISPATCH. `trade_user` et
 * `employee_zone_assignments` sont les deux tables de la requête candidate : cocher une case change
 * immédiatement les offres reçues, sans déploiement et sans intervention.
 *
 * LES LISTES VIENNENT DU CATALOGUE. On ne propose que des métiers réellement vendus quelque part,
 * et que des zones actives : laisser déclarer une couverture que rien ne peut servir ferait
 * attendre des offres qui ne viendraient jamais.
 *
 * @property-read array<string, mixed> $catalogue
 */
#[Layout('layouts.app')]
class TradesAndZones extends Component
{
    /** @var list<int> */
    public array $tradeIds = [];

    /** @var list<int> */
    public array $zoneIds = [];

    public string $flash = '';

    public function mount(): void
    {
        $prestataire = $this->prestataire();

        abort_unless($prestataire !== null, 403);

        $this->tradeIds = $prestataire->trades()->pluck('trades.id')->map(fn ($id) => (int) $id)->all();

        $this->zoneIds = $prestataire->zoneAssignments()
            ->where('is_active', true)
            ->pluck('service_zone_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<string, mixed> */
    #[Computed(persist: false)]
    public function catalogue(): array
    {
        return app(RegistrationOptionsService::class)->forCountry(
            (string) config('order_engine.geocoding_country', 'BE'),
        );
    }

    /**
     * Enregistre la déclaration.
     *
     * LIVEWIRE NE REJOUE PAS `mount()` : l'identité est relue ici, et les identifiants reçus sont
     * validés contre le catalogue par `ProviderCoverageWriter`. Ce qui vient du navigateur n'est
     * jamais cru sur parole — sans quoi on déclarerait une couverture sur un métier fermé.
     */
    public function save(): void
    {
        $prestataire = $this->prestataire();

        if (! $prestataire) {
            abort(403);
        }

        if ($this->tradeIds === []) {
            $this->addError('tradeIds', 'Choisissez au moins un métier : sans métier, aucune mission ne peut vous être proposée.');

            return;
        }

        if ($this->zoneIds === []) {
            $this->addError('zoneIds', 'Choisissez au moins une zone : sans zone, aucune mission ne peut vous être proposée.');

            return;
        }

        $ecrit = app(ProviderCoverageWriter::class)->sync(
            $prestataire,
            $this->tradeIds,
            $this->zoneIds,
        );

        $this->tradeIds = $ecrit['trades'];
        $this->zoneIds = $ecrit['zones'];

        $this->flash = 'Vos métiers et vos zones sont enregistrés. Les missions correspondantes vous seront proposées dès maintenant.';
    }

    protected function prestataire(): ?User
    {
        $utilisateur = Auth::user();

        if (! $utilisateur instanceof User) {
            return null;
        }

        // La garde de route dit `role:employe` ; celle-ci dit « et il a bien un profil prestataire ».
        return $utilisateur->providerProfile ? $utilisateur : null;
    }

    public function render(): View
    {
        return view('livewire.provider.trades-and-zones');
    }
}
