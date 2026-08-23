<?php

namespace App\Services\Client\Calendar;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Organizations\OrganizationNotifier;
use App\Services\Organizations\ProviderOrganisationResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Phase 6.1 — Service de reprogrammation d'un booking via drag-and-drop. */
class BookingRescheduleService
{
    /** Le client qui s'arrange, ou l'organisation cliente. */
    public const CONTEXTE_CLIENT = 'client';

    /** La société prestataire qui réorganise sa tournée. */
    public const CONTEXTE_PRESTATAIRE = 'provider';

    /**
     * Reprogramme un booking à une nouvelle date/heure.
     *
     * @throws \DomainException si non autorisé
     */
    public function reschedule(
        User $user,
        Booking $booking,
        Carbon $newDate,
        ?string $newTime = null,
        ?string $reason = null,
    ): Booking {
        $this->authorize($user, $booking);
        $this->validateNewSchedule($booking, $newDate, $newTime);

        return DB::transaction(function () use ($user, $booking, $newDate, $newTime, $reason) {
            $oldDate = $booking->scheduled_date;
            $oldTime = $booking->scheduled_time;

            $booking->update([
                'scheduled_date' => $newDate->toDateString(),
                'scheduled_time' => $newTime ?: $booking->scheduled_time,
            ]);

            // Audit dans la table d'historique (créée par la migration Phase 6.1)
            $this->logHistory($user, $booking, $oldDate, $oldTime, $newDate, $newTime, $reason);

            return $booking->fresh();
        });
    }

    /** La règle d'appartenance à une réservation. */
    public function peutAcceder(User $user, Booking $booking): bool
    {
        // Admin plateforme : OK
        if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) {
            return true;
        }

        // Le user doit être client direct ou membre de l'org cliente
        $isOwner = (int) ($booking->customer_user_id ?? 0) === (int) $user->id
            || (int) ($booking->client_id ?? 0) === (int) $user->id;

        $isOrgMember = $booking->customer_organization_id
            && $user->organization_account_id
            && (int) $booking->customer_organization_id === (int) $user->organization_account_id;

