<?php

namespace App\Services\Safety;

use App\Models\Mission;
use App\Models\SafetyAlert;
use App\Models\SafetyAlertPing;
use App\Models\User;
use App\Notifications\SafetyAlertRaised;
use App\Services\Notifications\SmsService;
use App\Services\Organizations\OrganizationNotifier;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * LE MODE SÉCURITÉ / SOS (E33).
 *
 * CE QUI EXISTE ET CE QUI MANQUE. Le centre de sécurité traite les SIGNALEMENTS : quelqu'un rapporte
 * un comportement, un administrateur arbitre, des jours plus tard. C'est de la modération. Il
 * n'existe rien pour l'URGENCE — quelqu'un seul chez un inconnu, qui a besoin qu'on sache où il est
 * MAINTENANT.
 *
 * JAMAIS DERRIÈRE UN DRAPEAU. Un bouton d'urgence qu'on peut désactiver par configuration est un
 * bouton dont personne ne peut garantir qu'il répondra. C'est la seule fonctionnalité de ce
 * programme dont l'indisponibilité se compte en intégrité physique.
 *
 * RIEN NE DOIT POUVOIR EMPÊCHER L'ENREGISTREMENT. Notifications, SMS, contact d'urgence : tout est
 * en soft-fail, et l'alerte est écrite AVANT qu'on tente quoi que ce soit. Une alerte perdue parce
 * qu'un serveur de SMS ne répondait pas serait le pire défaut possible de ce module.
 *
 * ON N'EN OUVRE PAS DEUX. Une alerte déjà ouverte est RENDUE plutôt que dupliquée : quelqu'un qui
 * appuie trois fois sur un bouton d'urgence appuie trois fois sur le même bouton, et trois lignes
 * feraient croire à trois personnes en difficulté.
 */
class SafetyAlertService
{
    /**
     * Déclencher une alerte.
     *
     * @param  array<string, mixed>  $position
     */
    public function declencher(
        User $utilisateur,
        string $niveau = SafetyAlert::LEVEL_EMERGENCY,
        ?Mission $mission = null,
        ?string $message = null,
        array $position = [],
    ): SafetyAlert {
        $niveau = in_array($niveau, [SafetyAlert::LEVEL_CHECK_IN, SafetyAlert::LEVEL_EMERGENCY], true)
            ? $niveau
            : SafetyAlert::LEVEL_EMERGENCY;

        $ouverte = $this->alerteOuverteDe($utilisateur);

        if ($ouverte !== null) {
            /*
             * ON N'EN OUVRE PAS DEUX. Quelqu'un qui appuie trois fois sur un bouton d'urgence appuie
             * trois fois sur le même bouton ; trois lignes feraient croire à trois personnes en
             * difficulté, et l'attention se diviserait.
             *
             * L'ESCALADE RESTE POSSIBLE : une veille qui devient une urgence monte de niveau sur la
             * même alerte, et re-prévient.
             */
            if ($niveau === SafetyAlert::LEVEL_EMERGENCY && $ouverte->level !== SafetyAlert::LEVEL_EMERGENCY) {
                $ouverte->forceFill(['level' => SafetyAlert::LEVEL_EMERGENCY])->save();
                $this->prevenir($ouverte->fresh(), $utilisateur);
            }

            return $ouverte->fresh();
        }

        $profil = $utilisateur->providerProfile;

        // ÉCRITE D'ABORD, notifiée ensuite. Une alerte perdue parce qu'un serveur de SMS ne
        // répondait pas serait le pire défaut possible de ce module.
        $alerte = SafetyAlert::query()->create([
            'user_id' => $utilisateur->id,
            'mission_id' => $mission?->id,
            'booking_id' => $mission?->booking_id,
            'level' => $niveau,
            'status' => SafetyAlert::STATUS_OPEN,
            'message' => $message,
            'lat' => $position['lat'] ?? null,
            'lng' => $position['lng'] ?? null,
            'accuracy_m' => $position['accuracy_m'] ?? null,
            // Recopié sur l'alerte : le profil peut changer, et une alerte se relit des mois plus
            // tard avec le contact qui valait CE jour-là.
            'emergency_contact_name' => $profil?->emergency_contact_name,
            'emergency_contact_phone' => $profil?->emergency_contact_phone,
        ]);

        if (isset($position['lat'], $position['lng'])) {
            $this->pointer($alerte, (float) $position['lat'], (float) $position['lng'], $position['accuracy_m'] ?? null);
        }

        $this->prevenir($alerte, $utilisateur);

        return $alerte->fresh();
    }

    /**
     * Une position de plus pendant l'alerte.
     *
     * `pinged_at` PORTE L'HEURE DU RELEVÉ, pas celle de l'écriture : un téléphone hors réseau
     * accumule et envoie plus tard, et confondre les deux placerait toute la trace au moment où la
     * connexion est revenue.
     */
    public function pointer(
        SafetyAlert $alerte,
        float $lat,
        float $lng,
        ?int $precision = null,
        ?Carbon $releveA = null,
    ): SafetyAlertPing {
        $ping = SafetyAlertPing::query()->create([
            'safety_alert_id' => $alerte->id,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $precision,
            'pinged_at' => $releveA ?? now(),
        ]);

        // La dernière position connue vit aussi sur l'alerte : celui qui l'ouvre en urgence ne doit
        // pas avoir à parcourir une table pour savoir où aller.
        $alerte->forceFill(['lat' => $lat, 'lng' => $lng, 'accuracy_m' => $precision])->save();

        return $ping;
    }

