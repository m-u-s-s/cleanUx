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

/** LE MODE SÉCURITÉ / SOS (E33). CE QUI EXISTE ET CE QUI MANQUE. */
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
            // ON N'EN OUVRE PAS DEUX.
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

    /** Une position de plus pendant l'alerte. */
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

    /** Un administrateur a VU l'alerte. */
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

    /** Prévenir — la plateforme, puis le contact d'urgence. TOUT EN SOFT-FAIL, ET DANS CET ORDRE. */
    protected function prevenir(SafetyAlert $alerte, User $utilisateur): void
    {
        try {
            // L'ÉQUIPE SÉCURITÉ D'ABORD.
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

    /** Le proche est prévenu par SMS, jamais par notification interne : il n'a pas l'application. */
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
