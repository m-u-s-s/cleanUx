<?php

namespace App\Admin\Console;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/** Le registre des descripteurs de console. */
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
