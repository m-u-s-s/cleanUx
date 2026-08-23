<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\DefaultsResourceWrites;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les codes promotionnels. SUSPENDRE PLUTÔT QUE SUPPRIMER.
 *
 * @implements AdminResource<PromoCode>
 */
class PromoCodeResource implements AdminResource
{
    use DefaultsResourceWrites;

    public function key(): string
    {
        return 'promo-codes';
    }

    /** @return Builder<PromoCode> */
    public function query(): Builder
    {
        return PromoCode::query();
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('code', 'Code'),
            Column::make('status', 'Statut', Column::TYPE_BADGE),
            Column::make('discount_value', 'Remise', Column::TYPE_NUMBER),
            Column::make('total_uses', 'Utilisations', Column::TYPE_NUMBER),
            Column::make('valid_until', 'Expire le', Column::TYPE_DATE),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Code ou nom'),
            Filter::select('status', 'Statut', [
                ['value' => PromoCode::STATUS_DRAFT, 'label' => 'Brouillon'],
                ['value' => PromoCode::STATUS_ACTIVE, 'label' => 'Actif'],
                ['value' => PromoCode::STATUS_PAUSED, 'label' => 'Suspendu'],
                ['value' => PromoCode::STATUS_EXPIRED, 'label' => 'Expiré'],
                ['value' => PromoCode::STATUS_ARCHIVED, 'label' => 'Archivé'],
            ]),
            Filter::bool('utilisables', 'Utilisables aujourd’hui'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'code', 'valid_until', 'total_uses'];
    }

    public function actions(): array
    {
        return [
            Action::make('activate', 'Activer', function (PromoCode $model) {
                $model->forceFill(['status' => PromoCode::STATUS_ACTIVE])->save();

                return ['ok' => true];
            }),

            Action::make('pause', 'Suspendre', function (PromoCode $model) {
                $model->forceFill(['status' => PromoCode::STATUS_PAUSED])->save();

                return ['ok' => true];
            })->destructive('Le code cessera immédiatement d’être accepté à la commande.'),
        ];
    }

    public function formFields(): array
    {
        return [
            Field::make('code', 'Code')->rules(['required', 'string', 'max:64']),
            Field::make('name', 'Nom interne')->rules(['required', 'string', 'max:255']),
            // Les trois valeurs de l'enum SQL, pas celles qu'on croirait naturelles : la colonne
            // porte une contrainte CHECK, et une valeur inventée fait échouer l'insertion en base
            // plutôt qu'à la validation — c'est-à-dire trop tard pour le dire à l'utilisateur.
            Field::select('discount_type', 'Type de remise', [
                ['value' => 'percent', 'label' => 'Pourcentage'],
                ['value' => 'fixed_amount', 'label' => 'Montant fixe'],
                ['value' => 'free_first_booking', 'label' => 'Première commande offerte'],
            ])->rules(['required', 'in:percent,fixed_amount,free_first_booking']),
            Field::make('discount_value', 'Valeur de la remise', Field::TYPE_NUMBER)
                ->rules(['required', 'numeric', 'min:0']),
            Field::make('valid_from', 'Valide à partir du', Field::TYPE_DATE)
                ->rules(['nullable', 'date']),
            Field::make('valid_until', 'Valide jusqu’au', Field::TYPE_DATE)
                ->rules(['nullable', 'date', 'after_or_equal:valid_from']),
            // Un code sans plafond est un code qu'on découvre en lisant la facture.
            Field::make('max_total_uses', 'Utilisations maximales', Field::TYPE_NUMBER)
                ->rules(['nullable', 'integer', 'min:1']),
            Field::make('max_uses_per_user', 'Maximum par personne', Field::TYPE_NUMBER)
                ->rules(['nullable', 'integer', 'min:1']),
            Field::select('status', 'Statut', [
                ['value' => PromoCode::STATUS_DRAFT, 'label' => 'Brouillon'],
                ['value' => PromoCode::STATUS_ACTIVE, 'label' => 'Actif'],
                ['value' => PromoCode::STATUS_PAUSED, 'label' => 'Suspendu'],
            ])->rules(['required', 'in:draft,active,paused']),
        ];
    }

    /**
     * @param  Builder<PromoCode>  $query
     * @return Builder<PromoCode>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->where(function (Builder $sub) use ($value) {
                $sub->where('code', 'like', '%'.$value.'%')
                    ->orWhere('name', 'like', '%'.$value.'%');
            }),
            'status' => $query->where('status', $value),
            'utilisables' => $query->where('status', PromoCode::STATUS_ACTIVE)
                ->where(fn (Builder $sub) => $sub->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
                ->where(fn (Builder $sub) => $sub->whereNull('valid_until')->orWhere('valid_until', '>=', now())),
            default => $query,
        };
    }

    /** @param  PromoCode  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'code' => $model->code,
            'status' => $model->status,
            'discount_value' => $model->discount_value,
            'total_uses' => $model->total_uses,
            'valid_until' => $model->valid_until?->toIso8601String(),
        ];
    }

    /** @param  PromoCode  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'name' => $model->name,
            'discount_type' => $model->discount_type,
            'max_total_uses' => $model->max_total_uses,
            'max_uses_per_user' => $model->max_uses_per_user,
            'valid_from' => $model->valid_from?->toIso8601String(),
        ];
    }
}
