<?php

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingJourney;
use Database\Seeders\OnboardingJourneysSeeder;
use Database\Seeders\ProviderOnboardingJourneySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le parcours `provider_default` n'a qu'un seul propriétaire.
 *
 * Deux seeders l'écrivaient : ProviderOnboardingJourneySeeder, avec les cinq codes que l'app
 * mobile sait rendre, et OnboardingJourneysSeeder, avec sept codes différents et un
 * `steps()->delete()` destructif. ProductionBootstrapSeeder appelant le second APRÈS le
 * référentiel, c'est lui qui gagnait en production — et comme STEP_COMPONENTS
 * (ProviderOnboardingScreen) ne connaît pas ses codes, CHAQUE étape se serait affichée
 * « non disponible ». Le parcours de vérification aurait été entièrement infranchissable.
 *
 * Ce test verrouille l'invariant dans les deux ordres d'exécution, l'ordre réel dépendant du
 * profil de seed employé.
 */
class ProviderJourneySeedingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Exactement les codes que STEP_COMPONENTS sait rendre côté mobile. Toute divergence rend
     * l'étape correspondante inutilisable dans l'application.
     */
    private const MOBILE_STEP_CODES = [
        'profile_complete',
        'contract_sign',
        'kyc_check',
        'document_upload',
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

    /**
     * Le parcours client vit toujours dans OnboardingJourneysSeeder : en retirer le provider ne
     * doit pas l'avoir emporté.
     */
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
