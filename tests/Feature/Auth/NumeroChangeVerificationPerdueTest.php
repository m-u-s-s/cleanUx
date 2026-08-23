<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHANGER DE NUMÉRO PERD LA VÉRIFICATION DU NUMÉRO. */
class NumeroChangeVerificationPerdueTest extends TestCase
{
    use RefreshDatabase;

    public function test_changer_de_numero_depuis_le_profil_mobile_perd_la_verification(): void
    {
        $user = $this->compteVerifie();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', ['phone' => '+32499999999'])
            ->assertOk();

        $frais = $user->fresh();
        $this->assertSame('+32499999999', $frais->phone);
        $this->assertNull($frais->phone_verified_at, 'Le numéro a changé, la vérification aurait dû tomber.');
        $this->assertFalse($frais->hasVerifiedPhone());
    }

    /** Le profil CLIENT mobile est un second écrivain : la règle ne dépend pas de l'appelant. */
    public function test_le_profil_client_mobile_perd_aussi_la_verification(): void
    {
        $user = $this->compteVerifie();

        // `PUT`, pas `PATCH` : les deux profils mobiles n'exposent pas le même verbe.
        $this->actingAs($user, 'sanctum')
            ->putJson('/api/client/profile', ['phone' => '+32488888888'])
            ->assertOk();

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    /** LE TÉMOIN : sans changement de numéro, la vérification reste. */
    public function test_modifier_autre_chose_ne_perd_pas_la_verification(): void
    {
        $user = $this->compteVerifie();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', ['name' => 'Nouveau Nom'])
            ->assertOk();

        $frais = $user->fresh();
        $this->assertSame('Nouveau Nom', $frais->name);
        $this->assertNotNull($frais->phone_verified_at, 'Changer de nom ne doit rien invalider.');
    }

    /** Réécrire LE MÊME numéro n'est pas un changement. */
    public function test_reecrire_le_meme_numero_ne_perd_rien(): void
    {
        $user = $this->compteVerifie();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', ['phone' => '+32471000900'])
            ->assertOk();

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    /** LE PIÈGE À NE PAS CRÉER : le parcours de vérification écrit le numéro ET sa date dans le même enregistrement. */
    public function test_la_verification_elle_meme_pose_bien_les_deux(): void
    {
        $user = User::factory()->create(['phone' => '+32471000001', 'phone_verified_at' => null]);

        $user->forceFill([
            'phone' => '+32471000002',
            'phone_verified_at' => now(),
        ])->save();

        $this->assertNotNull(
            $user->fresh()->phone_verified_at,
            "Écrire le numéro et sa vérification ensemble doit rester possible : c'est ce que fait la vérification par SMS."
        );
    }

    private function compteVerifie(): User
    {
        return User::factory()->create([
            'phone' => '+32471000900',
            'phone_verified_at' => now(),
        ]);
    }
}
