<?php

namespace App\Admin\Console;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Le contrat d'un domaine d'administration servi par le moteur de console.
 *
 * L'IDÉE. L'administration web porte 99 pages ; les écrire une par une en React Native
 * garantirait qu'elles divergent du web à la première évolution. Un descripteur DÉCRIT son
 * domaine — colonnes, filtres, actions, formulaire — et trois écrans natifs génériques le rendent.
 * Ajouter un domaine devient : écrire une classe.
 *
 * LA RÈGLE QUI TIENT TOUT. **Un descripteur ne réimplémente aucune logique métier.** Ses actions
 * délèguent aux services existants (CancellationV2, KybV2, RiskEngine…). Un descripteur qui aurait
 * besoin d'une règle nouvelle est le signe qu'il faut un écran sur-mesure, pas une règle dupliquée
 * dans le moteur : deux chemins vers la même table produiraient des décisions différentes selon la
 * porte empruntée.
 *
 * LA SÉCURITÉ N'EST PAS DANS LE DESCRIPTEUR. Le groupe `/api/admin/*` est gardé par `api_admin` ;
 * `query()` sert à cadrer le domaine (relations chargées, exclusions métier), pas à tenir une
 * frontière d'autorisation.
 */
interface AdminResource
{
    /** La clé du module dans `config/admin_console.php`. Elle fait le lien avec l'annuaire. */
    public function key(): string;

    /**
     * La requête de base du domaine, relations d'affichage déjà chargées.
     *
     * @return Builder<covariant Model>
     */
    public function query(): Builder;

    /**
     * La colonne de tri par défaut. Elle doit être STABLE et unique par ligne : la pagination du
     * moteur est par curseur, et une clé non stable ferait sauter ou répéter des lignes sur une
     * table qui bouge.
     */
    public function defaultSort(): string;

    /** @return list<Column> */
    public function columns(): array;

    /** @return list<Filter> */
    public function filters(): array;

    /**
     * Les champs sur lesquels un tri est accepté.
     *
     * Le contrôleur REFUSE tout tri absent de cette liste plutôt que de le transmettre : une clé
     * de tri arrivant de la requête est une chaîne fournie par le client.
     *
     * @return list<string>
     */
    public function sorts(): array;

    /** @return list<Action> */
    public function actions(): array;

    /**
     * Les champs de création et d'édition. Une liste vide signifie « lecture seule » — c'est le
     * cas de la plupart des files de décision, qui s'administrent par actions.
     *
     * @return list<Field>
     */
    public function formFields(): array;

    /**
     * Applique un filtre déclaré à la requête.
     *
     * Le descripteur seul sait quelle colonne ou quelle jointure porte un filtre donné. Un filtre
     * inconnu doit être IGNORÉ, jamais deviné.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder;

    /**
     * Une ligne telle que la liste l'affiche. Les clés correspondent à `columns()`.
     *
     * @return array<string, mixed>
     */
    public function toRow(Model $model): array;

    /**
     * Le détail d'une ligne. Peut porter plus que `toRow()`.
     *
     * @return array<string, mixed>
     */
    public function toDetail(Model $model): array;
}
