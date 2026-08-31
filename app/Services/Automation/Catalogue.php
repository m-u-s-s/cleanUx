<?php

namespace App\Services\Automation;

use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\DeclencheurRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;

class Catalogue
{
    public function __construct(
        private EntiteRegistre $entiteRegistre,
        private ActionRegistre $actionRegistre,
        private DeclencheurRegistre $declencheurRegistre
    ) {}

    /** @return array<string, array{cle: string, libelle: string, champs: list<string>, operateurs: list<string>}> */
    public function entites(): array
    {
        $catalogue = [];

        foreach ($this->entiteRegistre->cles() as $cle) {
            $descripteur = $this->entiteRegistre->descripteur($cle);

            if ($descripteur === null) {
                continue;
            }

            $catalogue[$cle] = [
                'cle' => $cle,
                'libelle' => $descripteur->libelle(),
                'champs' => array_keys($descripteur->fields()),
                'operateurs' => RuleTreeEvaluator::OPERATEURS_CONNUS,
            ];
        }

        return $catalogue;
    }

    /**
     * @param  string|null  $entite  Si fourni, filtre les actions pour cette entite.
     * @return array<string, array{cle: string, libelle: string, champs: array<string,string>, touche_au_domaine: bool}>
     */
    public function actions(?string $entite = null): array
    {
        $catalogue = [];

        foreach ($this->actionRegistre->toutes() as $cle => $action) {
            $entitesSupportees = $action->entitesSupportees();

            if ($entite !== null && ! in_array($entite, $entitesSupportees, true)) {
                continue;
            }

            $catalogue[$cle] = [
                'cle' => $cle,
                'libelle' => $action->libelle(),
                'champs' => $action->champs(),
                'touche_au_domaine' => $action->toucheAuDomaine(),
            ];
        }

        return $catalogue;
    }

    /**
     * @param  string|null  $entite  Si fourni, filtre les declencheurs pour cette entite.
     * @return array<string, array{cle: string, libelle: string, entite: string}>
     */
    public function declencheurs(?string $entite = null): array
    {
        $catalogue = [];

        foreach ($this->declencheurRegistre->toutes() as $cle => $declencheur) {
            if ($entite !== null && $declencheur->entite() !== $entite) {
                continue;
            }

            $catalogue[$cle] = [
                'cle' => $cle,
                'libelle' => $declencheur->libelle(),
                'entite' => $declencheur->entite(),
            ];
        }

        return $catalogue;
    }
}
