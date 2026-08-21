<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Mes statistiques » n'existe que pour les clients SOCIÉTÉ : le composant fait
 * `abort_unless(isClientCompany(), 403)`.
 *
 * La tuile du répertoire et le bouton de l'accueil, eux, étaient offerts à tous
 * les clients — et `Route::has()` ne prouve que l'existence de la porte, jamais
 * le droit de la franchir. Un particulier cliquait donc vers un 403 garanti.
 *
 * Ce test verrouille l'invariant : la tuile doit poser EXACTEMENT la condition
 * de la garde. `belongsToClientCompany` (organisation active) et
 * `isClientCompany` (profil client) sont deux notions différentes ; les
 * confondre rouvrirait le défaut sous une autre forme.
 */
class TuileStatistiquesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TÉMOIN POSITIF — une société cliente atteint bien ses statistiques.
     *
     * Il était resté en souffrance faute d'une fixture complète : il manquait
     * toujours l'un des quatre invariants de l'espace société, et le 403 qui en
     * résultait passait pour un défaut de l'écran. `societeCliente()` les pose
     * tous les quatre.
     */
    public function test_une_societe_cliente_atteint_ses_statistiques(): void
    {
        $societe = User::factory()->societeCliente()->create();
        $this->assertTrue($societe->isClientCompany(), 'La fabrique ne produit pas une société cliente');

        $this->actingAs($societe)
            ->get(route('client.analytics.dashboard'))
            ->assertOk();
    }

    /** REFUS — un particulier reste refusé (comportement d'origine, inchangé). */
    public function test_un_particulier_reste_refuse(): void
    {
        $particulier = User::factory()->client()->create();
        $this->assertFalse($particulier->isClientCompany());

        $this->actingAs($particulier)
            ->get(route('client.analytics.dashboard'))
            ->assertForbidden();
    }

    /** La tuile ne doit plus être offerte à qui se verra refuser l'entrée. */
    public function test_la_tuile_pose_la_meme_condition_que_la_garde(): void
    {
        $tuile = collect(config('modules.catalogue'))
            ->firstWhere('route', 'client.analytics.dashboard');

        $this->assertNotNull($tuile);
        $this->assertSame(
            'isClientCompany',
            $tuile['visible_si'] ?? null,
            'La tuile doit poser la condition de la garde du composant, pas une autre'
        );
        $this->assertTrue(
            method_exists(User::class, $tuile['visible_si']),
            'La condition de visibilité ne correspond à aucune méthode de User'
        );
    }
}
