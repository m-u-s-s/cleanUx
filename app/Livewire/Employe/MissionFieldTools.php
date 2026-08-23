<?php

namespace App\Livewire\Employe;

use App\Models\MaskedCallSession;
use App\Models\Mission;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Models\MissionQuoteRevision;
use App\Services\Missions\HourlyMissionClock;
use App\Services\Missions\MissionQuoteRevisionService;
use App\Services\Missions\MissionReinforcementService;
use App\Services\Missions\OnSite\MissionAccessSheetService;
use App\Services\Missions\OnSite\MissionExtraService;
use App\Services\Missions\OnSite\MissionIncidentService;
use App\Services\Missions\QuoteRevisionWindow;
use App\Support\Domain\MissionEngine;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** LES OUTILS DE TERRAIN QUI MANQUAIENT AU WEB. */
class MissionFieldTools extends Component
{
    #[Locked]
    public int $missionId;

    public string $incidentType = '';

    public string $incidentDescription = '';

    public string $extraLabel = '';

    public string $extraPrix = '';

    public string $revisionPrix = '';

    public string $revisionMotif = '';

    #[Locked]
    public ?string $erreur = null;

    #[Locked]
    public ?string $succes = null;

    public function mount(Mission $mission): void
    {
        $this->assertAssigne($mission);

        $this->missionId = $mission->id;
    }

