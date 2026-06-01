<?php

namespace Tests\Feature\Relations;

use App\Models\BookingFavorite;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\Booking\ProviderSelectionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderSelectionResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): ProviderSelectionResolver
    {
        return app(ProviderSelectionResolver::class);
    }

    private function standardClient(): User
    {
        $client = User::factory()->client()->create();
        CustomerProfile::query()->create([
            'user_id' => $client->id,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);

        return $client->fresh();
    }

    private function premiumClient(): User
    {
        $client = User::factory()->client()->create();
        CustomerProfile::query()->create([
            'user_id' => $client->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        return $client->fresh();
    }

    #[Test]
    public function favorite_provider_is_allowed_for_everyone_even_non_premium(): void
    {
        $client = $this->standardClient();
        $fav = User::factory()->create();

        BookingFavorite::query()->create([
            'client_user_id' => $client->id,
            'preferred_provider_user_id' => $fav->id,
        ]);

        $result = $this->resolver()->resolve($client, [
            'provider_type_preference' => 'any',
            'preferred_provider_user_id' => $fav->id,
        ]);

        $this->assertSame($fav->id, $result['preferred_provider_user_id']);
        $this->assertSame('any', $result['provider_type_preference']);
    }

    #[Test]
    public function new_provider_is_rejected_for_non_premium_client(): void
    {
        $client = $this->standardClient();
        $stranger = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->resolver()->resolve($client, [
            'provider_type_preference' => 'any',
            'preferred_provider_user_id' => $stranger->id,
        ]);
    }

    #[Test]
    public function new_provider_is_allowed_for_premium_client(): void
    {
        $client = $this->premiumClient();
        $stranger = User::factory()->create();

        $result = $this->resolver()->resolve($client, [
            'provider_type_preference' => 'independent',
            'preferred_provider_user_id' => $stranger->id,
        ]);

        $this->assertSame($stranger->id, $result['preferred_provider_user_id']);
        $this->assertSame('independent', $result['provider_type_preference']);
    }

    #[Test]
    public function no_preferred_provider_falls_back_to_auto_tier(): void
    {
        $client = $this->standardClient();

        $result = $this->resolver()->resolve($client, [
            'provider_type_preference' => 'garbage_value',
        ]);

        $this->assertNull($result['preferred_provider_user_id']);
        $this->assertSame('any', $result['provider_type_preference']);
    }
}
