<?php

namespace Tests\Feature\Api\Auth;

use App\Models\OnboardingProgress;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\ProviderOnboardingJourneySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** L'inscription prestataire déclare le métier visé et ouvre le parcours de vérification. */
class ProviderRegistrationTradeAndJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProviderOnboardingJourneySeeder::class);
    }

    public function test_registering_as_a_provider_opens_the_verification_journey(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();
        $progress = OnboardingProgress::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(OnboardingProgress::STATUS_IN_PROGRESS, $progress->status);
        // SIX depuis l'ajout de la déclaration de véhicule.
        $this->assertSame(
            6,
            $progress->completions()->count(),
            'Les six vérifications obligatoires doivent être ouvertes dès la création du compte.'
        );
    }

    public function test_the_declared_trade_is_attached_to_the_provider(): void
    {
        $trade = Trade::first() ?? Trade::factory()->create();

        $this->postJson('/api/auth/register', $this->payload([
            'trade_id' => $trade->id,
            'trade_answers' => ['experience_years' => 7, 'intervention_radius_km' => 30],
        ]))->assertCreated();

        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();

        $pivot = DB::table('trade_user')->where('user_id', $user->id)->where('trade_id', $trade->id)->first();
        $this->assertNotNull($pivot, 'Sans métier rattaché, le matching n’a rien sur quoi travailler.');
        $this->assertSame(1, (int) $pivot->is_primary);

        // Les réponses métier sont conservées : elles nourrissent la revue admin et l'étape
        // « déclarer vos métiers » du parcours.
        $this->assertStringContainsString('experience_years', (string) $pivot->notes);
    }

    public function test_an_unknown_trade_is_rejected(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['trade_id' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('trade_id');

        $this->assertSame(0, User::where('email', 'nouveau@prestataire.test')->count());
    }

    /** L'app cliente poste sur le même endpoint : elle ne doit ni déclarer de métier ni ouvrir de parcours prestataire. */
    public function test_a_client_registration_opens_no_provider_journey(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Cliente',
            'email' => 'cliente@test.test',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'accept_terms' => true,
        ])->assertCreated();

        $user = User::where('email', 'cliente@test.test')->firstOrFail();
        $this->assertSame(0, OnboardingProgress::where('user_id', $user->id)->count());
    }

    /** Le parcours ne doit jamais faire échouer la création du compte : un module désactivé ou une configuration absente laisserait sinon l'utilisateur sans compte ET sans explication. */
    public function test_registration_still_succeeds_when_the_journey_cannot_start(): void
    {
        DB::table('onboarding_journeys')->update(['is_active' => false]);

        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $this->assertSame(1, User::where('email', 'nouveau@prestataire.test')->count());
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
            'account_type' => 'provider',
        ], $overrides);
    }
}
