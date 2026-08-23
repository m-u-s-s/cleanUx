<?php

namespace App\Services\Missions\OnSite;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionMedia;
use App\Models\NpsResponse;
use App\Models\User;
use App\Notifications\MissionCheckInPingNotification;
use App\Services\Missions\MissionHistoryService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/** LE MODE « JE NE SUIS PAS LÀ » (F14) ET LE PING DE MI-MISSION (F15). */
class MissionCheckInService
{
    public function __construct(
        protected MissionMediaService $mediaService,
    ) {}

    // ── F14 : le mode absent ─────────────────────────────────────────────────

    /**
     * Le client déclare qu'il ne sera pas là.
     *
     * @throws DomainException
     */
    public function declarerAbsence(
        Booking $booking,
        ?string $instructions = null,
        ?string $contactNom = null,
        ?string $contactTelephone = null,
    ): Booking {
        if (blank($instructions)) {
            // Sans consigne, le prestataire arrive devant une porte fermée et rentre chez lui : le
            // mode absent sans instruction d'accès produit exactement l'échec qu'il devait éviter.
            throw new DomainException('Indiquez comment entrer : sans cela, le prestataire repartira.');
        }

        $booking->forceFill([
            'client_absent' => true,
            'client_absent_instructions' => trim($instructions),
            'backup_contact_name' => $contactNom ? trim($contactNom) : null,
            'backup_contact_phone' => $contactTelephone ? trim($contactTelephone) : null,
        ])->save();

        return $booking->fresh();
    }

    /** Le client sera finalement présent : la preuve revient au code à six chiffres. */
    public function annulerAbsence(Booking $booking): Booking
    {
        $booking->forceFill([
            'client_absent' => false,
            'client_absent_instructions' => null,
        ])->save();

        return $booking->fresh();
    }

    /** QUELLE PREUVE DE PRÉSENCE S'APPLIQUE À CETTE MISSION. */
    public function modeDePreuve(Mission $mission): string
    {
        return $mission->booking?->client_absent ? 'photo' : 'code';
    }

    /**
     * DÉMARRER SANS CODE, EN LAISSANT UNE PREUVE.
     *
     * @throws DomainException
     */
    public function preuveDArriveeSansClient(
        Mission $mission,
        User $prestataire,
        UploadedFile $photo,
        ?float $lat = null,
        ?float $lng = null,
    ): MissionMedia {
        if ($this->modeDePreuve($mission) !== 'photo') {
            // Le client est présent : c'est le code qui fait foi. Accepter une photo ici offrirait
            // un contournement à qui préfère ne pas sonner.
            throw new DomainException('Le client est présent : demandez-lui son code à six chiffres.');
        }

        $media = $this->mediaService->capture(
            $mission,
            $prestataire,
            $photo,
            MissionMedia::TYPE_BEFORE_PHOTO,
            $lat,
            $lng,
            caption: 'Preuve d’arrivée (client absent)',
        );

        app(MissionHistoryService::class)->log(
            $mission,
            $prestataire,
            'mission_arrival_photo_proof',
            'Arrivée attestée par photo',
            'Le client avait déclaré son absence.',
            ['media_id' => $media->id],
        );

        return $media;
    }

    // ── F15 : le ping ────────────────────────────────────────────────────────

    /** Envoie le « tout va bien ? » au client. */
    public function envoyerLePing(Mission $mission): bool
    {
        $booking = $mission->booking;
        $client = $booking?->client;

        if (! $booking || ! $client || $booking->checkin_ping_sent_at) {
            return false;
        }

        try {
            $client->notify(new MissionCheckInPingNotification($mission));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        $booking->forceFill(['checkin_ping_sent_at' => now()])->save();

        return true;
    }

    /**
     * La réponse du client, en un geste. UNE RÉPONSE NÉGATIVE ALERTE TOUT DE SUITE.
     *
     * @throws DomainException
     */
    public function repondreAuPing(Booking $booking, User $client, string $reponse): Booking
    {
        if (! in_array($reponse, ['ok', 'probleme'], true)) {
            throw new DomainException('Réponse inconnue.');
        }

        if ($booking->checkin_ping_answered_at) {
            throw new DomainException('Vous avez déjà répondu.');
        }

        $booking->forceFill([
            'checkin_ping_answer' => $reponse,
            'checkin_ping_answered_at' => now(),
        ])->save();

        $this->alimenterLeNps($booking, $client, $reponse);

        if ($reponse === 'probleme') {
            Log::warning('Ping de mi-mission : le client signale un problème', [
                'booking_id' => $booking->id,
                'client_id' => $client->id,
            ]);
        }

        return $booking->fresh();
    }

    /** Le ping alimente le NPS — sans prétendre en être un. */
    protected function alimenterLeNps(Booking $booking, User $client, string $reponse): void
    {
        try {
            $score = $reponse === 'probleme' ? 0 : 9;

            NpsResponse::query()->updateOrCreate(
                ['booking_id' => $booking->id, 'survey_code' => 'mission_checkin'],
                [
                    'user_id' => $client->id,
                    'score' => $score,
                    'category' => $score >= 9
                        ? NpsResponse::CATEGORY_PROMOTER
                        : NpsResponse::CATEGORY_DETRACTOR,
                ],
            );
        } catch (\Throwable $e) {
            // Le NPS est un agrégat : son indisponibilité ne doit pas faire échouer la réponse du
            // client, qui est la donnée qui compte.
            report($e);
        }
    }
}
