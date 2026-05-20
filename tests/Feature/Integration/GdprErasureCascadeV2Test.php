<?php

namespace Tests\Feature\Integration;

use App\Models\BusinessEntity;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\FleetEquipment;
use App\Models\FleetVehicle;
use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\ChatV2\ChatService;
use App\Services\Gdpr\DataErasureService;
use App\Services\KybV2\BusinessOnboardingService;
use App\Services\SubscriptionsV2\SubscriptionEngine;
use App\Services\TenancyV2\TenantService;
use Database\Seeders\SubscriptionPlansV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Test E2E GDPR erasure cascade : vérifie que `DataErasureService::anonymizeUser()`
 * cascade correctement sur les nouveaux modules v2 (KYB, Fleet, Subscriptions,
 * Tenancy, Chat) en nullify-ant les FKs tout en conservant les rows.
 */
class GdprErasureCascadeV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlansV2Seeder::class);

        Config::set('gdpr.anonymized_email_template', 'deleted_{id}@anonymized.test');
        Config::set('gdpr.anonymized_name', 'Utilisateur supprimé');

        Config::set('kyb_v2.identifier_types_by_country', ['FR' => ['siret']]);
        Config::set('chat_v2.enabled', true);
        Config::set('chat_v2.allowed_context_types', ['booking', 'generic']);
        Config::set('chat_v2.broadcast_enabled', false);
        Config::set('subscriptions_v2.billing_driver', 'mock');
        Config::set('subscriptions_v2.periods', ['monthly' => 30, 'weekly' => 7]);
        Config::set('subscriptions_v2.allowed_currencies', ['EUR']);
        Config::set('subscriptions_v2.default_currency', 'EUR');
        Config::set('tenancy_v2.allowed_plans', ['basic', 'growth']);
        Config::set('tenancy_v2.default_plan', 'basic');
    }

    public function test_erasure_cascades_to_all_v2_modules(): void
    {
        $user = User::factory()->create(['name' => 'Original Name', 'email' => 'original@test.com']);

        // 1. KYB entity owned by user
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
            'contact_email' => 'contact@acme.test',
        ], $user);
        $this->assertSame($user->id, $entity->owner_user_id);

        // 2. Fleet vehicle current_provider = user
        $vehicle = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-GDP-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_IN_USE,
            'current_provider_id' => $user->id,
        ]);
        $equipment = FleetEquipment::query()->create([
            'code' => FleetEquipment::generateCode(),
            'name' => 'Karcher', 'equipment_type' => 'machine',
            'status' => FleetEquipment::STATUS_IN_USE,
            'current_provider_id' => $user->id,
        ]);

        // 3. Active subscription for user
        $plan = SubscriptionPlanV2::query()->where('code', 'cleaning_weekly_basic')->first();
        $sub = app(SubscriptionEngine::class)->subscribe($user, $plan);
        $this->assertSame(SubscriptionV2::STATUS_ACTIVE, $sub->status);

        // 4. Tenant user (member)
        $tenant = app(TenantService::class)->create(['name' => 'Acme', 'code' => 'acme', 'slug' => 'acme']);
        app(TenantService::class)->attachUser($tenant, $user, TenantUser::ROLE_MEMBER);

        // 5. Chat thread + message from user
        $thread = app(ChatService::class)->startThread('generic', 1, [
            ['user_id' => $user->id, 'role' => 'client'],
        ]);
        $msg = app(ChatService::class)->sendMessage($thread, $user, 'hello world');

        // === ERASURE ===
        app(DataErasureService::class)->anonymizeUser($user);

        // === ASSERTIONS ===
        $user->refresh();
        $this->assertStringStartsWith('deleted_', $user->email);
        $this->assertSame('Utilisateur supprimé', $user->name);
        $this->assertSame('deleted', $user->status);

        // KYB : owner nullifié, row conservée
        $this->assertNotNull(BusinessEntity::query()->find($entity->id));
        $this->assertNull(BusinessEntity::query()->find($entity->id)->owner_user_id);
        $this->assertNull(BusinessEntity::query()->find($entity->id)->contact_email);

        // Fleet : current_provider nullifié, rows conservées
        $this->assertNull($vehicle->fresh()->current_provider_id);
        $this->assertNull($equipment->fresh()->current_provider_id);

        // Subscription : cancelled, row conservée
        $sub->refresh();
        $this->assertSame(SubscriptionV2::STATUS_CANCELLED, $sub->status);
        $this->assertNotNull($sub->cancelled_at);

        // Tenant user : left_at set, is_active false
        $tu = TenantUser::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($tu);
        $this->assertFalse((bool) $tu->is_active);
        $this->assertNotNull($tu->left_at);

        // Chat : participant marked left + can_send=false + message sender nullified, body conserved
        $participant = ChatParticipant::query()
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($participant);
        $this->assertNotNull($participant->left_at);
        $this->assertFalse((bool) $participant->can_send);

        $messageRow = ChatMessage::query()->find($msg->id);
        $this->assertNotNull($messageRow);
        $this->assertNull($messageRow->sender_user_id);
        $this->assertSame('hello world', $messageRow->body);
    }

    public function test_erasure_safe_when_v2_modules_have_no_data(): void
    {
        $user = User::factory()->create();
        app(DataErasureService::class)->anonymizeUser($user);
        $user->refresh();
        $this->assertStringStartsWith('deleted_', $user->email);
    }
}
