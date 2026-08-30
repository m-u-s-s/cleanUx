<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * FULLCALENDAR N'EST PAS UN TABLEAU DE DONNEES.
 *
 * `composants.css` reprend l'element `table` lui-meme pour en faire une carte de verre :
 * c'etait le seul moyen d'atteindre les 93 tableaux ecrits en Tailwind brut. Mais
 * FullCalendar construit TOUTE sa mise en page en tableaux imbriques — grille de
 * defilement, entete des jours, table de synchronisation. Chacune recevait donc un rayon
 * de 24 px avec `overflow: visible`, et les filets de cellule, carres, ressortaient
 * par-dessus l'arrondi aux quatre coins (mesure du 2026-08-30 sur `/admin/calendar`).
 */
class CalendrierEchappeAuTraitementDesTableauxTest extends TestCase
{
    /** Ce que la regle du systeme pose sur un `table`, et qui casse une grille de calendrier. */
    private const A_NEUTRALISER = [
        'border-radius',
        'background-color',
        'backdrop-filter',
        'box-shadow',
    ];

    private function systeme(): string
    {
        return (string) file_get_contents(resource_path('css/composants.css'));
    }

    private function overrides(): string
    {
        return (string) file_get_contents(resource_path('css/fullcalendar-overrides.css'));
    }

    /**
     * TEMOIN — le systeme habille bien l'element `table` lui-meme. Sans lui, le test
     * ci-dessous resterait vert le jour ou cette regle disparaitrait, et la reserve
     * deviendrait un vestige que personne ne saurait retirer.
     */
    public function test_temoin_le_systeme_habille_l_element_table(): void
    {
        $this->assertStringContainsString(
            'body:not(.cx-shell) table:not(.brio-opaque)',
            $this->systeme(),
            'La regle des tableaux a change de forme : la reserve du calendrier vise peut-etre dans le vide.'
        );
    }

    public function test_le_calendrier_neutralise_le_traitement_des_tableaux(): void
    {
        $overrides = $this->overrides();

        $this->assertStringContainsString(
            'body:not(.cx-shell) .fc table',
            $overrides,
            'La reserve doit viser les tables DE FULLCALENDAR, et rien d’autre.'
        );

        $manquantes = array_values(array_filter(
            self::A_NEUTRALISER,
            fn (string $propriete): bool => ! str_contains($overrides, $propriete.':')
        ));

        $this->assertSame([], $manquantes, 'Ces proprietes du systeme retomberont sur la grille : '.implode(', ', $manquantes));
    }

    /**
     * LA SPECIFICITE, ET NON L'ORDRE DES FICHIERS.
     *
     * `.fc table` seul vaut 0,1,1 face aux 0,2,1 de la regle du systeme : il perdrait,
     * meme importe plus tard. Le prefixe `body:not(.cx-shell)` retablit l'equilibre.
     */
    public function test_la_reserve_porte_le_meme_prefixe_que_la_regle_qu_elle_annule(): void
    {
        $overrides = $this->overrides();

        // Un bloc par `}` : une expression qui traverse les accolades ramassait des
        // declarations entieres pour des selecteurs. Premiere version, premier faux positif.
        $selecteurs = [];

        foreach (explode('}', $overrides) as $bloc) {
            $position = strpos($bloc, '{');

            if ($position === false) {
                continue;
            }

            $selecteurs[] = trim(substr($bloc, 0, $position));
        }

        // SEULES LES REGLES QUI ANNULENT LE TRAITEMENT DES TABLEAUX sont concernees :
        // `.fc-event` ou `.fc-button` n'affrontent aucune regle du systeme.
        $concernees = array_values(array_filter(
            $selecteurs,
            fn (string $sel): bool => str_contains($sel, 'table') || str_contains($sel, 'fc-scrollgrid')
        ));

        $this->assertNotEmpty($concernees, 'Aucune regle de grille trouvee : la mesure ne mesure plus rien.');

        $faibles = array_values(array_filter(
            $concernees,
            fn (string $sel): bool => ! str_contains($sel, 'body:not(.cx-shell)')
        ));

        $this->assertSame([], $faibles, "Ces selecteurs perdront contre la regle du systeme :\n".implode("\n", $faibles));
    }

    /** UN SEUL CADRE porte l'arrondi, et il rogne : sans rognage, les filets ressortent. */
    public function test_la_grille_porte_un_cadre_qui_rogne(): void
    {
        $overrides = $this->overrides();

        $this->assertMatchesRegularExpression(
            '/\.fc-scrollgrid\s*\{[^}]*overflow:\s*hidden/s',
            $overrides,
            'Sans `overflow: hidden` sur la grille, l’arrondi ne rogne rien.'
        );
    }
}