        return $isOwner || $isOrgMember;
    }

    protected function authorize(User $user, Booking $booking): void
    {
        if (! $this->peutAcceder($user, $booking)) {
            throw new \DomainException("Vous n'avez pas accès à cette réservation.");
        }
    }

    /** Vérifie que la nouvelle date est cohérente : - dans le futur (au moins +30 minutes) - le booking n'est pas déjà terminé/annulé/sur place - la date n'est pas plus de 6 mois dans le futur */
    protected function validateNewSchedule(Booking $booking, Carbon $newDate, ?string $newTime): void
    {
        // Bookings finals : pas reprogrammables
        $finalStatuses = ['termine', 'completed', 'done', 'annule', 'cancelled', 'refuse', 'sur_place', 'on_site'];
        if (in_array((string) $booking->status, $finalStatuses, true)) {
            throw new \DomainException(
                "Cette réservation ne peut plus être reprogrammée (statut: {$booking->status})."
            );
        }

        // Construire le datetime cible
        $time = $newTime ?: ($booking->scheduled_time
            ? Carbon::parse($booking->scheduled_time)->format('H:i')
            : '08:00');

        $target = Carbon::parse($newDate->toDateString().' '.$time);

        // Pas dans le passé
        if ($target->lessThan(now()->addMinutes(30))) {
            throw new \DomainException(
                'La nouvelle date doit être au moins 30 minutes dans le futur.'
            );
        }

        // Pas trop loin dans le futur (sécurité contre erreur de drag)
        if ($target->greaterThan(now()->addMonths(6))) {
            throw new \DomainException(
                'La nouvelle date ne peut pas dépasser 6 mois dans le futur.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $lieu  colonnes de lieu, vides quand seul l'horaire change
     */
    protected function logHistory(
        User $user,
        Booking $booking,
        $oldDate,
        $oldTime,
        Carbon $newDate,
        ?string $newTime,
        ?string $reason,
        string $actorContext = self::CONTEXTE_CLIENT,
        array $lieu = [],
    ): void {
        try {
            DB::table('booking_reschedule_history')->insert(array_merge([
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'old_date' => $oldDate instanceof Carbon ? $oldDate->toDateString() : (string) $oldDate,
                'old_time' => $oldTime ? Carbon::parse($oldTime)->format('H:i:s') : null,
                'new_date' => $newDate->toDateString(),
                'new_time' => $newTime ? $newTime.':00' : null,
                'reason' => $reason,
                // À QUEL TITRE. Le client qui s'arrange, l'admin qui corrige et le prestataire qui
                // réorganise sa tournée ne sont pas la même chose — et une réclamation se règle
                // précisément sur cette distinction.
                'actor_context' => $actorContext,
                'created_at' => now(),
                'updated_at' => now(),
            ], $lieu));
        } catch (\Throwable $e) {
            // La table peut ne pas exister yet → log mais ne bloque pas
            \Log::warning('booking_reschedule_history insert failed: '.$e->getMessage());
        }
    }

    /**
     * REPROGRAMMER CÔTÉ PRESTATAIRE — date, heure ET LIEU.
     *
     * @param  ?int  $nouveauSiteId  autre local du même client (B2B)
     * @param  ?string  $nouvelleAdresse  adresse libre (B2C)
     *
     * @throws \DomainException si la fenêtre de gel s'y oppose ou si le lieu n'est pas légitime
     */
    public function reprogrammerParPrestataire(
        Booking $rendezVous,
        User $acteur,
        Carbon $nouvelleDate,
        ?string $nouvelleHeure = null,
        ?int $nouveauSiteId = null,
        ?string $nouvelleAdresse = null,
        ?string $motif = null,
    ): Booking {
        $this->validateNewSchedule($rendezVous, $nouvelleDate, $nouvelleHeure);
        $this->exigerLaFenetreDeGel($rendezVous, $acteur, $motif);

        $site = $this->siteLegitime($rendezVous, $nouveauSiteId);

        return DB::transaction(function () use (
            $rendezVous, $acteur, $nouvelleDate, $nouvelleHeure, $site, $nouvelleAdresse, $motif
        ) {
            $ancienneDate = $rendezVous->scheduled_date;
            $ancienneHeure = $rendezVous->scheduled_time;
            $ancienSiteId = $rendezVous->organization_site_id;
            $ancienneAdresse = $rendezVous->adresse;

            $changements = [
                'scheduled_date' => $nouvelleDate->toDateString(),
                'scheduled_time' => $nouvelleHeure ?: $rendezVous->scheduled_time,
                // LES COLONNES LEGACY SUIVENT, et ce n'est pas cosmétique : `date` et `heure` sont ce que lit `MissionFromRendezVousSyncService` pour recaler `planned_start_at`.
                'date' => $nouvelleDate->toDateString(),
                'heure' => $nouvelleHeure ?: $rendezVous->heure,
            ];

            if ($site !== null) {
                $changements['organization_site_id'] = $site->id;
                $changements['adresse'] = $site->address ?? $rendezVous->adresse;
                $changements['ville'] = $site->city ?? $rendezVous->ville;
                $changements['code_postal'] = $site->postal_code ?? $rendezVous->code_postal;
            } elseif ($nouvelleAdresse !== null) {
                $changements['adresse'] = $nouvelleAdresse;
            }

            // `update()` DÉCLENCHE L'OBSERVATEUR, et c'est exactement ce qu'on veut ici : la propagation vers la mission existe déjà — `RendezVousObserver` resynchronise les `planned_*` et relance le géocodage.
            $rendezVous->update($changements);

            $this->logHistory(
                $acteur,
                $rendezVous,
                $ancienneDate,
                $ancienneHeure,
                $nouvelleDate,
                $nouvelleHeure,
                $motif,
                self::CONTEXTE_PRESTATAIRE,
                [
                    'old_site_id' => $ancienSiteId,
                    'new_site_id' => $site !== null ? $site->id : $ancienSiteId,
                    'old_address' => $ancienneAdresse,
                    'new_address' => $changements['adresse'] ?? $ancienneAdresse,
                ],
            );

            $this->prevenirDuDeplacement($rendezVous->fresh(), $motif);

            return $rendezVous->fresh();
        });
    }

    /**
     * LA FENÊTRE DE GEL — sous 24 h, seuls le propriétaire et le directeur d'opérations décident.
     *
     * @throws \DomainException
     */
    protected function exigerLaFenetreDeGel(Booking $rendezVous, User $acteur, ?string $motif): void
    {
        $debut = $this->debutPrevu($rendezVous);

        if ($debut === null) {
            return;
        }

        $heures = (int) config('provider_reschedule.freeze_window_hours', 24);

        if ($debut->greaterThan(now()->addHours($heures))) {
            return;
        }

        $organisationId = $rendezVous->assigned_provider_organization_id
            ?? app(ProviderOrganisationResolver::class)->pourUtilisateur($rendezVous->employe_id);

        $membre = $organisationId === null ? null : OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('user_id', $acteur->id)
            ->where('status', 'active')
            ->first();

        $rolesAutorises = config('provider_reschedule.freeze_window_roles', ['owner', 'operations_manager']);

        if ($membre === null || ! in_array($membre->role->value, $rolesAutorises, true)) {
            throw new \DomainException(
                "À moins de {$heures} h de l'intervention, seuls le propriétaire et le directeur d'opérations peuvent la déplacer."
            );
        }

        if ($motif === null || trim($motif) === '') {
            throw new \DomainException('Un motif est obligatoire pour un déplacement de dernière minute.');
        }
    }

    /**
     * Le nouveau site, s'il est légitime. UN AUTRE LOCAL DU MÊME CLIENT, jamais n'importe lequel.
     *
     * @throws \DomainException
     */
    protected function siteLegitime(Booking $rendezVous, ?int $siteId): ?OrganizationSite
    {
        if ($siteId === null) {
            return null;
        }

        $site = OrganizationSite::query()->find($siteId);

        if ($site === null
            || $rendezVous->organization_account_id === null
            || (int) $site->organization_account_id !== (int) $rendezVous->organization_account_id) {
            throw new \DomainException("Ce site n'appartient pas au client de cette réservation.");
        }

        return $site;
    }

    /** Prévenir le client ET le travailleur assigné. */
    protected function prevenirDuDeplacement(Booking $rendezVous, ?string $motif): void
    {
        // `scheduled_date` est CASTÉ en date par le modèle : l'accès rend toujours un Carbon.
        $quand = trim(
            $rendezVous->scheduled_date?->format('d/m')
            .' '.substr((string) ($rendezVous->heure ?? ''), 0, 5)
        );

        $corps = $motif !== null && trim($motif) !== ''
            ? "Votre intervention est déplacée au {$quand} : {$motif}"
            : "Votre intervention est déplacée au {$quand}.";

        $notifier = app(OrganizationNotifier::class);

        $notifier->notifierUtilisateur(
            userId: $rendezVous->customer_user_id ?? $rendezVous->client_id,
            titre: 'Intervention déplacée',
            corps: $corps,
            donnees: ['type' => 'booking_rescheduled', 'booking_id' => $rendezVous->id],
        );

        $assigne = Mission::query()
            ->where('booking_id', $rendezVous->id)
            ->value('lead_provider_user_id');

        if ($assigne !== null) {
            $notifier->notifierUtilisateur(
                userId: (int) $assigne,
                titre: 'Mission déplacée',
                corps: $corps,
                donnees: ['type' => 'mission_rescheduled', 'booking_id' => $rendezVous->id],
            );
        }
    }

    /** L'heure de début prévue, sous forme comparable. */
    protected function debutPrevu(Booking $rendezVous): ?Carbon
    {
        // `scheduled_date` est CASTÉ en date : son accès rend un Carbon ou `null`, jamais une chaîne.
        $jour = $rendezVous->scheduled_date?->toDateString()
            ?? substr((string) $rendezVous->date, 0, 10);

        if ($jour === '') {
            return null;
        }

        $heure = substr((string) ($rendezVous->heure ?? '08:00:00'), 0, 8);

        try {
            return Carbon::parse($jour.' '.$heure);
        } catch (\Throwable) {
            return null;
        }
    }
}
