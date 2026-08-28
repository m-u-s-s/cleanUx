<?php

namespace Tests\Feature\Client;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Trois pages de profil coexistaient. `/user/profile` reste seule : elle gagne le telephone,
 * qui n'existait que sur `/dashboard/client/profil/editer`.
 */
class UnSeulProfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_telephone_se_met_a_jour_depuis_le_profil(): void
    {
        $utilisateur = User::factory()->client()->create(['phone' => '+32470000000']);

        app(UpdateUserProfileInformation::class)->update($utilisateur, [
            'name' => $utilisateur->name,
            'email' => $utilisateur->email,
            'phone' => '+32499123456',
        ]);

        $this->assertSame('+32499123456', $utilisateur->fresh()->phone);
    }

    /**
     * TEMOIN — le nom et l'e-mail continuent de se mettre a jour. Sans lui, le test ci-dessus
     * resterait vert meme si j'avais casse l'action de Fortify en y ajoutant le telephone.
     */
    public function test_temoin_le_nom_et_l_email_se_mettent_toujours_a_jour(): void
    {
        $utilisateur = User::factory()->client()->create(['name' => 'Avant', 'email' => 'avant@example.test']);

        app(UpdateUserProfileInformation::class)->update($utilisateur, [
            'name' => 'Apres',
            'email' => 'apres@example.test',
        ]);

        $fraiche = $utilisateur->fresh();
        $this->assertSame('Apres', $fraiche->name);
        $this->assertSame('apres@example.test', $fraiche->email);
    }

    /** Un telephone absent de la saisie ne l'efface pas. */
    public function test_un_telephone_absent_ne_l_efface_pas(): void
    {
        $utilisateur = User::factory()->client()->create(['phone' => '+32470000000']);

        app(UpdateUserProfileInformation::class)->update($utilisateur, [
            'name' => $utilisateur->name,
            'email' => $utilisateur->email,
        ]);

        $this->assertSame('+32470000000', $utilisateur->fresh()->phone);
    }

    public function test_le_champ_telephone_est_offert_a_l_ecran(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('state.phone', escape: false);
    }

    public function test_les_deux_pages_de_profil_client_n_existent_plus(): void
    {
        $this->assertFalse(Route::has('client.profile'));
        $this->assertFalse(Route::has('client.profile.edit'));
    }

    /** TEMOIN — la page conservee, elle, repond. */
    public function test_temoin_la_page_conservee_repond(): void
    {
        $this->assertTrue(Route::has('profile.show'));

        $this->actingAs(User::factory()->client()->create())
            ->get(route('profile.show'))
            ->assertOk();
    }
}
