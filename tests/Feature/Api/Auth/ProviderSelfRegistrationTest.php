<?php

namespace Tests\Feature\Api\Auth;

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * L'inscription depuis l'app prestataire menait à un compte définitivement mort.
 *
 * ApiAuthController::register posait `role => 'client'` en dur, et TOUTE la surface prestataire
 * — y compris /provider/onboarding/*, la route censée transformer le compte en prestataire —
 * est gardée par `role:employe`. Un prestataire qui s'inscrivait obtenait donc un compte client
 * qui se connectait au dashboard et recevait 403 sur chaque appel, sans aucun moyen d'en sortir.
 *
 * L'inscription accepte désormais `account_type=provider`. Le compte créé peut compléter son
 * dossier mais rien d'autre, tant qu'il n'est pas approuvé.
 */
class ProviderSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const OPEN_ROUTE = '/api/provider/onboarding/progress';

    private const GATED_ROUTE = '/api/provider/wallet/balance';

    public function test_registering_as_a_provider_creates_an_employe_account_awaiting_approval(): void
    {
        $response = $this->postJson('/api/auth/register', $this->payload(['account_type' => 'provider']));

        $response->assertCreated();

        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();
        $this->assertSame('employe', $user->role);

        $profile = ProviderProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $profile->status);
        $this->assertSame('unverified', $profile->verification_status);
        $this->assertNotNull(
            $profile->self_registered_at,
            "self_registered_at marque les comptes créés par l'app ; c'est lui qui porte la restriction."
        );
    }

    /**
     * L'app cliente poste sur le même endpoint et ne doit rien changer à son comportement.
     */
    public function test_registering_without_an_account_type_still_creates_a_client(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();
        $this->assertSame('client', $user->role);
        $this->assertNull(ProviderProfile::where('user_id', $user->id)->first());
    }

    public function test_a_self_registered_provider_can_reach_the_onboarding_routes(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['account_type' => 'provider']))->assertCreated();
        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();

        $this->actingAs($user, 'sanctum')->getJson(self::OPEN_ROUTE)->assertOk();
    }

    public function test_a_self_registered_provider_cannot_reach_the_rest_of_the_provider_surface(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['account_type' => 'provider']))->assertCreated();
        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();

        $this->actingAs($user, 'sanctum')->getJson(self::GATED_ROUTE)->assertForbidden();
    }

    public function test_approving_a_self_registered_provider_opens_the_full_surface(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['account_type' => 'provider']))->assertCreated();
        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();

        ProviderProfile::where('user_id', $user->id)->update(['status' => 'active']);

        $this->actingAs($user, 'sanctum')->getJson(self::GATED_ROUTE)->assertOk();
    }

    /**
     * Anti-régression la plus importante de ce lot. Sur la base réelle, 4 comptes prestataires
     * sur 9 ne sont pas `active` : une garde qui exigerait ce statut mettrait dehors des
     * prestataires légitimes déjà en production. La restriction ne vise QUE les comptes portant
     * self_registered_at, que les lignes existantes n'ont pas.
     */
    public function test_a_provider_created_before_this_change_is_never_gated(): void
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'pending',
            'verification_status' => 'unverified',
            // self_registered_at absent : c'est l'état de toutes les lignes antérieures.
        ]);

        $this->actingAs($user, 'sanctum')->getJson(self::GATED_ROUTE)->assertOk();
    }

    /**
     * `User implements MustVerifyEmail` et EventServiceProvider écoute Registered avec
     * SendEmailVerificationNotification — mais l'événement n'était jamais émis à l'inscription :
     * aucun email de vérification ne partait jamais d'une création de compte mobile.
     */
    public function test_registering_dispatches_the_verification_event(): void
    {
        Event::fake([Registered::class]);

        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        Event::assertDispatched(Registered::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nouveau Prestataire',
            'email' => 'nouveau@prestataire.test',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'accept_terms' => true,
        ], $overrides);
    }
}
