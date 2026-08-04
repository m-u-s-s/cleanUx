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
 * LE CONTRAT EST GÉNÉRIQUE SUR SON MODÈLE. Un descripteur sert UN domaine, donc un seul type
 * d'entité : le déclarer (`@implements AdminResource<User>`) permet à l'analyse statique de
 * vérifier que `toRow()` lit des colonnes qui existent vraiment, au lieu de faire confiance.
 *
 * LA SÉCURITÉ N'EST PAS DANS LE DESCRIPTEUR. Le groupe `/api/admin/*` est gardé par `api_admin` ;
 * `query()` sert à cadrer le domaine (relations chargées, exclusions métier), pas à tenir une
 * frontière d'autorisation.
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
     * Les actions qui ne portent sur AUCUNE ligne.
     *
     * Rafraîchir tous les taux de change, clôturer un mois comptable, purger un cache, balayer les
     * certifications qui expirent : ces gestes ne visent rien en particulier. Les poser sur une
     * ligne arbitraire serait un mensonge d'interface — « purger le cache » offert sur la troisième
     * adresse de la liste — et les omettre laisserait des modules entiers sans leur geste principal.
     *
     * Leur signature diffère : elles reçoivent les valeurs saisies, jamais un modèle.
     *
     * @return list<Action>
     */
    public function globalActions(): array;

    /**
     * Enrichit les données validées juste avant une CRÉATION.
     *
     * Certaines colonnes obligatoires ne se demandent pas : le `slug` d'une zone se déduit de son
     * nom, et son état initial est une décision de la plateforme — une zone naît fermée, faute de
     * quoi la créer la rendrait commandable avant qu'on ait réglé son catalogue.
     *
     * Les mettre dans le formulaire les rendrait modifiables par le client de l'API ; les laisser
     * de côté ferait échouer l'insertion sur une colonne non nulle.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareForCreate(array $data): array;

    /**
     * Ce qui empêche de supprimer cette ligne. Tableau vide = suppression permise.
     *
     * LE MOTEUR SUPPRIMAIT SANS RIEN DEMANDER. Un pays effacé aurait emporté ses zones, et avec
     * elles l'historique de facturation qui s'y rattache — un dégât qu'aucune sauvegarde ne répare
     * proprement, puisque les identifiants auraient changé.
     *
     * ON REND DES RAISONS ET NON UN BOOLÉEN : « ça ne se supprime pas » sans dire pourquoi oblige à
     * ouvrir la base pour comprendre, ce que personne ne fera depuis un téléphone.
     *
     * @param  TModel  $model
     * @return list<string>
     */
    public function reasonsToRefuseDelete(Model $model): array;

    /**
     * Les champs de création et d'édition. Une liste vide signifie « lecture seule » — c'est le
     * cas de la plupart des files de décision, qui s'administrent par actions.
     *
     * LE FORMULAIRE DOIT COUVRIR TOUTES LES COLONNES NON NULLES du modèle. Le moteur n'écrit QUE
     * les champs déclarés — c'est ce qui empêche une création de poser `platform_role` et de se
     * promouvoir — donc une colonne obligatoire absente d'ici fait échouer l'insertion en base.
     * Un domaine dont la création exige des valeurs calculées relève d'un écran sur-mesure.
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
