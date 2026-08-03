<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\Filter;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Kyc\KycVerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Les vérifications d'identité à traiter.
 *
 * DÉLÉGATION STRICTE. `approveManually()` et `syncStatus()` portent la règle : journal, décision,
 * horodatage, réveil des modules qui attendaient la vérification. Écrire `status = 'clear'` à la
 * main donnerait un prestataire vérifié que rien n'a vérifié — et personne ne s'en apercevrait
 * avant un contrôle.
 *
 * LE REFUS N'EST PAS UNE ACTION DE CONSOLE, délibérément : `rejectManually()` exige un motif, et
 * un refus d'identité sans motif écrit n'est ni contestable ni auditable. Le moteur ne sait pas
 * demander une valeur avant d'agir — c'est le signe qu'il faut un écran sur-mesure.
 *
 * @implements AdminResource<KycVerification>
 */
class KycResource implements AdminResource
{
    public function __construct(private readonly KycVerificationService $kyc) {}

    public function key(): string
    {
        return 'kyc';
    }

    /** @return Builder<KycVerification> */
    public function query(): Builder
    {
        return KycVerification::query()->with('user');
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('user', 'Personne'),
            Column::make('status', 'Statut', Column::TYPE_BADGE),
            Column::make('decision', 'Décision', Column::TYPE_BADGE),
            Column::make('provider', 'Fournisseur'),
            Column::make('created_at', 'Demandée le', Column::TYPE_DATE),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Nom ou email'),
            Filter::select('status', 'Statut', [
                ['value' => KycVerification::STATUS_PENDING, 'label' => 'En attente'],
                ['value' => KycVerification::STATUS_IN_REVIEW, 'label' => 'En revue'],
                ['value' => KycVerification::STATUS_AWAITING_DOCS, 'label' => 'Documents attendus'],
                ['value' => KycVerification::STATUS_CLEAR, 'label' => 'Validée'],
                ['value' => KycVerification::STATUS_REJECTED, 'label' => 'Refusée'],
            ]),
            Filter::bool('a_traiter', 'À traiter seulement'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'created_at'];
    }

    public function actions(): array
    {
        return [
            Action::make('approve', 'Valider', function (KycVerification $model) {
                $admin = Auth::user();

                if (! $admin instanceof User) {
                    return ['ok' => false];
                }

                $this->kyc->approveManually($model, $admin, 'Validée depuis la console mobile.');

                return ['ok' => true];
            }),

            Action::make('sync', 'Rafraîchir depuis le fournisseur', function (KycVerification $model) {
                // Relit l'état chez le fournisseur d'identité plutôt que de deviner : une
                // vérification peut avoir abouti sans que le webhook soit arrivé.
                $this->kyc->syncStatus($model);

                return ['ok' => true];
            }),
        ];
    }

    public function formFields(): array
    {
        // Une vérification d'identité naît d'une demande du porteur, jamais d'une saisie admin.
        return [];
    }

    /**
     * @param  Builder<KycVerification>  $query
     * @return Builder<KycVerification>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->whereHas('user', function (Builder $sub) use ($value) {
                $sub->where('name', 'like', '%'.$value.'%')
                    ->orWhere('email', 'like', '%'.$value.'%');
            }),
            'status' => $query->where('status', $value),
            // Le scope du modèle plutôt qu'une liste recopiée : il porte déjà la définition de
            // « à traiter », et la recopier la ferait diverger au premier statut ajouté.
            'a_traiter' => $query->pending(),
            default => $query,
        };
    }

    /** @param  KycVerification  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            // Le typage dit la relation non nullable ; les données historiques, elles, ne le
            // garantissent pas — d'où le repli sur « — » plutôt qu'une cellule vide.
            'user' => $model->user->name ?? '—',
            'status' => $model->status,
            'decision' => $model->decision,
            'provider' => $model->provider,
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }

    /** @param  KycVerification  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'email' => $model->user?->email,
            'country_code' => $model->country_code,
            'score' => $model->score,
            'rejection_reason' => $model->rejection_reason,
            'reviewed_at' => $model->reviewed_at?->toIso8601String(),
        ];
    }
}
