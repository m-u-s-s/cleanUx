<?php

namespace App\Livewire\Admin;

use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderPerformanceMetric;
use App\Services\Dispatch\CandidateFinder;
use App\Services\Dispatch\DispatchCandidate;
use App\Services\Dispatch\DispatchEngine;
use App\Support\ActivityLogger;
use App\Support\Domain\AsapStatus;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * LE CENTRE DE RÉPARTITION — raconter l'histoire d'une recherche, pas afficher un compteur.
 *
 * @property-read array<string, int> $compteurs
 */
#[Layout('layouts.app')]
class DispatchCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    /** searching | expired | accepted | all */
    public string $filtre = 'searching';

    /** recherches | sans_intervenant | poids | metriques */
    public string $onglet = 'recherches';

    public ?int $rechercheOuverte = null;

    public ?int $simulerBookingId = null;

    /** @var list<array<string, mixed>>|null */
    public ?array $simulation = null;

    public string $erreurSimulation = '';

    /**
     * Les compteurs d'exploitation.
     *
     * @return array<string, int>
     */
    public function getCompteursProperty(): array
    {
        return [
            'en_cours' => AsapDispatchRequest::query()->where('status', AsapStatus::SEARCHING)->count(),
            'sans_candidat' => AsapDispatchRequest::query()->where('status', AsapStatus::EXPIRED)->count(),
            'acceptees' => AsapDispatchRequest::query()->where('status', AsapStatus::ACCEPTED)->count(),
            'offres_24h' => MissionAssignment::query()->where('created_at', '>=', now()->subDay())->count(),
            'refus_24h' => MissionAssignment::query()
                ->where('assignment_status', 'declined')
                ->where('updated_at', '>=', now()->subDay())
                ->count(),
            'silences_24h' => MissionAssignment::query()
                ->where('assignment_status', 'expired')
                ->where('updated_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    public function updatedOnglet(): void
    {
        $this->resetPage();
    }

    /**
     * IMPOSER D'OFFICE — l'acte que portait « IA Dispatch », rebranche sur le moteur de
     * production. L'ancien ecrivait `employe_id` en direct, sans offre ni garde.
     */
    public function imposer(int $missionId): void
    {
        $mission = Mission::find($missionId);

        if (! $mission) {
            $this->dispatch('toast', 'Mission introuvable.', 'error');

            return;
        }

        $assignation = app(DispatchEngine::class)->imposerDOffice($mission);

        if ($assignation === null) {
            $this->dispatch('toast', 'Aucune imposition : mission deja pourvue, ou aucun prestataire en regle.', 'error');

            return;
        }

        ActivityLogger::log('dispatch_impose_doffice', $mission, [
            'mission_id' => (int) $mission->id,
            'provider_user_id' => (int) $assignation->user_id,
        ]);

        $this->dispatch('toast', 'Mission imposee au prestataire #'.$assignation->user_id.'.', 'success');
    }

    public function ouvrir(int $rechercheId): void
    {
        $this->rechercheOuverte = $rechercheId;
    }

    public function fermer(): void
    {
        $this->rechercheOuverte = null;
    }

    /**
     * La chaîne d'offres d'une recherche, dans l'ordre où elle s'est déroulée.
     *
     * @return list<array<string, mixed>>
     */
    public function chaine(): array
    {
        if (! $this->rechercheOuverte) {
            return [];
        }

        $recherche = AsapDispatchRequest::find($this->rechercheOuverte);

        if (! $recherche) {
            return [];
        }

        return MissionAssignment::query()
            ->where('mission_id', $recherche->mission_id)
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (MissionAssignment $offre) => [
                'id' => (int) $offre->id,
                'provider' => $offre->user?->name,
                'statut' => $offre->assignment_status,
                'envoyee_a' => $offre->notification_sent_at?->format('H:i:s'),
                'expire_a' => $offre->expires_at?->format('H:i:s'),
                'repondue_en' => $offre->response_seconds,
                'motif' => $offre->decline_reason,
            ])
            ->all();
    }

    /** « Pour cette réservation, qui serait candidat, et dans quel ordre. */
    public function simuler(): void
    {
        $this->simulation = null;
        $this->erreurSimulation = '';

        $booking = $this->simulerBookingId ? Booking::find($this->simulerBookingId) : null;

        if (! $booking) {
            $this->erreurSimulation = 'Réservation introuvable.';

            return;
        }

        $finder = app(CandidateFinder::class);

        if (! $finder->tradeIdFor($booking)) {
            // Le message dit la CAUSE, pas « aucun résultat » : sans métier, la requête candidate
            // ne rend personne — par construction, et c'est l'invariant qu'on veut voir affirmé.
            $this->erreurSimulation = 'Cette réservation ne porte aucun métier : le dispatch ne cherche personne, plutôt que de chercher n’importe qui.';

            return;
        }

        $immediat = ($booking->booking_mode ?? null) === 'asap';

        $candidats = $immediat
            ? $finder->immediate($booking, (int) Config::get('dispatch.waves.max_radius_m', 20000))
            : $finder->scheduled($booking);

        $this->simulation = $candidats
            ->take(20)
            ->map(fn (DispatchCandidate $candidat) => $candidat->toArray())
            ->all();
    }

    public function render(): View
    {
        $recherches = $this->onglet === 'recherches'
            ? AsapDispatchRequest::query()
                ->with(['trade:id,name', 'booking:id,booking_reference,city,postal_code', 'acceptedBy:id,name'])
                ->when($this->filtre !== 'all', fn ($q) => $q->where('status', $this->filtre))
                ->latest('id')
                ->paginate(15)
            : null;

        // LA LISTE DIT VRAI : uniquement les missions sur lesquelles l'imposition peut agir —
        // planifiees, sans intervenant, hors mode immediat qui a sa propre sortie.
        $sansIntervenant = $this->onglet === 'sans_intervenant'
            ? Mission::query()
                ->whereNull('lead_provider_user_id')
                ->where('status', 'planned')
                ->whereHas('booking', fn ($q) => $q->where('booking_mode', '!=', 'asap'))
                ->with(['booking:id,booking_reference,date,heure,city,client_id', 'booking.client:id,name'])
                ->orderBy('id')
                ->paginate(15)
            : null;

        $metriques = $this->onglet === 'metriques'
            ? ProviderPerformanceMetric::query()
                ->with(['provider:id,name'])
                ->latest('period_end')
                ->paginate(15)
            : null;

        return view('livewire.admin.dispatch-center', [
            'recherches' => $recherches,
            'sansIntervenant' => $sansIntervenant,
            'metriques' => $metriques,
            // LES POIDS DU SCORE DE PRODUCTION : `MatchingScoreEngine::weights()` lit ce meme
            // reglage. Ce panneau decrit donc ce que le dispatch fait, pas un moteur voisin.
            'poids' => Config::get('matching.weights', []),
            'chaine' => $this->chaine(),
            'reglages' => [
                'ttl_immediat' => (int) Config::get('dispatch.default_timeout', 20),
                'ttl_planifie' => (int) Config::get('dispatch.scheduled_offer_timeout_seconds', 1800),
                'rayon_initial' => (int) Config::get('dispatch.waves.initial_radius_m', 5000),
                'rayon_max' => (int) Config::get('dispatch.waves.max_radius_m', 20000),
                'echeance' => (int) Config::get('dispatch.search_deadline_seconds', 300),
                'fraicheur' => (int) Config::get('dispatch.position_freshness_minutes', 5),
            ],
        ]);
    }
}
