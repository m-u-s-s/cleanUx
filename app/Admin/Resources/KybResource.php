<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\DefaultsResourceWrites;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use App\Models\BusinessEntity;
use App\Models\User;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Les vérifications d'entreprise (KYB) à traiter.
 *
 * TROIS ACTIONS, TOUTES DÉLÉGUÉES au service d'onboarding : relancer les vérifications, relancer
 * le criblage de sanctions, approuver. Chacune recalcule le score de risque et journalise — un
 * `status = 'verified'` écrit à la main donnerait une entreprise approuvée sans criblage, ce qui
 * est exactement ce que la conformité interdit.
 *
 * LE REFUS EXIGE UN MOTIF d'au moins dix caractères, et l'action le DÉCLARE : le moteur ouvre une
 * feuille de saisie et valide avant d'appeler `reject()`. Un refus sans motif écrit n'est ni
 * contestable ni auditable.
 *
 * @implements AdminResource<BusinessEntity>
 */
class KybResource implements AdminResource
{
    use DefaultsResourceWrites;

    public function __construct(private readonly BusinessOnboardingService $onboarding) {}

    public function key(): string
    {
        return 'kyb';
    }

    /** @return Builder<BusinessEntity> */
    public function query(): Builder
    {
        return BusinessEntity::query();
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('legal_name', 'Raison sociale'),
            Column::make('status', 'Statut', Column::TYPE_BADGE),
            Column::make('risk_score', 'Risque', Column::TYPE_NUMBER),
            Column::make('country_code', 'Pays'),
            Column::make('created_at', 'Déposée le', Column::TYPE_DATE),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Raison sociale, enseigne ou numéro'),
            Filter::select('status', 'Statut', [
                ['value' => BusinessEntity::STATUS_PENDING, 'label' => 'En attente'],
                ['value' => BusinessEntity::STATUS_NEEDS_REVIEW, 'label' => 'À revoir'],
                ['value' => BusinessEntity::STATUS_VERIFIED, 'label' => 'Vérifiée'],
                ['value' => BusinessEntity::STATUS_REJECTED, 'label' => 'Refusée'],
                ['value' => BusinessEntity::STATUS_SUSPENDED, 'label' => 'Suspendue'],
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
            Action::make('run-verifications', 'Relancer les vérifications', function (BusinessEntity $model) {
                $this->onboarding->runVerifications($model);

                return ['ok' => true];
            }),

            Action::make('run-sanctions', 'Relancer le criblage', function (BusinessEntity $model) {
                $this->onboarding->runSanctionsScreening($model);

                return ['ok' => true];
            }),

            Action::make('reject', 'Refuser', function (BusinessEntity $model, array $saisie) {
                $admin = Auth::user();

                if (! $admin instanceof User) {
                    return ['ok' => false];
                }

                $this->onboarding->reject($model, (string) $saisie['reason'], $admin);

                return ['ok' => true];
            })
                ->destructive('Le dossier sera refusé et l’entreprise en sera informée.')
                ->requires([
                    // Le motif est OBLIGATOIRE et long : un refus sans explication écrite n'est
                    // ni contestable par la personne concernée, ni auditable six mois plus tard.
                    Field::make('reason', 'Motif du refus', Field::TYPE_TEXTAREA)
                        ->rules(['required', 'string', 'min:10', 'max:1000']),
                ]),

            Action::make('approve', 'Approuver', function (BusinessEntity $model) {
                $admin = Auth::user();

                $this->onboarding->approve($model, $admin instanceof User ? $admin : null);

                return ['ok' => true];
            }),
        ];
    }

    public function formFields(): array
    {
        // Un dossier KYB naît du dépôt de l'entreprise, pas d'une saisie administrative.
        return [];
    }

    /**
     * @param  Builder<BusinessEntity>  $query
     * @return Builder<BusinessEntity>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->where(function (Builder $sub) use ($value) {
                $sub->where('legal_name', 'like', '%'.$value.'%')
                    ->orWhere('trade_name', 'like', '%'.$value.'%')
                    ->orWhere('identifier_value', 'like', '%'.$value.'%')
                    ->orWhere('vat_id', 'like', '%'.$value.'%');
            }),
            'status' => $query->where('status', $value),
            'a_traiter' => $query->whereIn('status', [
                BusinessEntity::STATUS_PENDING,
                BusinessEntity::STATUS_NEEDS_REVIEW,
            ]),
            default => $query,
        };
    }

    /** @param  BusinessEntity  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'legal_name' => $model->legal_name,
            'status' => $model->status,
            'risk_score' => $model->risk_score,
            'country_code' => $model->country_code,
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }

    /** @param  BusinessEntity  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'trade_name' => $model->trade_name,
            'identifier_value' => $model->identifier_value,
            'vat_id' => $model->vat_id,
            'risk_level' => $model->risk_level,
            'rejection_reason' => $model->rejection_reason,
        ];
    }
}
