<?php

namespace App\Admin\Console;

use Illuminate\Contracts\Container\Container;

/** Le registre des rapports d'administration. */
class ReportRegistry
{
    /** @var array<string, class-string<AdminReport>> */
    private array $bindings = [];

    /** @var array<string, AdminReport> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<AdminReport>  $class */
    public function register(string $key, string $class): void
    {
        $this->bindings[$key] = $class;
    }

    public function has(string $key): bool
    {
        return isset($this->bindings[$key]);
    }

    public function for(string $key): ?AdminReport
    {
        if (! isset($this->bindings[$key])) {
            return null;
        }

        return $this->resolved[$key] ??= $this->container->make($this->bindings[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->bindings);
    }
}
