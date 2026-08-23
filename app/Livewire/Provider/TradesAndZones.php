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
 * « CE QUE JE FAIS, ET OÙ » — l'écran qui décide de ce qu'un prestataire reçoit. Il n'existait pas.
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

    /** Enregistre la déclaration. */
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
