<?php

namespace App\Admin\Resources;

use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\DefaultsResourceWrites;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use App\Models\OrganizationSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les sites d'intervention rattachés aux entreprises.
 *
 * La relation `organizationAccount` est chargée d'avance : une liste de vingt-cinq sites
 * déclencherait sinon vingt-cinq requêtes pour afficher le nom de l'entreprise — invisible en
 * test sur trois lignes, sensible en production.
 *
 * @implements AdminResource<OrganizationSite>
 */
class SiteResource implements AdminResource
{
    use DefaultsResourceWrites;

    public function key(): string
    {
        return 'sites';
    }

    /** @return Builder<OrganizationSite> */
    public function query(): Builder
    {
        return OrganizationSite::query()->with('organizationAccount');
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('name', 'Nom du site'),
            Column::make('company', 'Entreprise'),
            Column::make('city', 'Ville'),
            Column::make('surface_m2', 'Surface (m²)', Column::TYPE_NUMBER),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Nom, ville ou adresse'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'name', 'city'];
    }

    public function actions(): array
    {
        return [];
    }

    public function formFields(): array
    {
        return [
            Field::make('name', 'Nom du site')->rules(['required', 'string', 'max:255']),
            Field::make('address', 'Adresse')->rules(['nullable', 'string', 'max:255']),
            Field::make('postal_code', 'Code postal')->rules(['nullable', 'string', 'max:16']),
            Field::make('city', 'Ville')->rules(['nullable', 'string', 'max:128']),
            Field::make('surface_m2', 'Surface (m²)', Field::TYPE_NUMBER)
                ->rules(['nullable', 'numeric', 'min:0']),
            Field::make('contact_name', 'Contact sur place')->rules(['nullable', 'string', 'max:255']),
            Field::make('contact_phone', 'Téléphone du contact', Field::TYPE_PHONE)
                ->rules(['nullable', 'string', 'max:32']),
            Field::make('access_instructions', 'Accès', Field::TYPE_TEXTAREA)
                ->rules(['nullable', 'string', 'max:2000']),
        ];
    }

    /**
     * @param  Builder<OrganizationSite>  $query
     * @return Builder<OrganizationSite>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->where(function (Builder $sub) use ($value) {
                $sub->where('name', 'like', '%'.$value.'%')
                    ->orWhere('city', 'like', '%'.$value.'%')
                    ->orWhere('address', 'like', '%'.$value.'%');
            }),
            default => $query,
        };
    }

    /** @param  OrganizationSite  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'name' => $model->name,
            // Un site sans entreprise rattachée existe (données historiques) : afficher « — »
            // plutôt que de laisser la ligne vide sans qu'on sache si c'est un défaut. Le typage
            // de la relation la dit non nullable ; les données, elles, ne le garantissent pas.
            'company' => $model->organizationAccount->name ?? '—',
            'city' => $model->city,
            'surface_m2' => $model->surface_m2,
        ];
    }

    /** @param  OrganizationSite  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'address' => $model->address,
            'postal_code' => $model->postal_code,
            'contact_name' => $model->contact_name,
            'contact_phone' => $model->contact_phone,
            'access_instructions' => $model->access_instructions,
        ];
    }
}
