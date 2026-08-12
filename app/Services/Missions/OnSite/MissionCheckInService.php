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

/**
 * LE MODE « JE NE SUIS PAS LÀ » (F14) ET LE PING DE MI-MISSION (F15).
 *
 * F14 — LA PREUVE DE PRÉSENCE SUPPOSE UN CLIENT PRÉSENT, et c'est la limite du dispositif actuel.
 * Le code à six chiffres est affiché par le client et saisi par le prestataire : il atteste que les
 * deux personnes sont face à face. Parfait quand c'est vrai, impossible quand le client travaille et
 * laisse la clé chez la voisine — c'est-à-dire le cas ordinaire du ménage à domicile.
 *
 * Ces interventions se déroulaient donc HORS du dispositif : soit le prestataire ne pouvait pas
 * démarrer, soit quelqu'un contournait. Déclarer son absence à l'avance bascule la preuve sur une
 * photo d'arrivée horodatée et géolocalisée : moins forte qu'une confrontation, infiniment plus
 * qu'un contournement non tracé.
 *
 * LA DÉCLARATION EST FAITE PAR LE CLIENT, JAMAIS PAR LE PRESTATAIRE. C'est la garantie qui tient
 * tout : si celui qui doit prouver sa présence pouvait décider que la preuve ne s'applique pas, il
 * n'y aurait plus de preuve du tout.
 *
 * F15 — LE PING EST UNE QUESTION, PAS UN FORMULAIRE. « Tout va bien ? », une réponse en un geste. Il
 * vaut par son MOMENT : au milieu, quand il est encore temps de corriger. Après, il ne reste que
 * l'avis à écrire et le litige à ouvrir — et les deux coûtent bien plus cher à tout le monde qu'un
 * mot dit au prestataire pendant qu'il est là.
 */
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

    /**
     * QUELLE PREUVE DE PRÉSENCE S'APPLIQUE À CETTE MISSION.
     *
     * `code` par défaut, `photo` quand le client a déclaré son absence. Le rendre explicite plutôt
     * que de le déduire à trois endroits : deux écrans qui devinent différemment finiraient par
     * demander une preuve à quelqu'un qui ne peut pas la donner.
     */
    public function modeDePreuve(Mission $mission): string
    {
        return $mission->booking?->client_absent ? 'photo' : 'code';
    }

    /**
     * DÉMARRER SANS CODE, EN LAISSANT UNE PREUVE.
     *
     * La photo d'arrivée porte l'horodatage, la position et l'empreinte : c'est ce qui distingue un
     * démarrage tracé d'un démarrage déclaratif. Sans elle, le mode absent ne serait qu'un bouton
     * « je suis arrivé » que rien ne contredit.
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

    /**
     * Envoie le « tout va bien ? » au client.
     *
     * Une seule fois par intervention : répéter la question transforme une attention en
     * harcèlement, et personne ne répond à la troisième.
     */
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
     * La réponse du client, en un geste.
     *
     * UNE RÉPONSE NÉGATIVE ALERTE TOUT DE SUITE. C'est toute la raison d'être du ping : signaler
     * pendant qu'on peut encore corriger. La transmettre au registre NPS sans prévenir personne
     * reviendrait à collecter un mécontentement pour l'analyser plus tard.
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

    /**
     * Le ping alimente le NPS — sans prétendre en être un.
     *
     * Deux réponses ne font pas une note sur dix : on enregistre les extrêmes (0 pour un problème,
     * 9 pour un « tout va bien ») et la nuance viendra de l'avis, plus tard. Prétendre à une
     * précision qu'on n'a pas fausserait la moyenne de tout le monde.
     */
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