    public function signalerUnImprevu(): void
    {
        $this->reinitialiser();

        $this->validate([
            'incidentType' => ['required', 'string'],
            'incidentDescription' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        try {
            app(MissionIncidentService::class)->report(
                $this->mission(),
                Auth::user(),
                $this->incidentType,
                $this->incidentDescription,
            );

            $this->reset(['incidentType', 'incidentDescription']);
            $this->succes = 'Imprévu signalé : le client vient d’être prévenu.';
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function proposerUnSupplement(): void
    {
        $this->reinitialiser();

        $this->validate([
            'extraLabel' => ['required', 'string', 'max:191'],
            'extraPrix' => ['required', 'numeric', 'min:0.01', 'max:500'],
        ]);

        try {
            app(MissionExtraService::class)->propose(
                $this->mission(),
                Auth::user(),
                $this->extraLabel,
                // La saisie est en euros, l'envoi en centimes : laisser voyager un flottant jusqu'à
                // la base produirait des écarts d'un centime que personne ne sait expliquer.
                (int) round(((float) $this->extraPrix) * 100),
            );

            $this->reset(['extraLabel', 'extraPrix']);
            $this->succes = 'Supplément proposé : le client répond depuis son téléphone.';
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();
        }
    }

    /** PROPOSER UN NOUVEAU DEVIS — moteur à domicile seulement, et le serveur le refusera ailleurs. */
    public function proposerUnNouveauDevis(): void
    {
        $this->reinitialiser();

        $this->validate([
            'revisionPrix' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'revisionMotif' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $mission = $this->mission();

        // LA PREUVE EST OBLIGATOIRE, et on la cherche parmi les photos « avant » déjà prises :
        // sans elle, le client doit croire sur parole et l'arbitre doit trancher sans matière.
        $preuves = MissionMedia::query()
            ->where('mission_id', $mission->id)
            ->where('media_type', MissionMedia::TYPE_BEFORE_PHOTO)
            ->pluck('id')
            ->all();

        if ($preuves === []) {
            $this->erreur = 'Prenez d’abord une photo « avant » : sans preuve, le client doit vous croire sur parole.';

            return;
        }

        try {
            app(MissionQuoteRevisionService::class)->proposer(
                $mission,
                Auth::user(),
                (int) round(((float) $this->revisionPrix) * 100),
                $this->revisionMotif,
                $preuves,
            );

            $this->reset(['revisionPrix', 'revisionMotif']);
            $this->succes = 'Nouveau devis envoyé : le client répond depuis son téléphone.';
        } catch (DomainException $e) {
            // « Vous avez commencé l'intervention » dit quel geste employer à la place.
            $this->erreur = $e->getMessage();
        }
    }

    /** DEMANDER DU RENFORT — l'autre réponse au même constat. */
    public function demanderDuRenfort(): void
    {
        $this->reinitialiser();

        $this->validate(['revisionMotif' => ['required', 'string', 'min:3', 'max:2000']]);

        try {
            app(MissionReinforcementService::class)
                ->demander($this->mission(), Auth::user(), $this->revisionMotif);

            $this->reset(['revisionMotif']);
            $this->succes = 'Renfort demandé : votre demande est ouverte.';
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function retirerLeNouveauDevis(int $revisionId): void
    {
        $this->reinitialiser();

        $revision = MissionQuoteRevision::query()->find($revisionId);

        if ($revision === null || (int) $revision->mission_id !== $this->missionId) {
            return;
        }

        try {
            app(MissionQuoteRevisionService::class)->retirer($revision, Auth::user());
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function render(): View
    {
        $mission = $this->mission();
        $moteur = MissionEngine::pourMission($mission);

        return view('livewire.employe.mission-field.tools', [
            'mission' => $mission,
            'moteur' => $moteur,
            'categories' => MissionIncident::typesTerrain(),
            'extras' => app(MissionExtraService::class)->pourLaMission($mission),
            'ficheDAcces' => $this->ficheDAcces($mission),
            // Le compteur se rend NUL de lui-même hors mission horaire : la vue n'a pas à savoir
            // ce qui décide, et une seconde règle ici finirait par contredire la première.
            'horloge' => app(HourlyMissionClock::class)->etat($mission),
            'fenetreRevision' => MissionEngine::accepteLeNouveauDevis($moteur)
                ? app(QuoteRevisionWindow::class)->etat($mission)
                : null,
            'revision' => app(MissionQuoteRevisionService::class)->vivante($mission),
            'renfort' => app(MissionReinforcementService::class)->ouverte($mission),
            'ligne' => $mission->booking === null ? null : $this->ligneMasquee(
                (int) $mission->booking->client_id,
                (int) Auth::id(),
                (int) $mission->booking->id,
            ),
        ]);
    }

    /**
     * LA LIGNE MASQUÉE — un numéro relais, jamais celui de l'autre.
     *
     * @return array<string, mixed>|null
     */
    private function ligneMasquee(int $clientId, int $prestataireId, int $bookingId): ?array
    {
        $session = MaskedCallSession::query()
            ->where('booking_id', $bookingId)
            ->where('client_user_id', $clientId)
            ->where('provider_user_id', $prestataireId)
            ->where('status', MaskedCallSession::STATUS_ACTIVE)
            ->latest('id')
            ->first();

        if ($session === null || ! $session->isActive() || $session->proxy_phone_number === null) {
            return null;
        }

        return ['numero' => $session->proxy_phone_number];
    }

    /**
     * LA FICHE D'ACCÈS NE S'AFFICHE QU'UNE FOIS L'ARRIVÉE CONFIRMÉE — le service le décide, et il lève quand la condition n'est pas remplie.
     *
     * @return array<string, mixed>
     */
    private function ficheDAcces(Mission $mission): array
    {
        try {
            return app(MissionAccessSheetService::class)->pour($mission, Auth::user());
        } catch (DomainException $e) {
            return app(MissionAccessSheetService::class)->verrouillee($e->getMessage());
        }
    }

    private function mission(): Mission
    {
        return Mission::query()->with('booking')->findOrFail($this->missionId);
    }

    /** « QUI INTERVIENT » NE SE DÉDUIT PAS D'UNE COLONNE — et un test du dépôt le vérifie. */
    private function assertAssigne(Mission $mission): void
    {
        abort_unless($mission->estIntervenant(Auth::user()), 403);
    }

    private function reinitialiser(): void
    {
        $this->erreur = null;
        $this->succes = null;
    }
}
