<?php

namespace Tests\Feature\Parity;

use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParityRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function parityKeysFor(User $user): array
    {
        Sanctum::actingAs($user);

        return collect($this->getJson('/api/parity-map')->assertOk()->json('data'))
            ->pluck('key')->all();
    }

    public function test_individual_client_sees_client_and_public_but_not_admin_provider_or_company_only(): void
    {
        $keys = $this->parityKeysFor(User::factory()->client()->create());

        foreach (config('parity.modules', []) as $module) {
            $roles = $module['roles'] ?? [];
            $key = $module['key'];

            if ($roles === ['client']) {
                $this->assertContains($key, $keys, "individual client should see client module {$key}");
            }
            // admin-only, provider-only, entreprise-only, provider_company-only must be hidden
            foreach (['admin', 'provider', 'entreprise', 'provider_company'] as $exclusive) {
                if ($roles === [$exclusive]) {
                    $this->assertNotContains($key, $keys, "individual client must NOT see {$exclusive}-only module {$key}");
                }
            }
        }
    }

    public function test_provider_company_sees_its_modules_and_not_entreprise_client(): void
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::COMPANY_WORKER,
        ]);

        $keys = $this->parityKeysFor($user);

        $hasPrestataire = false;
        foreach (config('parity.modules', []) as $module) {
            $roles = $module['roles'] ?? [];
            if ($roles === ['provider_company']) {
                $hasPrestataire = true;
                $this->assertContains($module['key'], $keys, "provider-company should see {$module['key']}");
            }
            if ($roles === ['entreprise']) {
                $this->assertNotContains($module['key'], $keys, "provider-company must NOT see entreprise-client module {$module['key']}");
            }
        }
        $this->assertTrue($hasPrestataire, 'expected at least one provider_company module in the registry');
    }

    public function test_client_company_sees_entreprise_client_and_not_provider_company(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->entreprise()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => $org->id,
        ]);

        $keys = $this->parityKeysFor($user);

        $hasEntrepriseClient = false;
        foreach (config('parity.modules', []) as $module) {
            $roles = $module['roles'] ?? [];
            if ($roles === ['entreprise']) {
                $hasEntrepriseClient = true;
                $this->assertContains($module['key'], $keys, "client-company should see {$module['key']}");
            }
            if ($roles === ['provider_company']) {
                $this->assertNotContains($module['key'], $keys, "client-company must NOT see provider-company module {$module['key']}");
            }
        }
        $this->assertTrue($hasEntrepriseClient, 'expected at least one entreprise (client-company) module in the registry');
    }

    public function test_client_cannot_access_an_admin_module_path(): void
    {
        $admin = collect(config('parity.modules', []))
            ->first(fn ($m) => ($m['roles'] ?? []) === ['admin']);
        $this->assertNotNull($admin, 'expected at least one admin-only module');

        $client = User::factory()->client()->create();
        $status = $this->actingAs($client)->get('/'.ltrim($admin['path'], '/'))->getStatusCode();

        $this->assertNotSame(200, $status, "individual client got 200 on admin path {$admin['path']} — authz gap");
    }
}
