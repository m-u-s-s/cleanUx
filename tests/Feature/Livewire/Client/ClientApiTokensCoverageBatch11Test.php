<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\ClientApiTokens;
use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Models\User;
use App\Services\ApiTokensV2\ApiTokenManager;
use Database\Seeders\ApiTokenScopesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class ClientApiTokensCoverageBatch11Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApiTokenScopesSeeder::class);
        Config::set('api_tokens_v2.default_expiry_days', 365);
        Config::set('api_tokens_v2.rotation_grace_hours', 24);
        Config::set('api_tokens_v2.owner_roles', ['api_partner', 'admin', 'client', 'provider']);
        Config::set('api_tokens_v2.default_owner_role', 'api_partner');
    }

    private function makeToken(User $user, string $name = 'My token'): PersonalAccessTokenV2
    {
        return app(ApiTokenManager::class)->createForUser($user, [
            'name' => $name,
            'scopes' => ['read:bookings'],
            'owner_role' => 'api_partner',
        ])->accessToken;
    }

    public function test_render_shows_tokens_and_available_scopes(): void
    {
        $user = User::factory()->client()->create();
        $this->makeToken($user, 'Visible token');

        Livewire::actingAs($user)
            ->test(ClientApiTokens::class)
            ->assertOk()
            ->assertSee('Visible token');
    }

    public function test_create_token_persists_and_exposes_plain_text_once(): void
    {
        $user = User::factory()->client()->create();

        $component = Livewire::actingAs($user)
            ->test(ClientApiTokens::class)
            ->set('newName', 'Acme prod')
            ->set('newDescription', 'Integration token')
            ->set('newScopes', ['read:bookings', 'write:bookings'])
            ->set('newExpiryDays', 30)
            ->set('newRateLimit', 250)
            ->call('createToken')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('newName', '')
            ->assertSet('newScopes', [])
            ->assertSet('newRateLimit', null);

        $this->assertNotNull($component->get('justCreatedToken'));
        $meta = $component->get('justCreatedMeta');
        $this->assertSame('Acme prod', $meta['name']);
        $this->assertNotNull($meta['expires_at']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Acme prod',
            'tokenable_id' => $user->id,
            'rate_limit_per_minute' => 250,
        ]);
    }

    public function test_create_token_validation_error_blocks_creation(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientApiTokens::class)
            ->set('newName', '')
            ->set('newScopes', [])
            ->call('createToken')
            ->assertHasErrors(['newName', 'newScopes'])
            ->assertSet('justCreatedToken', null);
    }

    public function test_create_token_with_invalid_scope_dispatches_error_toast(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientApiTokens::class)
            ->set('newName', 'Bad scopes')
            ->set('newScopes', ['definitely:not:a:scope'])
            ->call('createToken')
            ->assertDispatched('toast')
            ->assertSet('justCreatedToken', null);

        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Bad scopes']);
    }

    public function test_rotate_issues_new_token_and_grace_window(): void
    {
        $user = User::factory()->client()->create();
        $token = $this->makeToken($user, 'Rotate me');

        $component = Livewire::actingAs($user)
            ->test(ClientApiTokens::class)
            ->call('rotate', $token->id)
            ->assertDispatched('toast');

        $this->assertNotNull($component->get('justRotatedToken'));
        $meta = $component->get('justRotatedMeta');
        $this->assertSame($token->id, $meta['previous_id']);
        $this->assertNotNull($meta['grace_until']);
        $this->assertNotNull($token->fresh()->rotation_grace_until);
    }

    public function test_rotate_unknown_token_dispatches_error(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientApiTokens::class)
            ->call('rotate', 999999)
            ->assertDispatched('toast')
            ->assertSet('justRotatedToken', null);
    }

    public function test_rotate_other_users_token_is_not_found(): void
    {
        $owner = User::factory()->client()->create();
        $intruder = User::factory()->client()->create();
        $token = $this->makeToken($owner, 'Owner token');

        Livewire::actingAs($intruder)
            ->test(ClientApiTokens::class)
            ->call('rotate', $token->id)
            ->assertSet('justRotatedToken', null);
    }

    public function test_revoke_deletes_owned_token(): void
    {
        $user = User::factory()->client()->create();
        $token = $this->makeToken($user, 'Revoke me');

        Livewire::actingAs($user)
            ->test(ClientApiTokens::class)
            ->call('revoke', $token->id)
            ->assertDispatched('toast');

        $this->assertSame(0, PersonalAccessTokenV2::query()->where('id', $token->id)->count());
    }

    public function test_revoke_unknown_token_is_noop(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientApiTokens::class)
            ->call('revoke', 999999)
            ->assertOk();
    }

    public function test_dismiss_handlers_clear_one_shot_state(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientApiTokens::class)
            ->set('justCreatedToken', 'plain-text-created')
            ->set('justCreatedMeta', ['id' => 1])
            ->set('justRotatedToken', 'plain-text-rotated')
            ->set('justRotatedMeta', ['id' => 2])
            ->call('dismissNewToken')
            ->assertSet('justCreatedToken', null)
            ->assertSet('justCreatedMeta', null)
            ->call('dismissRotatedToken')
            ->assertSet('justRotatedToken', null)
            ->assertSet('justRotatedMeta', null);
    }
}