    /**
     * Un administrateur a VU l'alerte.
     *
     * C'EST CE QUE LA PERSONNE SUR PLACE ATTEND DE SAVOIR EN PREMIER — plus que la résolution.
     * Savoir qu'on est seul est ce qui rend une situation effrayante.
     */
    public function accuserReception(SafetyAlert $alerte, User $admin): SafetyAlert
    {
        if (! $alerte->estOuverte()) {
            return $alerte;
        }

        $alerte->forceFill([
            'status' => SafetyAlert::STATUS_ACKNOWLEDGED,
            'acknowledged_by_user_id' => $admin->id,
            'acknowledged_at' => now(),
        ])->save();

        try {
            app(OrganizationNotifier::class)->notifierUtilisateur(
                $alerte->user_id,
                'Votre alerte a été vue',
                'Un membre de l’équipe sécurité suit votre situation.',
                ['safety_alert_id' => $alerte->id],
                'safety:ack:'.$alerte->id,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $alerte->fresh();
    }

    /** @throws DomainException */
    public function cloturer(SafetyAlert $alerte, User $acteur, bool $fausseAlerte = false, ?string $note = null): SafetyAlert
    {
        if (! $alerte->estOuverte()) {
            throw new DomainException('Cette alerte est déjà close.');
        }

        $alerte->forceFill([
            // Une fausse alerte se conserve : l'effacer empêcherait de voir qu'un bouton se
            // déclenche tout seul dans une poche.
            'status' => $fausseAlerte ? SafetyAlert::STATUS_FALSE_ALARM : SafetyAlert::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_note' => $note,
            'metadata' => array_merge((array) $alerte->metadata, ['closed_by' => $acteur->id]),
        ])->save();

        return $alerte->fresh();
    }

    /** L'alerte encore ouverte de quelqu'un, s'il y en a une. */
    public function alerteOuverteDe(User $utilisateur): ?SafetyAlert
    {
        return SafetyAlert::query()
            ->where('user_id', $utilisateur->id)
            ->whereIn('status', [SafetyAlert::STATUS_OPEN, SafetyAlert::STATUS_ACKNOWLEDGED])
            ->latest('id')
            ->first();
    }

    /**
     * Les alertes ouvertes, pour le centre de sécurité — les urgences d'abord.
     *
     * @return Collection<int, SafetyAlert>
     */
    public function ouvertes(): Collection
    {
        return SafetyAlert::query()
            ->whereIn('status', [SafetyAlert::STATUS_OPEN, SafetyAlert::STATUS_ACKNOWLEDGED])
            ->with(['user:id,name,phone', 'mission:id,booking_id'])
            ->orderByRaw("CASE WHEN level = 'emergency' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Prévenir — la plateforme, puis le contact d'urgence.
     *
     * TOUT EN SOFT-FAIL, ET DANS CET ORDRE. L'alerte est déjà écrite : rien de ce qui suit ne doit
     * pouvoir la faire échouer. Un canal qui tombe ne doit pas empêcher les autres de partir.
     */
    protected function prevenir(SafetyAlert $alerte, User $utilisateur): void
    {
        try {
            /*
             * L'ÉQUIPE SÉCURITÉ D'ABORD. Ce sont eux qui peuvent agir — appeler, envoyer quelqu'un,
             * alerter les secours. Le contact d'urgence, lui, est un proche : il rassure, il
             * n'intervient pas.
             */
            $this->notifierLEquipeSecurite($alerte, $utilisateur);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($alerte->level !== SafetyAlert::LEVEL_EMERGENCY) {
            // Une veille ne dérange pas les proches : elle demande qu'on garde un œil, pas qu'on
            // s'inquiète.
            return;
        }

        try {
            $this->prevenirLeContactDUrgence($alerte, $utilisateur);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifierLEquipeSecurite(SafetyAlert $alerte, User $utilisateur): void
    {
        $admins = User::query()
            ->whereIn('platform_role', ['admin', 'super_admin'])
            ->where('is_active', true)
            ->get();

        if ($admins->isEmpty()) {
            Log::warning('[safety] alerte sans destinataire administrateur', ['alert_id' => $alerte->id]);

            return;
        }

        Notification::send($admins, new SafetyAlertRaised($alerte, $utilisateur));
    }

    /**
     * Le proche est prévenu par SMS, jamais par notification interne : il n'a pas l'application.
     *
     * LE MESSAGE NE DIT PAS OÙ. Une position transmise à un tiers sans que la personne l'ait
     * explicitement voulu serait une divulgation ; le proche apprend qu'il se passe quelque chose et
     * qui appeler. C'est l'équipe sécurité qui dispose de la position.
     */
    protected function prevenirLeContactDUrgence(SafetyAlert $alerte, User $utilisateur): void
    {
        $telephone = $alerte->emergency_contact_phone;

        if (! $telephone) {
            return;
        }

        app(SmsService::class)->send(
            $telephone,
            sprintf(
                '%s a déclenché une alerte de sécurité. L’équipe %s a été prévenue et le suit.',
                $utilisateur->name ?? 'Un proche',
                config('app.name', 'la plateforme'),
            ),
        );

        $alerte->forceFill(['contact_notified_at' => now()])->save();
    }
}
