<?php

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingJourney;
use Database\Seeders\OnboardingJourneysSeeder;
use Database\Seeders\ProviderOnboardingJourneySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le parcours `provider_default` n'a qu'un seul propriétaire. */
class ProviderJourneySeedingTest extends TestCase
{
    use RefreshDatabase;

    /** Exactement les codes que STEP_COMPONENTS sait rendre côté mobile. */
    private const MOBILE_STEP_CODES = [
        'profile_complete',
        'contract_sign',
        'kyc_check',
        'document_upload',
        // `vehicle_declare` ne concerne que les métiers sous règles taxi, mais elle est dans le parcours de TOUT LE MONDE — le parcours est unique et partagé.
        'vehicle_declare',
        'skill_declare',
    ];

    public function test_the_provider_journey_matches_the_mobile_steps_whatever_the_seeding_order(): void
    {
        $this->seed(ProviderOnboardingJourneySeeder::class);
        $this->seed(OnboardingJourneysSeeder::class);

        $this->assertSame(self::MOBILE_STEP_CODES, $this->providerStepCodes());
    }

    public function test_the_order_does_not_matter(): void
    {
        $this->seed(OnboardingJourneysSeeder::class);
        $this->seed(ProviderOnboardingJourneySeeder::class);

        $this->assertSame(self::MOBILE_STEP_CODES, $this->providerStepCodes());
    }

    /** Le parcours client vit toujours dans OnboardingJourneysSeeder : en retirer le provider ne doit pas l'avoir emporté. */
    public function test_the_client_journey_is_preserved(): void
    {
        $this->seed(OnboardingJourneysSeeder::class);

        $client = OnboardingJourney::where('code', 'client_default')->first();

        $this->assertNotNull($client);
        $this->assertGreaterThan(0, $client->steps()->count());
    }

    /** Rejouer les seeders ne doit ni dupliquer ni vider les étapes. */
    public function test_seeding_twice_is_idempotent(): void
    {
        $this->seed(ProviderOnboardingJourneySeeder::class);
        $this->seed(ProviderOnboardingJourneySeeder::class);

        $this->assertSame(self::MOBILE_STEP_CODES, $this->providerStepCodes());
    }

    /** @return array<int, string> */
    private function providerStepCodes(): array
    {
        return OnboardingJourney::where('code', 'provider_default')
            ->firstOrFail()
            ->steps()
            ->orderBy('position')
            ->pluck('code')
            ->all();
    }
}
