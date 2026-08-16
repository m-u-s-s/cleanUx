<?php

namespace App\Services\FaceCheck;

use App\Models\Booking;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use App\Services\FaceCheck\Data\FaceCheckDecision;

/**
 * « CE PRESTATAIRE PEUT-IL ALLER CHEZ UN CLIENT MAINTENANT ? » — une seule réponse, un seul endroit.
 *
 * Sept points de passage mènent un prestataire vers un client : la mise en ligne (deux services
 * distincts), la constitution des candidats au dispatch, la fabrication d'une offre, l'acceptation,
 * le départ vers le client, et l'affectation interne d'une société. Ils posent tous la même
 * question ; si chacun se répondait à lui-même, six d'entre eux finiraient par répondre autrement
 * que le septième, et la porte se contournerait par celui qui aurait été oublié.
 *
 * CETTE CLASSE NE MODIFIE RIEN. Elle lit et rend un verdict. Ouvrir un contrôle est un geste du
 * prestataire, pas un effet de bord d'une requête de dispatch — sinon un balayage de candidats
 * ouvrirait des dizaines de contrôles à des gens qui ne regardent même pas leur téléphone.
 */
class FaceCheckGate
{
    public function __construct(
        private readonly FaceCheckRequirement $requirement,
        private readonly FaceCheckScheduler $scheduler,
        private readonly FaceCheckSettings $settings,
    ) {}

    /**
     * La porte générale : mise en ligne, départ vers le client, surface API prestataire.
     */
    public function inspectProvider(User $provider, ?string $deviceName = null): FaceCheckDecision
    {
        if (! $this->requirement->appliesToProvider($provider)) {
            return FaceCheckDecision::ok();
        }

        return $this->verdict($provider, $deviceName);
    }

    /**
     * La porte de la mission : acceptation d'une offre, affectation interne.
     *
     * On interroge la RÉSERVATION, pas le prestataire : un intervenant peut être hors périmètre
     * (aucun de ses métiers ne l'exige) et se voir tout de même confier une mission d'un métier
     * qui, lui, l'exige — par une affectation interne de société, par exemple. C'est le client
     * final qui doit être protégé, pas le profil.
     */
    public function inspectForBooking(User $provider, Booking $booking): FaceCheckDecision
    {
        $soumisParLaMission = $this->requirement->appliesToBooking($booking);
        $soumisParSonProfil = $this->requirement->appliesToProvider($provider);

        if (! $soumisParLaMission && ! $soumisParSonProfil) {
            return FaceCheckDecision::ok();
        }

        return $this->verdict($provider, null);
    }

    private function verdict(User $provider, ?string $deviceName): FaceCheckDecision
    {
        $profil = ProviderFaceProfile::query()->where('user_id', $provider->id)->first();

        if ($profil === null || ! $profil->isEnrolled() || ! $profil->hasActiveConsent()) {
            return $this->enrolementRequis();
        }

        if ($profil->isBlocked()) {
            return new FaceCheckDecision(
                code: FaceCheckDecision::BLOCKED,
                message: "Votre compte est suspendu à la suite d'un contrôle d'identité. "
                    ."Un administrateur doit lever la suspension : signalez-le depuis l'écran de vérification.",
            );
        }

        /*
         * Un contrôle DÉJÀ OUVERT prime sur la cadence. Sans cette clause, chaque appel rendrait
         * « contrôle requis » et le client rouvrirait un contrôle par requête : le prestataire
         * accumulerait des contrôles abandonnés — et finirait signalé pour fraude par le module
         * lui-même. C'est le genre de boucle qui ne se voit qu'en production.
         */
        $ouvert = ProviderFaceCheck::query()
            ->where('provider_face_profile_id', $profil->id)
            ->where('status', ProviderFaceCheck::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->latest('requested_at')
            ->first();

        if ($ouvert !== null) {
            // Répondu mais pas encore tranché par le fournisseur : on attend, porte fermée.
            if ($ouvert->answered_at !== null) {
                return new FaceCheckDecision(
                    code: FaceCheckDecision::CHECK_PENDING,
                    message: 'Votre contrôle est en cours de vérification. Encore quelques secondes.',
                    checkId: $ouvert->id,
                );
            }

            return new FaceCheckDecision(
                code: FaceCheckDecision::CHECK_REQUIRED,
                message: 'Un contrôle de votre identité est nécessaire avant de continuer.',
                checkId: $ouvert->id,
                trigger: $ouvert->triggered_by,
            );
        }

        $motif = $this->scheduler->dueTrigger($profil, $deviceName);

        if ($motif !== null) {
            return new FaceCheckDecision(
                code: FaceCheckDecision::CHECK_REQUIRED,
                message: 'Un contrôle de votre identité est nécessaire avant de continuer.',
                trigger: $motif,
            );
        }

        return FaceCheckDecision::ok();
    }

    /**
     * LA GRÂCE SE COMPTE DEPUIS L'ALLUMAGE DU MODULE, PAS DEPUIS L'INSCRIPTION.
     *
     * Une grâce comptée depuis l'inscription protégerait les nouveaux venus — ceux dont on sait le
     * moins de choses — et laisserait les prestataires déjà installés bloqués du jour au lendemain.
     * C'est l'inverse de ce qu'on veut : la grâce sert à absorber l'allumage du module, pas à ouvrir
     * une fenêtre pour les arrivants.
     *
     * Défaut à zéro jour : un selfie prend trente secondes, contrairement à un permis qu'il faut
     * aller chercher. Une grâce sur l'enrôlement est une protection qui n'existe pas.
     */
    private function enrolementRequis(): FaceCheckDecision
    {
        $grace = $this->settings->enrolmentGraceDays();

        if ($grace > 0) {
            $allumage = $this->settings->module()?->updated_at;

            if ($allumage !== null && $allumage->copy()->addDays($grace)->isFuture()) {
                return FaceCheckDecision::ok();
            }
        }

        return new FaceCheckDecision(
            code: FaceCheckDecision::ENROLMENT_REQUIRED,
            message: "Enregistrez votre visage pour pouvoir intervenir chez des clients. C'est l'affaire de trente secondes.",
        );
    }
}
