<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\Filter;
use App\Models\EnterpriseBookingApproval;
use App\Models\User;
use App\Services\Enterprise\EnterpriseBookingApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Les demandes d'approbation des réservations d'entreprise.
 *
 * DEUX VALIDATIONS DISTINCTES, ET L'ORDRE COMPTE : le manager d'abord, la finance ensuite. Les
 * deux passent par le service, qui vérifie l'enchaînement, horodate chaque étape et débloque la
 * réservation quand les deux sont posées. Écrire `status = 'approved'` d'un coup sauterait la
 * validation manquante — une réservation d'entreprise engagée sans que la finance l'ait vue.
 *
 * LE REFUS RESTE HORS CONSOLE : `reject()` exige un motif, et une commande refusée sans motif
 * écrit laisse le demandeur sans rien à corriger.
 *
 * @implements AdminResource<EnterpriseBookingApproval>
 */
class EnterpriseApprovalResource implements AdminResource
{
    public function __construct(private readonly EnterpriseBookingApprovalService $approvals) {}

    public function key(): string
    {
        return 'enterprise-approvals';
    }

    /** @return Builder<EnterpriseBookingApproval> */
    public function query(): Builder
    {
        return EnterpriseBookingApproval::query()->with(['organizationAccount', 'rendezVous']);
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('company', 'Entreprise'),
            Column::make('status', 'Statut', Column::TYPE_BADGE),
            Column::make('manager_approved_at', 'Manager', Column::TYPE_DATETIME),
            Column::make('finance_approved_at', 'Finance', Column::TYPE_DATETIME),
            Column::make('created_at', 'Demandée le', Column::TYPE_DATE),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Entreprise'),
            Filter::bool('a_traiter', 'En attente seulement'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'created_at'];
    }

    public function actions(): array
    {
        return [
            Action::make('approve-manager', 'Valider (manager)', function (EnterpriseBookingApproval $model) {
                $admin = Auth::user();

                if (! $admin instanceof User) {
                    return ['ok' => false];
                }

                $this->approvals->approveManager($model, $admin, 'Validé depuis la console mobile.');

                return ['ok' => true];
            }),

            Action::make('approve-finance', 'Valider (finance)', function (EnterpriseBookingApproval $model) {
                $admin = Auth::user();

                if (! $admin instanceof User) {
                    return ['ok' => false];
                }

                // Le service refuse cette étape tant que le manager n'a pas validé : l'ordre est
                // sa règle, pas la nôtre.
                $this->approvals->approveFinance($model, $admin, 'Validé depuis la console mobile.');

                return ['ok' => true];
            }),
        ];
    }

    public function formFields(): array
    {
        // Une demande d'approbation naît d'une réservation d'entreprise, pas d'une saisie admin.
        return [];
    }

    /**
     * @param  Builder<EnterpriseBookingApproval>  $query
     * @return Builder<EnterpriseBookingApproval>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->whereHas('organizationAccount', function (Builder $sub) use ($value) {
                $sub->where('name', 'like', '%'.$value.'%');
            }),
            'a_traiter' => $query->whereNull('approved_at')->whereNull('rejected_at'),
            default => $query,
        };
    }

    /** @param  EnterpriseBookingApproval  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'company' => $model->organizationAccount->name ?? '—',
            'status' => $model->status,
            'manager_approved_at' => $model->manager_approved_at?->toIso8601String(),
            'finance_approved_at' => $model->finance_approved_at?->toIso8601String(),
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }

    /** @param  EnterpriseBookingApproval  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'request_note' => $model->request_note,
            'manager_note' => $model->manager_note,
            'finance_note' => $model->finance_note,
            'rejection_reason' => $model->rejection_reason,
        ];
    }
}
