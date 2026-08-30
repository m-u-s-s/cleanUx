<?php

namespace App\Listeners\Automation;

use App\Services\Automation\FileDeReevaluation;
use App\Services\Automation\Registre\DeclencheurRegistre;

/** Un evenement qui DESIGNE une entite : on depose, on rend la main. */
class DeposerLaReevaluation
{
    public function __construct(
        protected DeclencheurRegistre $declencheurs,
        protected FileDeReevaluation $file,
    ) {}

    public function handle(object $evenement): void
    {
        foreach ($this->declencheurs->pourEvenement($evenement) as $declencheur) {
            $this->file->deposer(
                $declencheur->cle(),
                $declencheur->entite(),
                $declencheur->identifiant($evenement)
            );
        }
    }
}
