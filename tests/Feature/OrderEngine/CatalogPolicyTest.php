<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\QuestionnaireBuilder;
use App\Models\Question;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/** L'autorisation d'écrire le catalogue tient dans UNE règle, et les écrans la consultent. */
class CatalogPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    /** @dataProvider catalogModels */
    public function test_the_policy_answers_for_every_catalog_model(string $model): void
    {
        $this->assertNotNull(
            Gate::getPolicyFor($model),
            sprintf('Aucune Policy pour %s : la règle vit ailleurs, en plusieurs exemplaires.', $model),
        );
    }

    public static function catalogModels(): array
    {
        return [
            'secteur' => [Sector::class],
            'métier' => [Trade::class],
            'question' => [Question::class],
        ];
    }

    public function test_a_full_admin_may_publish(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'platform_role' => 'admin']);

        $this->assertTrue(Gate::forUser($admin)->allows('publish', Trade::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', Trade::class));
    }

    /** Le lecteur seul LIT. C'est la règle que trois endroits redisaient chacun à sa façon. */
    public function test_a_read_only_admin_may_not_write(): void
    {
        $readonly = User::factory()->create([
            'role' => 'admin', 'platform_role' => 'admin', 'access_scope' => 'readonly',
        ]);

        $this->assertFalse(Gate::forUser($readonly)->allows('publish', Trade::class));
        $this->assertFalse(Gate::forUser($readonly)->allows('update', Trade::class));
        $this->assertTrue(Gate::forUser($readonly)->allows('viewAny', Trade::class));
    }

    public function test_a_client_may_not_even_look(): void
    {
        $client = User::factory()->client()->create();

        $this->assertFalse(Gate::forUser($client)->allows('viewAny', Trade::class));
    }

    /** L'ÉCRAN CONSULTE LA POLICY — il ne redit pas la règle. */
    public function test_the_builder_defers_to_the_policy(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'platform_role' => 'admin']));

        // On remplace LA POLICY, pas une capacité nommée.
        Gate::policy(Trade::class, RefuseTout::class);

        $trade = Trade::where('slug', 'peinture')->firstOrFail();
        $before = $trade->formRevisions()->count();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('publish')
            ->assertHasErrors('publication');

        $this->assertSame(
            $before,
            $trade->formRevisions()->count(),
            'Le composant publie malgré un refus de la Policy : il garde sa propre règle.',
        );
    }
}

/** Une Policy qui refuse tout, pour prouver que l'écran la consulte vraiment. */
class RefuseTout
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function archive(User $user): bool
    {
        return false;
    }

    public function publish(User $user): bool
    {
        return false;
    }
}
