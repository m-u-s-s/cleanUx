<?php

namespace App\Services\Missions\OnSite;

use App\Events\Missions\MissionIncidentReported;
use App\Models\Mission;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Models\User;
use App\Notifications\MissionIncidentNotification;
use App\Services\Missions\MissionAssignmentStatusService;
use App\Services\Missions\MissionHistoryService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * LE SIGNALEMENT D'IMPRÉVU — dire tout de suite ce qui, sinon, se dira dans un litige.
 *
 * Un dégât préexistant non signalé devient, trois jours plus tard, un dégât causé par le
 * prestataire : c'est sa parole contre celle du client, et personne ne gagne ce genre de dossier.
 * Signalé à l'arrivée, photo à l'appui et horodatage à l'appui, il n'y a plus de débat à avoir.
 *
 * TROIS EFFETS, dans cet ordre, et l'ordre compte :
 *
 * 1. La ligne est écrite. Elle survit même si tout le reste échoue.
 * 2. Le client est prévenu. `notified_at` n'est posé QUE si l'envoi n'a pas levé — une colonne
 *    remplie d'avance ferait croire à une information qui n'est jamais partie.
 * 3. Le dossier de litige est pré-rempli. On ne l'OUVRE pas : ouvrir un litige au nom du client
 *    déclencherait une procédure qu'il n'a pas demandée, avec ses délais et ses relances. On
 *    prépare le formulaire, il décide.
 *
 * L'échec de la notification ne fait PAS échouer le signalement. Le prestataire est devant une
 * porte close ; lui rendre une erreur l'inviterait à réessayer, ce qui créerait un deuxième
 * incident identique pendant que le client, lui, n'a toujours rien reçu.
 */
class MissionIncidentService
{
    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
        protected MissionMediaService $mediaService,
    ) {}

    /**
     * Signale un imprévu sur place.
     *
     * @param  MissionIncident::TYPE_*  $type
     */
    public function report(
        Mission $mission,
        User $author,
        string $type,
        string $description,
        ?UploadedFile $photo = null,
        ?float $lat = null,
        ?float $lng = null,
    ): MissionIncident {
        $this->assignmentStatusService->assertAssignedToMission($mission, $author);

        if (! in_array($type, MissionIncident::typesTerrain(), true)) {
            throw new DomainException('Catégorie d’imprévu inconnue : '.$type);
        }

        $media = null;

        if ($photo !== null) {
            $media = $this->mediaService->capture(
                $mission,
                $author,
                $photo,
                MissionMedia::TYPE_INCIDENT_PHOTO,
                $lat,
                $lng,
                caption: MissionIncident::libelleType($type),
            );
        }

        $incident = MissionIncident::query()->create([
            'mission_id' => $mission->id,
            'reported_by_user_id' => $author->id,
            'incident_type' => $type,
            'severity' => $this->graviteDe($type),
            'status' => 'open',
            'title' => MissionIncident::libelleType($type),
            'description' => $description,
            'client_visible' => true,
            'reported_at' => now(),
            'mission_media_id' => $media?->id,
            'meta' => [
                'lat' => $lat,
                'lng' => $lng,
                'source' => 'terrain',
            ],
        ]);

        $this->prevenirLeClient($mission, $incident);

        event(new MissionIncidentReported($mission, $incident->fresh()));

        app(MissionHistoryService::class)->log(
            $mission,
            $author,
            'mission_incident_reported',
            'Imprévu signalé',
            MissionIncident::libelleType($type).' — '.$description,
            ['incident_id' => $incident->id],
        );

        return $incident->fresh();
    }

    /**
     * @return Collection<int, MissionIncident>
     */
    public function pourLaMission(Mission $mission, bool $clientSeulement = false): Collection
    {
        return MissionIncident::query()
            ->where('mission_id', $mission->id)
            ->when($clientSeulement, fn ($q) => $q->where('client_visible', true))
            ->with('media')
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function presenter(MissionIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'type' => $incident->incident_type,
            'label' => MissionIncident::libelleType($incident->incident_type),
            'severity' => $incident->severity,
            'status' => $incident->status,
            'description' => $incident->description,
            'reported_at' => $incident->reported_at?->toIso8601String(),
            'notified_at' => $incident->notified_at?->toIso8601String(),
            'photo' => $incident->media !== null ? $this->mediaService->presenter($incident->media) : null,
            'complaint_case_id' => $incident->complaint_case_id,
            'dispute_prefill' => $this->prefillLitige($incident),
        ];
    }

    /**
     * Ce que le formulaire de litige affichera si le client décide d'en ouvrir un.
     *
     * Les catégories sont celles de `ComplaintCase`, pas celles de l'incident : les traduire ici
     * évite au client de reclasser lui-même un problème qu'il vient de subir.
     *
     * @return array<string, string>
     */
    public function prefillLitige(MissionIncident $incident): array
    {
        /*
         * Une mission SANS réservation est légitime dans ce dépôt — les sociétés prestataires en
         * créent par conception. Le numéro interne prend alors le relais : le client n'aura jamais
         * un sujet de litige vide.
         */
        $reference = $incident->mission?->booking?->booking_reference;
        $designation = $reference !== null && $reference !== ''
            ? $reference
            : '#'.$incident->mission_id;

        return [
            'category' => match ($incident->incident_type) {
                MissionIncident::TYPE_PREEXISTING_DAMAGE => 'damage',
                MissionIncident::TYPE_ACCESS_IMPOSSIBLE => 'access',
                MissionIncident::TYPE_MISSING_ITEM => 'quality',
                default => 'other',
            },
            'subject' => MissionIncident::libelleType($incident->incident_type)
                .' — intervention '.$designation,
            'description' => (string) $incident->description,
        ];
    }

    /**
     * Les imprévus les plus graves sont ceux qui ARRÊTENT la mission, pas ceux qui la salissent.
     */
    private function graviteDe(string $type): string
    {
        return match ($type) {
            MissionIncident::TYPE_ACCESS_IMPOSSIBLE => 'high',
            MissionIncident::TYPE_PREEXISTING_DAMAGE => 'medium',
            default => 'normal',
        };
    }

    private function prevenirLeClient(Mission $mission, MissionIncident $incident): void
    {
        $client = $mission->booking?->client;

        if (! $client instanceof User) {
            return;
        }

        try {
            $client->notify(new MissionIncidentNotification($incident));
            $incident->forceFill(['notified_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('[terrain] Imprévu signalé mais client non prévenu.', [
                'mission_id' => $mission->id,
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
