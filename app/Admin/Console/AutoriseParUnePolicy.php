<?php

namespace App\Admin\Console;

/**
 * UN DESCRIPTEUR QUI EXIGE LA MÊME POLICY QUE LE WEB.
 *
 * Le moteur de console applique `module_gate` et refuse les comptes en lecture seule, mais il
 * n'interrogeait aucune policy : deux chemins d'écriture vers la même table, l'un policé côté
 * Livewire et l'autre pas, et c'est le second que sert l'application mobile.
 *
 * Adhérer à ce contrat est un CHOIX du descripteur : les 79 autres gardent exactement le
 * comportement qu'ils avaient.
 */
interface AutoriseParUnePolicy
{
    /**
     * Les capacités exigées, par opération.
     *
     * Clés reconnues : `create`, `update`, `delete`, et `action:<clé>` pour une action de ligne.
     * Une opération absente de ce tableau n'est pas soumise à policy.
     *
     * @return array<string, string>
     */
    public function policyAbilities(): array;
}
