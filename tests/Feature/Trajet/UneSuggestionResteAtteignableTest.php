<?php

namespace Tests\Feature\Trajet;

use Tests\TestCase;

/**
 * LA SUGGESTION D'ADRESSE PASSAIT SOUS LA BARRE DU POUCE.
 *
 * La liste naît DANS LE FLUX, sous le champ. Quand le champ est bas — c'est le cas du point
 * d'arrivée d'une course, en fin de formulaire — elle apparaît sous le pli, et la barre fixe
 * `bottom-0 z-30` la recouvre. L'appui atteint alors « Continuer » : l'adresse reste sans
 * coordonnées, la course sans distance ni prix, et rien ne le dit.
 *
 * Mesuré sur l'émulateur le 2026-09-07, deux fois le même geste : suggestion à y=2899, sous la
 * barre (2886) → point NON posé ; le champ remonté, suggestion à y=2293 → point posé, 41 590 m.
 *
 * `pb-28` sur la racine ne réserve que la FIN de page : elle ne protège rien au milieu.
 */
class UneSuggestionResteAtteignableTest extends TestCase
{
    private function vue(): string
    {
        return (string) file_get_contents(
            resource_path('views/livewire/order-engine/questions/location.blade.php'),
        );
    }

    public function test_la_liste_se_ramene_a_l_ecran_quand_elle_apparait(): void
    {
        $this->assertStringContainsString('scrollIntoView', $this->vue());
    }

    public function test_elle_garde_la_hauteur_de_la_barre_sous_elle(): void
    {
        // `scroll-margin-bottom` est ce que `scrollIntoView` respecte : sans lui, « nearest »
        // s'arrête au bord du cadre, c'est-à-dire derrière la barre.
        $this->assertStringContainsString('scroll-mb-32', $this->vue());
    }

    public function test_temoin_la_barre_du_pouce_est_bien_fixe_et_au_dessus(): void
    {
        // Sans elle, il n'y aurait rien à dégager — et ce test mesurerait un problème disparu.
        $journey = (string) file_get_contents(
            resource_path('views/livewire/order-engine/order-journey.blade.php'),
        );

        $this->assertStringContainsString('fixed inset-x-0 bottom-0 z-30', $journey);
    }

    public function test_l_autre_champ_d_adresse_porte_la_meme_garde(): void
    {
        // `address-availability` a le meme motif : une liste dans le flux, sous un champ souvent bas.
        $autre = (string) file_get_contents(
            resource_path('views/livewire/order-engine/partials/address-availability.blade.php'),
        );

        $this->assertStringContainsString('scrollIntoView', $autre);
        $this->assertStringContainsString('scroll-mb-32', $autre);
    }

    public function test_temoin_la_liste_existe_toujours(): void
    {
        $this->assertStringContainsString('locationSuggestions', $this->vue());
        $this->assertStringContainsString('chooseLocation', $this->vue());
    }
}
