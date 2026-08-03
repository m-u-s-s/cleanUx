<?php

namespace App\Admin\Console;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Le registre des descripteurs de console.
 *
 * Il fait le lien entre une clé de `config/admin_console.php` et la classe qui sait servir ce
 * domaine. Le registre est la PREUVE de ce que le registre de couverture DÉCLARE : trois tests
 * refusent que les deux divergent — un module annoncé disponible sans descripteur, un descripteur
 * sans module, un descripteur écrit mais laissé « à venir ».
 *
 * LES DESCRIPTEURS SONT CONSTRUITS À LA DEMANDE. Un descripteur peut charger des options de
 * filtre depuis la base (statuts, métiers, zones) ; tous les instancier au démarrage ferait payer
 * ces requêtes à chaque requête HTTP, y compris celles qui ne touchent pas la console.
 */
class ResourceRegistry
{
    /** @var array<string, class-string<AdminResource<covariant Model>>> */
    private array $bindings = [];

    /** @var array<string, AdminResource<covariant Model>> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<AdminResource<covariant Model>>  $class */
    public function register(string $key, string $class): void
    {
        $this->bindings[$key] = $class;
    }

    public function has(string $key): bool
    {
        return isset($this->bindings[$key]);
    }

    /**
     * POURQUOI CE RETOUR EST DÉCLARÉ SUR `Model` ET NON SUR LE MODÈLE RÉEL.
     *
     * `AdminResource` est invariant : son paramètre de type apparaît en entrée (`toRow(TModel)`)
     * comme en sortie (`query(): Builder<TModel>`). Un registre qui mélange
     * `AdminResource<User>` et `AdminResource<ComplaintCase>` n'est donc pas typable strictement
     * — l'enregistrement accepte la variance, la lecture la reperd.
     *
     * CE QUI REND L'ÉLARGISSEMENT SÛR, ET CE N'EST PAS une convention : l'appelant ne fabrique
     * jamais le modèle qu'il passe. Il le tire de `$descripteur->query()`, donc de la requête du
     * descripteur lui-même. Un descripteur ne peut recevoir que ses propres entités, par
     * construction — le système de types ne sait pas exprimer ce lien, le code le garantit.
     *
     * @return AdminResource<Model>|null
     */
    public function for(string $key): ?AdminResource
    {
        if (! isset($this->bindings[$key])) {
            return null;
        }

        // Mémorisé par clé : une liste d'annuaire résout la même ressource pour chaque ligne.
        /** @var AdminResource<Model> $resource */
        $resource = $this->resolved[$key] ??= $this->container->make($this->bindings[$key]);

        return $resource;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->bindings);
    }
}
