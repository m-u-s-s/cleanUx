<?php

namespace App\Livewire\Admin;

use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\MissionAssignment;
use App\Services\Dispatch\CandidateFinder;
use App\Services\Dispatch\DispatchCandidate;
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
 * Quand une course n'aboutit pas, la seule question qui compte est « pourquoi ». Elle a exactement
 * quatre réponses possibles, et il faut pouvoir les distinguer d'un coup d'œil :
 *
 *   1. personne n'a été trouvé (métier ou zone sans prestataire déclaré) ;
 *   2. des gens ont été trouvés mais aucun n'était en ligne ;
 *   3. ils ont refusé ;
 *   4. ils n'ont pas répondu.
 *
 * Sans la chaîne d'offres complète — qui, quand, à quelle distance, refus ou silence — les quatre se
 * ressemblent, et l'exploitation conclut « pas assez de prestataires » alors que le problème est un
 * réglage de rayon.
 *
 * LE SIMULATEUR répond à l'autre question : « pour CETTE réservation, qui serait candidat, et dans
 * quel ordre ». Il appelle le VRAI `CandidateFinder`, pas une approximation : une simulation qui
 * n'emprunterait pas le même chemin que le dispatch ne prouverait rien.
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

    public ?int $rechercheOuverte = null;

    public ?int $simulerBookingId = null;

    /** @var list<array<string, mixed>>|null */
    public ?array $simulation = null;

    public string $erreurSimulation = '';

    /**
     * Les compteurs d'exploitation.
     *
     * `no_candidate` est celui qui compte : c'est le nombre de clients qui ont attendu pour rien.
     * Le laisser invisible fait découvrir le problème par les avis clients.
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

    /**
     * « Pour cette réservation, qui serait candidat, et dans quel ordre. »
     *
     * Appelle le MÊME service que le dispatch. Une simulation qui recalculerait les candidats
     * autrement donnerait un ordre plausible et faux — le pire des deux mondes.
     */
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
        $recherches = AsapDispatchRequest::query()
            ->with(['trade:id,name', 'booking:id,booking_reference,city,postal_code', 'acceptedBy:id,name'])
            ->when($this->filtre !== 'all', fn ($q) => $q->where('status', $this->filtre))
            ->latest('id')
            ->paginate(15);

        return view('livewire.admin.dispatch-center', [
            'recherches' => $recherches,
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
