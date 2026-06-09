<?php

namespace Tests\Feature\Assistant;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Assistant\Tools\Implementations\RegisterSiteTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterSiteToolCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    private function tool(): RegisterSiteTool
    {
        return new RegisterSiteTool;
    }

    public function test_metadata_name_description_and_immediacy(): void
    {
        $tool = $this->tool();

        $this->assertSame('register_site', $tool->name());
        $this->assertStringContainsString('site', $tool->description());
        $this->assertFalse($tool->executesImmediately());
    }

    public function test_input_schema_shape(): void
    {
        $schema = $this->tool()->inputSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('postal_code', $schema['properties']);
        $this->assertSame(
            ['office', 'shop', 'school', 'warehouse', 'restaurant', 'other'],
            $schema['properties']['type']['enum']
        );
        $this->assertSame(
            ['name', 'address', 'city', 'postal_code'],
            $schema['required']
        );
    }

    public function test_authorize_returns_false_without_organization(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'organization_account_id' => null,
            'current_organization_id' => null,
        ]);

        $this->assertFalse($this->tool()->authorize($user));
    }

    public function test_authorize_returns_false_when_current_organization_missing(): void
    {
        $org = OrganizationAccount::factory()->create();

        // organization_account_id present (so orgId is truthy) but no
        // current_organization_id -> currentOrganization relation is null.
        $user = User::factory()->create([
            'role' => 'client',
            'organization_account_id' => $org->id,
            'current_organization_id' => null,
        ]);

        $this->assertFalse($this->tool()->authorize($user));
    }

    public function test_authorize_returns_false_without_permission(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create([
            'role' => 'client',
            'current_organization_id' => $org->id,
        ]);

        // No membership at all -> PermissionService::can returns false.
        $this->assertFalse($this->tool()->authorize($user));
    }

    public function test_authorize_returns_true_for_owner_member(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create([
            'role' => 'client',
            'current_organization_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
        ]);

        $this->assertTrue($this->tool()->authorize($user));
    }

    public function test_execute_creates_site_with_defaults(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create([
            'role' => 'client',
            'current_organization_id' => $org->id,
        ]);

        $result = $this->tool()->execute($user, [
            'name' => 'Bureaux Bruxelles',
            'address' => 'Rue de la Loi 1',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('Bureaux Bruxelles', $result['site_name']);
        $this->assertStringContainsString('Bureaux Bruxelles', $result['message']);
        $this->assertNotNull($result['site_id']);

        $site = OrganizationSite::find($result['site_id']);
        $this->assertNotNull($site);
        $this->assertSame($org->id, $site->organization_account_id);
        $this->assertSame('BE', $site->country);
    }

    public function test_execute_honours_provided_optional_fields(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create([
            'role' => 'client',
            'current_organization_id' => $org->id,
        ]);

        $result = $this->tool()->execute($user, [
            'name' => 'Magasin Anvers',
            'type' => 'shop',
            'address' => 'Meir 100',
            'city' => 'Anvers',
            'postal_code' => '2000',
            'country' => 'FR',
            'notes' => 'Accès par derrière.',
        ]);

        $this->assertTrue($result['ok']);

        $site = OrganizationSite::find($result['site_id']);
        $this->assertSame('FR', $site->country);
        $this->assertSame('Accès par derrière.', $site->notes);
    }

    public function test_role_owner_enum_resolves(): void
    {
        // Sanity guard that the OWNER enum used in setup matches matrix.
        $this->assertSame('owner', OrganizationRole::OWNER->value);
    }
}
