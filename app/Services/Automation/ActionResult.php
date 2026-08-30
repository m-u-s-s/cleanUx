<?php

namespace App\Services\Automation;

/** Le resultat d'une action : reussie ou non, et de quoi le journaliser. */
final class ActionResult
{
    private function __construct(
        public readonly bool $reussie,
        public readonly ?string $message,
    ) {}

    public static function reussie(?string $message = null): self
    {
        return new self(true, $message);
    }

    public static function echouee(string $message): self
    {
        return new self(false, $message);
    }
}
