<?php

namespace App\Services\Automation\Contracts;

/** Ce qu'un evenement du produit doit savoir dire au moteur. */
interface Declencheur
{
    /** La cle stockee dans `automation_rules.declencheur`, ex. `alerte.payout_failed`. */
    public function cle(): string;

    /** @return class-string La classe d'evenement ecoutee. */
    public function evenement(): string;

    /** La cle d'entite visee, ex. `alerte`. */
    public function entite(): string;

    /**
     * Cet evenement-ci me concerne-t-il ?
     *
     * Separee d'`identifiant()` a dessein : cinq alertes partagent une classe d'evenement, et
     * « ce n'est pas moi » ne doit pas se confondre avec « je n'ai pas trouve d'entite ».
     */
    public function sApplique(object $evenement): bool;

    /** L'entite visee, ou `null` si l'evenement n'en designe aucune. */
    public function identifiant(object $evenement): ?int;

    public function libelle(): string;
}
