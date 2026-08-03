<?php

namespace App\Admin\Console;

/**
 * Le contrat d'un module d'administration qui n'est PAS une liste.
 *
 * POURQUOI UN SECOND CONTRAT. Dix des pages d'administration n'ont aucune table derrière elles :
 * l'accueil, les alertes, la préparation de la plateforme, la santé financière. Ce sont des
 * SYNTHÈSES — des compteurs, des seuils, des états. Les forcer dans le moteur de liste aurait
 * demandé d'inventer une entité qui n'existe pas, et l'écran aurait montré une liste vide en
 * prétendant couvrir le domaine. C'est exactement le mensonge que le registre de couverture sert
 * à empêcher.
 *
 * CE QU'UN RAPPORT EST : des tuiles chiffrées, groupées en sections, chacune sachant dire si elle
 * a pu être mesurée. Rien de plus. Les graphiques et les séries temporelles restent sur le web,
 * qui a la place de les afficher lisiblement.
 *
 * CHAQUE TUILE DIT SI ELLE A PU ÊTRE MESURÉE. Une tuile à zéro parce que la requête a échoué se
 * lit comme un calme réel, et personne ne va vérifier — c'est la même règle que les compteurs de
 * l'accueil.
 */
interface AdminReport
{
    /** La clé du module dans `config/admin_console.php`. */
    public function key(): string;

    /**
     * Les sections du rapport, chacune portant ses tuiles.
     *
     * Forme d'une section : `['title' => string, 'tiles' => list<ReportTile>]`.
     *
     * @return list<array{title: string, tiles: list<ReportTile>}>
     */
    public function sections(): array;
}
