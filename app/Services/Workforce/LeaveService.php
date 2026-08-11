<?php

namespace App\Services\Workforce;

use App\Models\LeaveRequest;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Organizations\OrganizationNotifier;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * LES CONGÉS ET ABSENCES (E21) — poser, approuver, refuser.
 *
 * TOUT L'INTÉRÊT EST AILLEURS QUE DANS CE SERVICE : une demande approuvée doit empêcher
 * l'assignation, et c'est `WorkerAvailabilityService` qui le fait. Un module de congés qui ne
 * parlerait qu'à lui-même produirait un joli tableau et enverrait quand même la course au salarié le
 * premier jour de ses vacances.
 *
 * ON NE POSE PAS UN CONGÉ POUR QUELQU'UN D'AUTRE, et on n'approuve pas le sien. Les deux gardes
 * disent la même chose sous deux angles : une absence engage deux personnes, celle qui s'absente et
 * celle qui assume le trou dans le planning.
 */
class LeaveService
{
    public function __construct(
        protected OrganizationNotifier $notifier,
    ) {}

    /**
     * Poser une demande.
     *
     * @throws DomainException
     */
    public function demander(
        User $demandeur,
        int $organisationId,
        Carbon $debut,
        Carbon $fin,
        string $type = 'paid',
        ?string $motif = null,
    ): LeaveRequest {
        if ($fin->lessThan($debut)) {
            throw new DomainException('La fin d’une absence ne peut pas précéder son début.');
        }

        $estDeLaMaison = OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('user_id', $demandeur->id)
            ->where('status', 'active')
            ->exists();

        if (! $estDeLaMaison) {
            // Sans cette garde, on poserait des congés dans le planning d'une société qui ne nous
            // emploie pas — et un salarié en disparaîtrait sans que personne ne l'ait décidé.
            throw new DomainException('Cette personne n’appartient pas à cette société.');
        }

        $chevauche = LeaveRequest::query()
            ->where('user_id', $demandeur->id)
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->where('starts_on', '<=', $fin->toDateString())
            ->where('ends_on', '>=', $debut->toDateString())
            ->exists();

        if ($chevauche) {
            /*
             * DEUX ABSENCES QUI SE CHEVAUCHENT NE VEULENT RIEN DIRE. Le responsable devrait deviner
             * laquelle fait foi, et le décompte de jours — celui qui finit sur la fiche de paie —
             * compterait deux fois la même semaine.
             */
            throw new DomainException('Une demande couvre déjà tout ou partie de ces dates.');
        }

        $demande = LeaveRequest::query()->create([
            'organization_account_id' => $organisationId,
            'user_id' => $demandeur->id,
            'type' => $type,
            'starts_on' => $debut->toDateString(),
            'ends_on' => $fin->toDateString(),
            'status' => LeaveRequest::STATUS_PENDING,
            'reason' => $motif,
        ]);

        try {
            // Prévenir ceux qui peuvent trancher : une demande que personne ne voit reste en attente
            // jusqu'au jour du départ.
            $this->notifier->notifierPorteursDe(
                organisationId: $organisationId,
                permission: 'team.manage',
                titre: 'Demande d’absence : '.$demandeur->name,
                corps: sprintf(
                    'Du %s au %s.',
                    $debut->format('d/m/Y'),
                    $fin->format('d/m/Y'),
                ),
                donnees: ['leave_request_id' => $demande->id],
                cleIdempotence: 'leave:requested:'.$demande->id,
            );
        } catch (\Throwable $e) {
            // La demande existe : une notification qui échoue ne doit pas l'effacer.
            report($e);
        }

        return $demande;
    }

    /**
     * Approuver ou refuser.
     *
     * @throws DomainException
     */
    public function statuer(LeaveRequest $demande, User $responsable, bool $approuve, ?string $note = null): LeaveRequest
    {
        if ($demande->status !== LeaveRequest::STATUS_PENDING) {
            throw new DomainException('Cette demande a déjà été traitée.');
        }

        if ((int) $demande->user_id === (int) $responsable->id) {
            // S'approuver soi-même viderait l'approbation de son sens : le planning se viderait sans
            // que personne n'ait accepté d'assumer le trou.
            throw new DomainException('Une absence ne s’approuve pas soi-même.');
        }

        $demande->forceFill([
            'status' => $approuve ? LeaveRequest::STATUS_APPROVED : LeaveRequest::STATUS_REJECTED,
            'decided_by_user_id' => $responsable->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        return $demande->fresh();
    }

    /**
     * Le demandeur retire sa demande.
     *
     * Possible même APRÈS approbation : un congé annulé rend la personne assignable, ce qui est
     * exactement ce qu'on veut quand quelqu'un renonce à ses vacances.
     *
     * @throws DomainException
     */
    public function annuler(LeaveRequest $demande, User $acteur): LeaveRequest
    {
        if ((int) $demande->user_id !== (int) $acteur->id) {
            throw new DomainException('Seul le demandeur peut retirer sa demande.');
        }

        if ($demande->status === LeaveRequest::STATUS_REJECTED) {
            throw new DomainException('Une demande refusée n’a plus rien à annuler.');
        }

        $demande->forceFill(['status' => LeaveRequest::STATUS_CANCELLED])->save();

        return $demande->fresh();
    }

    /**
     * Les absences d'une société sur une période — pour l'écran et pour l'API.
     *
     * @return Collection<int, LeaveRequest>
     */
    public function surLaPeriode(int $organisationId, Carbon $debut, Carbon $fin, ?string $statut = null): Collection
    {
        return LeaveRequest::query()
            ->where('organization_account_id', $organisationId)
            ->when($statut, fn ($q) => $q->where('status', $statut))
            ->where('starts_on', '<=', $fin->toDateString())
            ->where('ends_on', '>=', $debut->toDateString())
            ->with('user:id,name')
            ->orderBy('starts_on')
            ->get();
    }
}
