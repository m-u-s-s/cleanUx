<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\CustomerProfile;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\Referral;
use App\Models\Trade;
use App\Models\User;
use App\Services\Promotion\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNewUserCoverageBatch8Test extends TestCase
{
    use RefreshDatabase;

    private function action(): CreateNewUser
    {
        return app(CreateNewUser::class);
    }

    public function test_default_account_type_creates_personal_client_profile(): void
    {
        $user = $this->action()->create([
            'name' => 'Perso Default',
            'email' => 'perso-default@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertSame('client_personal', $user->account_type);
        $this->assertSame('client', $user->role);
        // `user`, ET NON `client` — corrigé le 2026-08-08.
        $this->assertSame('user', $user->platform_role);
        $this->assertSame('active', $user->status);
        $this->assertTrue((bool) $user->is_active);

        $profile = CustomerProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('personal', $profile->customer_type?->value ?? $profile->customer_type);
        $this->assertNotEmpty($user->fresh()->referral_code);
    }

    public function test_unknown_account_type_falls_back_to_personal_client(): void
    {
        $user = $this->action()->create([
            'name' => 'Mystery Type',
            'email' => 'mystery@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'something_unknown',
        ]);

        $profile = CustomerProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('personal', $profile->customer_type?->value ?? $profile->customer_type);
    }

    public function test_client_company_creates_organization_owner_and_company_profile(): void
    {
        $user = $this->action()->create([
            'name' => 'Boss Person',
            'email' => 'company-client@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'client_company',
            'company_name' => 'Acme Cleaning SARL',
            // NUMÉRO RÉEL — « BE0123456789 » n'a jamais eu une clé de contrôle valide, et l'inscription web l'acceptait sans examen là où l'API le refusait depuis longtemps.
            'tva_number' => 'BE0202239951',
        ]);

        $profile = CustomerProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('company', $profile->customer_type?->value ?? $profile->customer_type);

        $org = OrganizationAccount::where('email', 'company-client@example.test')->first();
        $this->assertNotNull($org);
        $this->assertSame('Acme Cleaning SARL', $org->name);
        $this->assertSame('BE0202239951', $org->tva_number);

        $fresh = $user->fresh();
        $this->assertSame($org->id, $fresh->current_organization_id);
        $this->assertSame($org->id, $fresh->organization_account_id);

        $this->assertTrue(
            OrganizationMember::where('organization_account_id', $org->id)
                ->where('user_id', $user->id)
                ->where('role', 'owner')
                ->exists()
        );
    }

    public function test_provider_independent_creates_provider_profile_and_attaches_trades(): void
    {
        $trade = Trade::factory()->create();

        $user = $this->action()->create([
            'name' => 'Indep Worker',
            'email' => 'indep-cov@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'provider_independent',
            'trade_ids' => [$trade->id],
        ]);

        $this->assertSame('employe', $user->role);

        $profile = ProviderProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('independent', $profile->provider_type?->value ?? $profile->provider_type);
        $this->assertSame('pending', $profile->status);

        $this->assertTrue($user->trades()->where('trades.id', $trade->id)->exists());
    }

    public function test_provider_company_creates_org_provider_profile_and_owner(): void
    {
        $user = $this->action()->create([
            'name' => 'Company Provider',
            'email' => 'provider-company@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'provider_company',
            'provider_company_name' => 'CleanPro Services',
        ]);

        $this->assertSame('employe', $user->role);

        $org = OrganizationAccount::where('name', 'CleanPro Services')->first();
        $this->assertNotNull($org);

        $profile = ProviderProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame($org->id, $profile->organization_account_id);

        $fresh = $user->fresh();
        $this->assertSame($org->id, $fresh->current_organization_id);
        $this->assertTrue(
            OrganizationMember::where('organization_account_id', $org->id)
                ->where('user_id', $user->id)
                ->where('role', 'owner')
                ->exists()
        );
    }

    public function test_organization_slug_is_made_unique_on_collision(): void
    {
        OrganizationAccount::create([
            'name' => 'Acme Cleaning SARL',
            'legal_name' => 'Acme Cleaning SARL',
            'slug' => 'acme-cleaning-sarl',
            'type' => 'client_company',
            'email' => 'existing@example.test',
            'status' => 'active',
        ]);

        $user = $this->action()->create([
            'name' => 'Second Boss',
            'email' => 'company-client-2@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'client_company',
            'company_name' => 'Acme Cleaning SARL',
        ]);

        $org = OrganizationAccount::where('email', 'company-client-2@example.test')->first();
        $this->assertNotNull($org);
        $this->assertNotSame('acme-cleaning-sarl', $org->slug);
        $this->assertStringStartsWith('acme-cleaning-sarl-', $org->slug);
    }

    public function test_valid_referral_code_registers_referral(): void
    {
        $referrer = User::factory()->client()->create();
        $code = app(ReferralService::class)->ensureReferralCode($referrer);

        $referee = $this->action()->create([
            'name' => 'Referred Friend',
            'email' => 'referred@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => $code,
        ]);

        $this->assertTrue(
            Referral::where('referee_user_id', $referee->id)
                ->where('referrer_user_id', $referrer->id)
                ->exists()
        );
    }

    public function test_invalid_referral_code_does_not_throw_and_creates_no_referral(): void
    {
        $referee = $this->action()->create([
            'name' => 'No Referral',
            'email' => 'no-referral@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'ref' => 'DOESNOTEXIST',
        ]);

        $this->assertSame(0, Referral::where('referee_user_id', $referee->id)->count());
    }
}
