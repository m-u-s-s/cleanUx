<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * Le thème se pose avant la première peinture, et se règle depuis la vue mobile.
 * Deux défauts mesurés : un éclair clair sur quatre pages, et aucun réglage sous 640 px.
 */
class LeThemeSAppliqueAvantLaPremierePeintureTest extends TestCase
{
    /** @var list<string> */
    private const LAYOUTS = ['app', 'client-company', 'guest', 'provider-company'];

    private function layout(string $nom): string
    {
        return (string) file_get_contents(resource_path("views/layouts/{$nom}.blade.php"));
    }

    /**
     * TÉMOIN — les quatre layouts existent et portent bien un `<head>`.
     * Sans lui, un chemin faux rendrait des chaînes vides et les gardes passeraient sur du néant.
     */
    public function test_temoin_les_quatre_layouts_existent_et_ont_une_tete(): void
    {
        $manques = [];

        foreach (self::LAYOUTS as $nom) {
            $chemin = resource_path("views/layouts/{$nom}.blade.php");

            if (! is_file($chemin)) {
                $manques[] = "{$nom} : fichier absent";

                continue;
            }

            if (! str_contains((string) file_get_contents($chemin), '<head>')) {
                $manques[] = "{$nom} : pas de <head>";
            }
        }

        $this->assertSame([], $manques);
    }

    /**
     * Chaque layout amorce le thème. `client-company` et `guest` ne le faisaient pas :
     * la préférence du compte était ignorée sur leurs quatorze vues.
     */
    public function test_chaque_layout_amorce_le_theme(): void
    {
        $sans = [];

        foreach (self::LAYOUTS as $nom) {
            if (! str_contains($this->layout($nom), '<x-theme-amorce />')) {
                $sans[] = "{$nom} : la préférence de thème y est ignorée";
            }
        }

        $this->assertSame([], $sans, 'Tout layout doit poser <x-theme-amorce /> en tête de son <head>.');
    }

    /**
     * L'amorce précède les feuilles de style. Placée après, elle repeint après coup —
     * c'est exactement l'éclair blanc qu'elle est là pour supprimer.
     */
    public function test_l_amorce_precede_les_feuilles_de_style(): void
    {
        $tardives = [];

        foreach (self::LAYOUTS as $nom) {
            $html = $this->layout($nom);
            $amorce = strpos($html, '<x-theme-amorce />');
            $styles = strpos($html, '@vite(');

            if ($amorce === false || $styles === false) {
                continue;
            }

            if ($amorce > $styles) {
                $tardives[] = "{$nom} : l’amorce est APRÈS @vite — la page se repeint sous les yeux";
            }
        }

        $this->assertSame([], $tardives);
    }

    /**
     * Aucun layout ne rejoue le pilotage à la main : deux copies avaient déjà divergé,
     * l'une posant `dark` sans jamais pouvoir le retirer.
     */
    public function test_aucun_layout_ne_pilote_le_theme_a_la_main(): void
    {
        $doublons = [];

        foreach (self::LAYOUTS as $nom) {
            $html = $this->layout($nom);

            foreach (["classList.add('dark')", "classList.toggle('dark'", "localStorage.getItem('theme')"] as $motif) {
                if (str_contains($html, $motif)) {
                    $doublons[] = "{$nom} : `{$motif}` — c’est le travail de <x-theme-amorce />";
                }
            }
        }

        $this->assertSame([], $doublons);
    }

    /**
     * L'amorce doit être synchrone : `defer` ou `async` la font tourner après la peinture.
     */
    public function test_l_amorce_est_synchrone(): void
    {
        $html = (string) file_get_contents(resource_path('views/components/theme-amorce.blade.php'));

        $this->assertStringContainsString('<script>', $html, 'L’amorce a perdu son script.');
        $this->assertStringNotContainsString('<script defer', $html);
        $this->assertStringNotContainsString('<script async', $html);
        $this->assertStringContainsString(
            "classList.toggle('dark'",
            $html,
            '`toggle` et non `add` : sans lui, repasser en clair est impossible.',
        );
    }

    /**
     * Le réglage du thème est atteignable en vue mobile.
     * Il ne vivait que dans un conteneur `hidden sm:flex` : sur un téléphone, il n'existait pas.
     */
    public function test_le_reglage_du_theme_est_atteignable_en_vue_mobile(): void
    {
        $nav = (string) file_get_contents(resource_path('views/navigation-menu.blade.php'));

        $this->assertStringContainsString('<x-theme-toggle />', $nav, 'Le bouton de thème a disparu de la navigation.');

        // Le panneau mobile est celui qui porte `sm:hidden` : le bouton doit y figurer.
        $panneau = strstr($nav, 'id="menu-mobile"');

        $this->assertIsString($panneau, 'Le panneau du menu mobile a perdu son identifiant.');
        $this->assertStringContainsString(
            '<x-theme-toggle />',
            $panneau,
            'Sous 640 px, le thème redevient irréglable : le bouton n’est plus dans le menu mobile.',
        );
    }

    /**
     * Le bouton du menu mobile s'annonce : nom, état d'ouverture, panneau commandé.
     * Il n'avait aucun des trois — muet pour un lecteur d'écran.
     */
    public function test_le_bouton_du_menu_mobile_s_annonce(): void
    {
        $nav = (string) file_get_contents(resource_path('views/navigation-menu.blade.php'));
        $bouton = strstr($nav, '@click="open = ! open"');

        $this->assertIsString($bouton, 'Le bouton du menu mobile est introuvable.');
        $bouton = substr($bouton, 0, 700);

        $manques = [];

        foreach ([
            'aria-label' => 'son nom',
            'aria-expanded' => 'son état d’ouverture',
            'aria-controls="menu-mobile"' => 'le panneau qu’il commande',
        ] as $motif => $role) {
            if (! str_contains($bouton, $motif)) {
                $manques[] = "{$motif} — {$role}";
            }
        }

        $this->assertSame([], $manques, 'Le bouton du menu mobile est muet pour un lecteur d’écran.');
    }
}
