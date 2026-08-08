<?php

namespace App\Services\Organizations;

use App\Models\OrganizationMember;

/**
 * Ce qu'une action d'administration a fait, ou pourquoi elle n'a rien fait.
 *
 * `applique` et `motif` sont exclusifs : une action appliquée n'a pas de motif de refus, un refus
 * n'a pas de membre. Le type le dit plutôt que la documentation.
 */
final class ResultatAdministration
{
    private function __construct(
        public readonly bool $applique,
        public readonly ?OrganizationMember $membre,
        public readonly ?MotifDeRefus $motif,
    ) {}

    public static function applique(OrganizationMember $membre): self
    {
        return new self(true, $membre, null);
    }

    public static function refuse(MotifDeRefus $motif): self
    {
        return new self(false, null, $motif);
    }

    public function estRefuse(MotifDeRefus $motif): bool
    {
        return $this->motif === $motif;
    }
}
