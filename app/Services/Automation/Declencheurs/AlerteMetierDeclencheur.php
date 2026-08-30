<?php

namespace App\Services\Automation\Declencheurs;

use App\Events\BusinessAlertRaised;
use App\Services\Automation\Contracts\Declencheur;

/** Une alerte metier devient un declencheur. Les cinq ne different que par leur cle. */
class AlerteMetierDeclencheur implements Declencheur
{
    public function __construct(
        protected string $alerte,
        protected string $libelle,
    ) {}

    public function cle(): string
    {
        return 'alerte.'.$this->alerte;
    }

    public function evenement(): string
    {
        return BusinessAlertRaised::class;
    }

    public function entite(): string
    {
        return 'alerte';
    }

    public function sApplique(object $evenement): bool
    {
        return $evenement instanceof BusinessAlertRaised && $evenement->key === $this->alerte;
    }

    /** La ligne d'alerte n'existe pas encore ici : c'est l'ecouteur qui la cree, puis depose. */
    public function identifiant(object $evenement): ?int
    {
        return null;
    }

    public function libelle(): string
    {
        return $this->libelle;
    }
}
