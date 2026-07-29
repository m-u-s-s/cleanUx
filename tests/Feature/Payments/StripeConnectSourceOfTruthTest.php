<?php

namespace Tests\Feature\Payments;

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Où se lit l'état d'un compte Stripe Connect.
 *
 * Les colonnes `stripe_connect_*` existent sur `users` ET sur `provider_profiles`. Une seule est
 * alimentée : StripeConnectService écrit sur `users`, rien n'écrit jamais sur le profil.
 *
 * `canReceiveStripeConnectPayments()` ne lisait pourtant que le profil. Elle rendait donc `false`
 * pour TOUT prestataire, y compris un compte Stripe pleinement configuré — et
 * MissionPaymentService::authorize() refusait chaque autorisation de paiement en conséquence.
 *
 * Le défaut était masqué par son propre test, dont le fixture renseigne les deux tables : une
 * forme qui ne se produit jamais en production. Ce test-ci décrit la forme réelle.
 */
class StripeConnectSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La forme que produit réellement StripeConnectService : le compte est sur `users`, le profil
     * n'en sait rien. C'est le cas de tous les prestataires en production.
     */
    public function test_an_account_written_only_on_the_user_is_recognised(): void
    {
        $user = User::factory()->employe()->create([
            'stripe_connect_account_id' => 'acct_reel',
            'stripe_connect_status' => 'active',
        ]);
        ProviderProfile::factory()->create(['user_id' => $user->id]);

        $this->assertTrue(
            $user->fresh()->canReceiveStripeConnectPayments(),
            'un compte Stripe actif écrit par StripeConnectService doit être reconnu'
        );
    }

    /** Les environnements dont le profil porte l'information restent acceptés. */
    public function test_an_account_written_only_on_the_profile_is_still_recognised(): void
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'stripe_connect_account_id' => 'acct_legacy',
            'stripe_connect_status' => 'active',
        ]);

        $this->assertTrue($user->fresh()->canReceiveStripeConnectPayments());
    }

    /** Un compte créé mais non finalisé ne doit pas ouvrir les paiements. */
    public function test_a_pending_account_cannot_receive_payments(): void
    {
        $user = User::factory()->employe()->create([
            'stripe_connect_account_id' => 'acct_en_cours',
            'stripe_connect_status' => 'pending',
        ]);
        ProviderProfile::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->fresh()->canReceiveStripeConnectPayments());
    }

    public function test_no_account_at_all_cannot_receive_payments(): void
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->fresh()->canReceiveStripeConnectPayments());
    }

    /**
     * `stripe_connect_onboarded_at` atteste l'aboutissement au même titre que le statut : un
     * compte peut être marqué terminé sans que le statut ait été rafraîchi.
     *
     * La date est portée par le PROFIL : `users` n'a pas cette colonne. StripeConnectService
     * tente pourtant de l'y écrire — la clé n'étant pas assignable en masse, elle est ignorée
     * en silence, ce qui explique qu'aucune erreur ne l'ait jamais signalé.
     */
    public function test_an_onboarded_date_is_enough(): void
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'stripe_connect_account_id' => 'acct_termine',
            'stripe_connect_status' => 'pending',
            'stripe_connect_onboarded_at' => now(),
        ]);

        $this->assertTrue($user->fresh()->canReceiveStripeConnectPayments());
    }
}
