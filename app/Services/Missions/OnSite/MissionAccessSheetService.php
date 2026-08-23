<?php

namespace App\Services\Missions\OnSite;

use App\Models\Mission;
use App\Models\User;
use App\Services\Missions\MissionAssignmentStatusService;
use App\Support\Domain\MissionStatus;
use DomainException;

/** LA FICHE D'ACCÈS AU LIEU — codes, étage, consignes (F5). */
class MissionAccessSheetService
{
    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
    ) {}

    /**
     * Les états où la fiche s'ouvre : le prestataire est sur place, ou déjà au travail.
     *
     * @var list<string>
     */
    private const ETATS_OUVRANTS = [
        MissionStatus::ARRIVED,
        MissionStatus::STARTED,
    ];

    /**
     * @return array<string, mixed>
     *
     * @throws DomainException quand la mission n'en est pas encore là
     */
    public function pour(Mission $mission, User $prestataire): array
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $prestataire);

        if (! in_array((string) $mission->status, self::ETATS_OUVRANTS, true)) {
            throw new DomainException(
                'Les informations d’accès s’affichent une fois votre arrivée confirmée sur place.',
            );
        }

        $booking = $mission->booking;
        $site = $booking?->organizationSite;
        // Le lieu du carnet client (E2) : la source pour un particulier, là où le site
        // d'organisation sert le B2B.
        $lieu = $booking?->clientPlace;

        return [
            'available' => true,
            // L'adresse est déjà connue avant l'arrivée : la répéter ici évite de faire naviguer
            // entre deux écrans avec les mains prises.
            'address' => $booking?->location_display,
            'floor' => $site?->floor ?: $lieu?->floor,
            'access_instructions' => $site?->access_instructions
                ?: ($lieu?->access_instructions
                    ?: ($booking?->commentaire_client ?: $booking?->customer_comment)),
            // LA CONSIGNE DE DERNIÈRE MINUTE, À PART ET AU-DESSUS.
            'live_note' => $booking?->live_access_note,
            'live_note_at' => $booking?->live_access_note_at?->toIso8601String(),
            // Un code d'alarme demande une manœuvre chronométrée : le prestataire doit le savoir
            // AVANT d'ouvrir la porte, pas en entendant la sirène.
            'alarm_code_required' => (bool) ($site->alarm_code_required ?? $lieu->alarm_code_required ?? false),
            'access_window' => $this->fenetreDAcces($site ?: $lieu),
            // LES PRÉFÉRENCES DU LIEU — produits, allergies, animaux.
            'preferences' => $lieu?->preferencesLisibles() ?? [
                'products' => null,
                'allergies' => null,
                'pets' => null,
                'notes' => null,
            ],
            // LE BÉNÉFICIAIRE (E1).
            'beneficiary' => $booking?->beneficiary_name === null ? null : [
                'name' => $booking->beneficiary_name,
                'phone' => $booking->beneficiary_phone,
                'note' => $booking->beneficiary_note,
            ],
            // LA CONSIGNE DU CLIENT, ET NON `bookings.notes` — QUE RIEN N'ÉCRIVAIT.
            'notes' => $booking?->customer_comment,
        ];
    }

    /**
     * Ce qu'on répond quand la fiche n'est pas encore ouverte.
     *
     * @return array<string, mixed>
     */
    public function verrouillee(string $raison): array
    {
        return [
            'available' => false,
            'address' => null,
            'floor' => null,
            'access_instructions' => null,
            'live_note' => null,
            'live_note_at' => null,
            'alarm_code_required' => false,
            'access_window' => null,
            // MÊMES CLÉS QUE LA FICHE OUVERTE : un appelant qui doit tester la présence d'une clé
            // avant de la lire finit par en oublier une.
            'preferences' => ['products' => null, 'allergies' => null, 'pets' => null, 'notes' => null],
            'beneficiary' => null,
            'notes' => null,
            'message' => $raison,
        ];
    }

    private function fenetreDAcces(mixed $site): ?string
    {
        $debut = $site?->access_start_time;
        $fin = $site?->access_end_time;

        if (! $debut && ! $fin) {
            return null;
        }

        // Une plage tronquée reste utile : « à partir de 8 h » vaut mieux que rien quand la fin
        // n'est pas renseignée.
        return trim(($debut ? 'de '.$debut : '').($fin ? ' à '.$fin : ''));
    }
}
