<?php

namespace App\Admin\Console;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * La base des descripteurs adossés à un modèle Eloquent.
 *
 * POURQUOI ELLE EXISTE. Soixante domaines d'administration se décrivent de la même façon : un
 * modèle, des colonnes, une recherche sur quelques champs, un tri. Les écrire un par un aurait
 * produit soixante fois le même squelette — et soixante occasions de se tromper de nom de
 * colonne. Ici, un descripteur concret déclare ce qui lui est PROPRE et hérite du reste.
 *
 * CE QU'ELLE NE FAIT PAS. Elle ne devine rien. Les colonnes sont DÉCLARÉES, et
 * `EloquentResourceSchemaTest` vérifie que chacune existe vraiment sur la table — la même leçon
 * que les options de liste refusées par une contrainte de la base : une déclaration qui n'a pas
 * été confrontée au schéma est une déclaration fausse qui a l'air juste.
 *
 * ELLE N'INVENTE AUCUNE ACTION. Un domaine sans action déclarée n'en a aucune : le moteur ne
 * fabrique pas de suppression ni de bascule à partir du nom d'une colonne. Toute écriture passe
 * par ce que le descripteur a explicitement voulu.
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
     * Une liste de vingt-cinq lignes déclencherait sinon vingt-cinq requêtes par relation lue —
     * invisible sur trois lignes en test, sensible en production.
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

        /*
         * `name` quand la colonne existe.
         *
         * Sans lui, toute liste s'ordonne par identifiant — c'est-à-dire par ordre de création,
         * illisible dès la cinquième ligne. Le refus n'était pas silencieux pour autant : l'API
         * rendait 422, et l'écran mobile affichait « impossible de charger », un message d'erreur
         * générique pour ce qui n'était qu'un tri non déclaré.
         *
         * Le curseur reste stable parce que le contrôleur ajoute l'identifiant en départage : un
         * tri sur une colonne non unique produirait sinon des pages qui se chevauchent.
         */
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
     * Les dates sont rendues en ISO 8601 : le mobile les formate selon le type déclaré, et lui
     * envoyer une chaîne déjà mise en forme lui retirerait ce choix.
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
