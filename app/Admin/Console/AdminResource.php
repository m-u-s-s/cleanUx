<?php

namespace App\Admin\Console;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Le contrat d'un domaine d'administration servi par le moteur de console. L'IDÉE.
 *
 * @template TModel of Model
 */
interface AdminResource
{
    /** La clé du module dans `config/admin_console.php`. Elle fait le lien avec l'annuaire. */
    public function key(): string;

    /**
     * La requête de base du domaine, relations d'affichage déjà chargées.
     *
     * @return Builder<TModel>
     */
    public function query(): Builder;

    /** La colonne de tri par défaut. */
    public function defaultSort(): string;

    /** @return list<Column> */
    public function columns(): array;

    /** @return list<Filter> */
    public function filters(): array;

    /**
     * Les champs sur lesquels un tri est accepté.
     *
     * @return list<string>
     */
    public function sorts(): array;

    /** @return list<Action> */
    public function actions(): array;

    /**
     * Les actions qui ne portent sur AUCUNE ligne.
     *
     * @return list<Action>
     */
    public function globalActions(): array;

    /**
     * Enrichit les données validées juste avant une CRÉATION.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareForCreate(array $data): array;

    /**
     * Ce qui empêche de supprimer cette ligne. Tableau vide = suppression permise.
     *
     * @param  TModel  $model
     * @return list<string>
     */
    public function reasonsToRefuseDelete(Model $model): array;

    /**
     * Les champs de création et d'édition.
     *
     * @return list<Field>
     */
    public function formFields(): array;

    /**
     * Applique un filtre déclaré à la requête.
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder;

    /**
     * Une ligne telle que la liste l'affiche. Les clés correspondent à `columns()`.
     *
     * @param  TModel  $model
     * @return array<string, mixed>
     */
    public function toRow(Model $model): array;

    /**
     * Le détail d'une ligne. Peut porter plus que `toRow()`.
     *
     * @param  TModel  $model
     * @return array<string, mixed>
     */
    public function toDetail(Model $model): array;
}
