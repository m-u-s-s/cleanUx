<?php

namespace App\Livewire\ClientCompany;

use App\Models\OrganizationSite;
use App\Models\Trade;
use App\Services\Bookings\MultiSiteRequestService;
use App\Services\Enterprise\MemberSiteAccessService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Demander une même prestation pour plusieurs locaux d'un coup. */
class MultiSiteRequest extends Component
{
    use EnforcesActiveOrgMembership;

    /** @var list<int> */
    public array $siteIds = [];

    public ?int $tradeId = null;

    public string $date = '';

    public string $heure = '09:00';

    public int $dureeEstimee = 120;

    public string $commentaire = '';

    public ?int $demandeCreee = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->isClientCompany(), 403);

        $this->date = now()->addWeek()->toDateString();
    }

    public function creer(): void
    {
        $acteur = Auth::user();

        // Une demande multi-sites engage la société sur plusieurs interventions : c'est une
        // création de réservations, pas une consultation.
        abort_unless(
            app(PermissionService::class)->can($acteur, 'bookings.create', $acteur->currentOrganization),
            403
        );

        $this->validate([
            'siteIds' => ['required', 'array', 'min:1'],
            'tradeId' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'heure' => ['required'],
            'dureeEstimee' => ['required', 'integer', 'min:15'],
        ]);

        $trade = Trade::find($this->tradeId);

        if (! $trade) {
            $this->addError('tradeId', 'Métier introuvable.');

            return;
        }

        // LA SÉLECTION VIENT DU NAVIGATEUR, et `siteIds` est une propriété publique : elle se réécrit par un simple `$set`, quelle que soit la liste affichée.
        $retenus = $this->filtrerAuxLocauxAutorises(array_map('intval', $this->siteIds));

        if ($retenus === []) {
            $this->addError('siteIds', 'Aucun local valide dans la sélection.');

            return;
        }

        $demande = app(MultiSiteRequestService::class)->creer(
            $acteur,
            $acteur->currentOrganization,
            $trade,
            $retenus,
            Carbon::parse($this->date.' '.$this->heure),
            [
                'duree_estimee' => $this->dureeEstimee,
                'commentaire_client' => $this->commentaire,
            ],
        );

        if (! $demande) {
            // Le service filtre les sites sur l'organisation : n'en retenir aucun signifie que la
            // sélection ne contenait rien de recevable.
            $this->addError('siteIds', 'Aucun local valide dans la sélection.');

            return;
        }

        $this->demandeCreee = $demande->id;
        $this->reset(['siteIds', 'commentaire']);
    }

    /**
     * Ne garder que les locaux que ce membre a le droit de piloter.
     *
     * @param  list<int>  $siteIds
     * @return list<int>
     */
    protected function filtrerAuxLocauxAutorises(array $siteIds): array
    {
        $autorises = app(MemberSiteAccessService::class)->sitesAutorises(Auth::user());

        if ($autorises === null) {
            return $siteIds;
        }

        return array_values(array_intersect($siteIds, $autorises));
    }

    public function render(): View
    {
        $orgId = Auth::user()->current_organization_id;
        $autorises = app(MemberSiteAccessService::class)->sitesAutorises(Auth::user());

        return view('livewire.client-company.multi-site-request', [
            'sites' => OrganizationSite::query()
                ->where('organization_account_id', $orgId)
                ->where('is_active', true)
                // La liste proposée suit la même restriction que l'écriture : offrir un local
                // qu'on refusera ensuite serait une invitation à l'erreur, et révélerait au passage
                // l'existence des agences voisines.
                ->when($autorises !== null, fn ($q) => $q->whereIn('id', $autorises))
                ->orderBy('name')
                ->get(['id', 'name', 'site_code', 'city']),
            'trades' => Trade::query()->orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.client-company');
    }
}
