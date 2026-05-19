<?php

namespace Tests\Feature\Marketing;

use App\Models\MarketingSegment;
use App\Models\MarketingSegmentMember;
use App\Models\User;
use App\Services\Marketing\SegmentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegmentEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSegment(array $rules): MarketingSegment
    {
        return MarketingSegment::create([
            'code' => 'seg_' . uniqid(),
            'name' => 'Test segment',
            'rules' => $rules,
            'is_active' => true,
        ]);
    }

    public function test_compute_empty_rules_yields_zero_members(): void
    {
        $segment = $this->makeSegment([]);

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(0, $count);
        $this->assertSame(0, MarketingSegmentMember::count());
    }

    public function test_eq_role_client_matches_only_clients(): void
    {
        User::factory()->client()->count(3)->create();
        User::factory()->admin()->count(2)->create();

        $segment = $this->makeSegment([
            'field' => 'role', 'op' => 'eq', 'value' => 'client',
        ]);

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(3, $count);
        $this->assertSame(3, $segment->fresh()->member_count);
    }

    public function test_and_combines_constraints(): void
    {
        User::factory()->client()->create(['locale' => 'fr']);
        User::factory()->client()->create(['locale' => 'nl']);
        User::factory()->admin()->create(['locale' => 'fr']);

        $segment = $this->makeSegment([
            'and' => [
                ['field' => 'role', 'op' => 'eq', 'value' => 'client'],
                ['field' => 'locale', 'op' => 'eq', 'value' => 'fr'],
            ],
        ]);

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(1, $count);
    }

    public function test_or_unions_constraints(): void
    {
        User::factory()->client()->create(['locale' => 'fr']);
        User::factory()->client()->create(['locale' => 'nl']);
        User::factory()->admin()->create(['locale' => 'en']);

        $segment = $this->makeSegment([
            'or' => [
                ['field' => 'locale', 'op' => 'eq', 'value' => 'fr'],
                ['field' => 'locale', 'op' => 'eq', 'value' => 'en'],
            ],
        ]);

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(2, $count);
    }

    public function test_older_than_days_matches_old_accounts(): void
    {
        User::factory()->client()->create(['created_at' => now()->subDays(60)]);
        User::factory()->client()->create(['created_at' => now()->subDays(10)]);

        $segment = $this->makeSegment([
            'field' => 'created_at', 'op' => 'older_than_days', 'value' => 30,
        ]);

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(1, $count);
    }

    public function test_unknown_field_yields_zero_members(): void
    {
        User::factory()->client()->count(3)->create();

        $segment = $this->makeSegment([
            'field' => 'arbitrary_xyz', 'op' => 'eq', 'value' => 'anything',
        ]);

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(0, $count);
    }

    public function test_unknown_operator_yields_zero_members(): void
    {
        User::factory()->client()->count(3)->create();

        $segment = $this->makeSegment([
            'field' => 'role', 'op' => 'regex_match', 'value' => 'foo',
        ]);

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(0, $count);
    }

    public function test_in_operator_matches_set(): void
    {
        User::factory()->client()->create();
        User::factory()->admin()->create();
        User::factory()->employe()->create();

        $segment = $this->makeSegment([
            'field' => 'role', 'op' => 'in', 'value' => ['client', 'admin'],
        ]);

        $count = app(SegmentEngine::class)->compute($segment);

        $this->assertSame(2, $count);
    }

    public function test_preview_returns_count_and_sample(): void
    {
        User::factory()->client()->count(5)->create();

        $preview = app(SegmentEngine::class)->preview([
            'field' => 'role', 'op' => 'eq', 'value' => 'client',
        ], limit: 3);

        $this->assertSame(5, $preview['count']);
        $this->assertCount(3, $preview['sample']);
    }

    public function test_recompute_replaces_members(): void
    {
        $u1 = User::factory()->client()->create();
        $u2 = User::factory()->client()->create();

        $segment = $this->makeSegment([
            'field' => 'role', 'op' => 'eq', 'value' => 'client',
        ]);

        app(SegmentEngine::class)->compute($segment);
        $this->assertSame(2, MarketingSegmentMember::count());

        // Delete one, recompute — count should drop
        $u1->delete();
        app(SegmentEngine::class)->compute($segment);
        $this->assertSame(1, MarketingSegmentMember::count());
    }
}
