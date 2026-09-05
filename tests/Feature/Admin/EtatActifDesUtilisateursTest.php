<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\GestionUtilisateurs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La colonne « Actif » lisait `$u->active`, un attribut qui n'existe pas. */
class EtatActifDesUtilisateursTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_compte_actif_s_affiche_comme_actif(): void
    {
        $actif = User::factory()->create(['name' => 'Compte Ouvert', 'is_active' => true]);

        $rendu = Livewire::actingAs($this->prendreLeSiege())
            ->test(GestionUtilisateurs::class)
            ->assertSee('Compte Ouvert')
            ->html();

        // Le bouton proposait « Activer » sur un compte deja actif : le cliquer le DESACTIVAIT.
        $ligne = $this->ligneDe($rendu, 'Compte Ouvert');
        $this->assertStringContainsString(__('ui.admin_users.deactivate'), $ligne);
        $this->assertStringNotContainsString(__('ui.admin_users.activate'), $ligne);

        $this->assertTrue($actif->fresh()->is_active);
    }

    /** TEMOIN POSITIF : un compte ferme propose bien « Activer ». */
    public function test_temoin_un_compte_inactif_propose_l_activation(): void
    {
        User::factory()->create(['name' => 'Compte Ferme', 'is_active' => false]);

        $rendu = Livewire::actingAs($this->prendreLeSiege())
            ->test(GestionUtilisateurs::class)
            ->html();

        $ligne = $this->ligneDe($rendu, 'Compte Ferme');
        $this->assertStringContainsString(__('ui.admin_users.activate'), $ligne);
    }

    /** La portion de HTML entre le nom et la fin de sa ligne de tableau. */
    private function ligneDe(string $html, string $nom): string
    {
        $debut = strpos($html, $nom);
        $this->assertNotFalse($debut, "Le nom {$nom} n'apparait pas dans le rendu.");

        $fin = strpos($html, '</tr>', $debut);

        return substr($html, $debut, ($fin !== false ? $fin : strlen($html)) - $debut);
    }
}
