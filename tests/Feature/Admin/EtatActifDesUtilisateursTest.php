<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\GestionUtilisateurs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    /**
     * LE COMPTE QUE L'ECRAN DISAIT ACTIF ET QUE LA CONNEXION REFUSAIT.
     *
     * `is_active = true` avec `status = 'inactive'` : la pastille affichait « oui », le bouton
     * proposait « Desactiver », et il fallait DEUX clics pour rouvrir le compte.
     */
    public function test_un_compte_ferme_par_son_statut_s_affiche_comme_ferme(): void
    {
        $bloque = User::factory()->create([
            'name' => 'Compte Bloque Par Statut',
            'is_active' => true,
            'status' => 'inactive',
        ]);

        $this->assertFalse($bloque->compteActif(), 'La connexion refuse deja ce compte.');

        $rendu = Livewire::actingAs($this->prendreLeSiege())
            ->test(GestionUtilisateurs::class)
            ->html();

        $ligne = $this->ligneDe($rendu, 'Compte Bloque Par Statut');
        $this->assertStringContainsString(__('ui.admin_users.activate'), $ligne);
        $this->assertStringNotContainsString(__('ui.admin_users.deactivate'), $ligne);
    }

    public function test_un_seul_clic_rouvre_un_compte_ferme_par_son_statut(): void
    {
        $bloque = User::factory()->create(['is_active' => true, 'status' => 'inactive']);

        Livewire::actingAs($this->prendreLeSiege())
            ->test(GestionUtilisateurs::class)
            ->call('toggleActivation', $bloque->id);

        $bloque->refresh();

        $this->assertTrue($bloque->compteActif(), 'Un clic doit suffire a rouvrir le compte.');
        $this->assertTrue((bool) $bloque->is_active);
        $this->assertSame('active', $bloque->status);
    }

    /** TEMOIN : le meme clic ferme bien un compte ouvert — la bascule marche dans les deux sens. */
    public function test_temoin_un_clic_ferme_un_compte_ouvert(): void
    {
        $ouvert = User::factory()->create(['is_active' => true, 'status' => 'active']);

        Livewire::actingAs($this->prendreLeSiege())
            ->test(GestionUtilisateurs::class)
            ->call('toggleActivation', $ouvert->id);

        $ouvert->refresh();

        $this->assertFalse($ouvert->compteActif());
        $this->assertFalse((bool) $ouvert->is_active);
        $this->assertSame('inactive', $ouvert->status);
    }

    /**
     * LA CONSOLE MOBILE REACTIVAIT SANS TOUCHER `status`.
     *
     * Elle ecrivait `is_active = true` et rien d'autre : le compte s'affichait actif dans la
     * console ET restait refuse a la connexion, sans second recours — aucun ecran mobile ne
     * montrait `status`.
     */
    public function test_la_console_mobile_rouvre_vraiment_un_compte(): void
    {
        $bloque = User::factory()->create(['is_active' => true, 'status' => 'suspended']);

        Sanctum::actingAs(User::factory()->adminComplet()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
        ]), ['*']);

        $this->postJson("/api/admin/console/users/{$bloque->id}/actions/reactivate")->assertOk();

        $this->assertTrue($bloque->refresh()->compteActif());

        // TEMOIN : la suspension par la console ferme bien les deux colonnes.
        $this->postJson("/api/admin/console/users/{$bloque->id}/actions/suspend")->assertOk();

        $bloque->refresh();
        $this->assertFalse($bloque->compteActif());
        $this->assertSame('inactive', $bloque->status);
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
