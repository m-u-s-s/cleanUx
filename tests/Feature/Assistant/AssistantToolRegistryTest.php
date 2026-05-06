<?php

namespace Tests\Feature\Assistant;

use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Assistant\Tools\AssistantToolRegistry;
use App\Services\Assistant\Tools\Implementations\CreateBookingTool;
use App\Services\Assistant\Tools\Implementations\ListMyBookingsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_client_gets_basic_tool_set(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
        ]);

        $registry = app(AssistantToolRegistry::class);
        $tools    = $registry->toolsForUser($user);
        $names    = array_map(fn ($t) => $t->name(), $tools);

        $this->assertContains('list_my_bookings', $names);
        $this->assertContains('create_booking',  $names);
        $this->assertContains('cancel_booking',  $names);

        // Pas de tool entreprise
        $this->assertNotContains('list_my_sites', $names);
    }

    public function test_company_client_gets_sites_tool(): void
    {
        $org  = OrganizationAccount::factory()->create();
        $user = User::factory()->create([
            'role'                    => 'client',
            'organization_account_id' => $org->id,
        ]);

        $registry = app(AssistantToolRegistry::class);
        $names    = array_map(fn ($t) => $t->name(), $registry->toolsForUser($user));

        $this->assertContains('list_my_sites', $names);
    }

    public function test_provider_independent_only_gets_read_tools(): void
    {
        $user = User::factory()->create([
            'role' => 'employe',
            // ProviderType INDEPENDENT (selon le mapping de assistantContextRole())
        ]);

        $registry = app(AssistantToolRegistry::class);
        $names    = array_map(fn ($t) => $t->name(), $registry->toolsForUser($user));

        $this->assertContains('list_my_bookings', $names);
        $this->assertNotContains('create_booking', $names);
        $this->assertNotContains('cancel_booking', $names);
    }

    public function test_definitions_for_user_returns_anthropic_tool_format(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $registry    = app(AssistantToolRegistry::class);
        $definitions = $registry->definitionsForUser($user);

        $this->assertNotEmpty($definitions);

        foreach ($definitions as $def) {
            $this->assertArrayHasKey('name',         $def);
            $this->assertArrayHasKey('description',  $def);
            $this->assertArrayHasKey('input_schema', $def);
            $this->assertSame('object', $def['input_schema']['type']);
        }
    }

    public function test_find_returns_tool_by_name(): void
    {
        $registry = app(AssistantToolRegistry::class);

        $this->assertInstanceOf(ListMyBookingsTool::class, $registry->find('list_my_bookings'));
        $this->assertInstanceOf(CreateBookingTool::class,  $registry->find('create_booking'));
        $this->assertNull($registry->find('this_tool_does_not_exist'));
    }
}
