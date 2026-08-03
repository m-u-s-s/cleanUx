<?php

namespace App\Admin\Resources;

use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use App\Models\OrganizationAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les entreprises — clientes comme prestataires.
 *
 * PAS D'ACTIONS ICI, ET C'EST VOULU. Approuver ou refuser une entreprise passe par le module
 * d'approbations, qui porte la règle (vérifications KYB, notification, journal). Rejouer un
 * changement de statut par une écriture de colonne produirait une entreprise « approuvée » que
 * rien n'a vérifiée — deux chemins vers la même table, deux résultats.
 *
 * @implements AdminResource<OrganizationAccount>
 */
class CompanyResource implements AdminResource
{
    public function key(): string
    {
        return 'companies';
    }

    /** @return Builder<OrganizationAccount> */
    public function query(): Builder
    {
        return OrganizationAccount::query();
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('name', 'Nom'),
            Column::make('type', 'Type', Column::TYPE_BADGE),
            Column::make('status', 'Statut', Column::TYPE_BADGE),
            Column::make('city', 'Ville'),
            Column::make('created_at', 'Créée le', Column::TYPE_DATE),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Nom, TVA ou email'),
            Filter::select('type', 'Type', [
                ['value' => 'client', 'label' => 'Cliente'],
                ['value' => 'provider', 'label' => 'Prestataire'],
            ]),
            Filter::bool('cles', 'Grands comptes seulement'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'name', 'created_at'];
    }

    public function actions(): array
    {
        return [];
    }

    public function formFields(): array
    {
        return [
            Field::make('name', 'Nom commercial')->rules(['required', 'string', 'max:255']),
            Field::make('legal_name', 'Raison sociale')->rules(['nullable', 'string', 'max:255']),
            Field::make('tva_number', 'Numéro de TVA')->rules(['nullable', 'string', 'max:32']),
            Field::make('email', 'Email', Field::TYPE_EMAIL)->rules(['nullable', 'email', 'max:255']),
            Field::make('address_line_1', 'Adresse')->rules(['nullable', 'string', 'max:255']),
            Field::make('postal_code', 'Code postal')->rules(['nullable', 'string', 'max:16']),
            Field::make('city', 'Ville')->rules(['nullable', 'string', 'max:128']),
        ];
    }

    /**
     * @param  Builder<OrganizationAccount>  $query
     * @return Builder<OrganizationAccount>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->where(function (Builder $sub) use ($value) {
                $sub->where('name', 'like', '%'.$value.'%')
                    ->orWhere('legal_name', 'like', '%'.$value.'%')
                    ->orWhere('tva_number', 'like', '%'.$value.'%')
                    ->orWhere('email', 'like', '%'.$value.'%');
            }),
            'type' => $query->where('type', $value),
            'cles' => $query->where('is_key_account', true),
            default => $query,
        };
    }

    /** @param  OrganizationAccount  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'name' => $model->name,
            'type' => $model->type,
            'status' => $model->status,
            'city' => $model->city,
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }

    /** @param  OrganizationAccount  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'legal_name' => $model->legal_name,
            'tva_number' => $model->tva_number,
            'email' => $model->email,
            'address_line_1' => $model->address_line_1,
            'postal_code' => $model->postal_code,
        ];
    }
}
