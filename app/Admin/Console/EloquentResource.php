<?php

namespace App\Admin\Console;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * La base des descripteurs adossés à un modèle Eloquent. POURQUOI ELLE EXISTE.
 *
 * @template TModel of Model
 *
 * @implements AdminResource<TModel>
 */
abstract class EloquentResource implements AdminResource
{
    // Les réponses neutres aux deux questions posées avant écriture, partagées avec les dix
    // descripteurs qui implémentent le contrat sans passer par cette base.
    use DefaultsResourceWrites;

    /**
     * La classe du modèle servi.
     *
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * Les colonnes de liste : clé → [libellé, type].
     *
     * @return array<string, array{0: string, 1?: string}>
     */
    abstract protected function columnSpec(): array;

    /**
     * Les colonnes sur lesquelles porte la recherche libre. Vide = pas de recherche offerte.
     *
     * @return list<string>
     */
    protected function searchable(): array
    {
        return [];
    }

    /** Le libellé du champ de recherche, quand il y en a un. */
    protected function searchLabel(): string
    {
        return 'Rechercher';
    }

    /**
     * Les filtres à valeur exacte : clé de filtre → [libellé, colonne, options].
     *
     * @return array<string, array{0: string, 1: string, 2: list<array{value: string, label: string}>}>
     */
    protected function selectFilters(): array
    {
        return [];
    }

    /**
     * Les champs servis en DÉTAIL par-dessus les colonnes de liste.
     *
     * @return array<string, string>
     */
    protected function detailSpec(): array
    {
        return [];
    }

    /**
     * Les relations chargées d'avance.
     *
     * @return list<string>
     */
    protected function eagerLoad(): array
    {
        return [];
    }

    /** @return Builder<TModel> */
    public function query(): Builder
    {
        /** @var Builder<TModel> $query */
        $query = ($this->model())::query();

        return $this->eagerLoad() === [] ? $query : $query->with($this->eagerLoad());
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        $colonnes = [];

        foreach ($this->columnSpec() as $key => $spec) {
            $colonnes[] = Column::make($key, $spec[0], $spec[1] ?? Column::TYPE_TEXT);
        }

        return $colonnes;
    }

    public function filters(): array
    {
        $filtres = [];

        if ($this->searchable() !== []) {
            $filtres[] = Filter::search('q', $this->searchLabel());
        }

        foreach ($this->selectFilters() as $key => $spec) {
            $filtres[] = Filter::select($key, $spec[0], $spec[2]);
        }

        return $filtres;
    }

    public function sorts(): array
    {
        // `created_at` n'existe pas partout : le tri par défaut reste la clé, toujours présente
        // et toujours stable — ce que la pagination par curseur exige.
        $tris = ['id'];

        if (array_key_exists('created_at', $this->columnSpec())) {
            $tris[] = 'created_at';
        }

        // `name` quand la colonne existe.
        if (array_key_exists('name', $this->columnSpec())) {
            $tris[] = 'name';
        }

        return $tris;
    }

    public function actions(): array
    {
        return [];
    }

    public function formFields(): array
    {
        return [];
    }

    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        if ($key === 'q' && $this->searchable() !== []) {
            $colonnes = $this->searchable();

            return $query->where(function (Builder $sub) use ($colonnes, $value) {
                foreach ($colonnes as $index => $colonne) {
                    $index === 0
                        ? $sub->where($colonne, 'like', '%'.$value.'%')
                        : $sub->orWhere($colonne, 'like', '%'.$value.'%');
                }
            });
        }

        $selects = $this->selectFilters();

        if (isset($selects[$key])) {
            return $query->where($selects[$key][1], $value);
        }

        // Un filtre inconnu est ignoré, jamais deviné depuis sa clé.
        return $query;
    }

    /**
     * @param  TModel  $model
     * @return array<string, mixed>
     */
    public function toRow(Model $model): array
    {
        $ligne = ['id' => $model->getKey()];

        foreach (array_keys($this->columnSpec()) as $key) {
            $ligne[$key] = $this->valeur($model, $key);
        }

        return $ligne;
    }

    /**
     * @param  TModel  $model
     * @return array<string, mixed>
     */
    public function toDetail(Model $model): array
    {
        $detail = $this->toRow($model);

        foreach (array_keys($this->detailSpec()) as $key) {
            $detail[$key] = $this->valeur($model, $key);
        }

        return $detail;
    }

    /**
     * Lit une valeur, en traversant les relations notées `relation.champ`.
     *
     * @param  TModel  $model
     */
    protected function valeur(Model $model, string $key): mixed
    {
        $valeur = str_contains($key, '.')
            ? data_get($model, $key)
            : $model->getAttribute($key);

        return $valeur instanceof \DateTimeInterface
            ? $valeur->format(\DateTimeInterface::ATOM)
            : $valeur;
    }
}
