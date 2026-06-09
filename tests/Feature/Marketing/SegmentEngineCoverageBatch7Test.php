<?php

namespace Tests\Feature\Marketing;

use App\Models\MarketingSegment;
use App\Models\MarketingSegmentMember;
use App\Models\User;
use App\Services\Marketing\SegmentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegmentEngineCoverageBatch7Test extends TestCase
{
    use RefreshDatabase;

    private function engine(): SegmentEngine
    {
        return app(SegmentEngine::class);
    }

    private function makeSegment(array $rules, bool $active = true): MarketingSegment
    {
        return MarketingSegment::create([
            'code' => 'seg_'.uniqid(),
            'name' => 'Batch7 segment',
            'rules' => $rules,
            'is_active' => $active,
        ]);
    }

    public function test_inactive_segment_short_circuits_to_zero(): void
    {
        User::factory()->client()->count(3)->create();

        $segment = $this->makeSegment([
            'field' => 'role', 'op' => 'eq', 'value' => 'client',
        ], active: false);

        $count = $this->engine()->compute($segment);

        $this->assertSame(0, $count);
        $this->assertSame(0, MarketingSegmentMember::count());
    }

    public function test_not_node_excludes_matching_users(): void
    {
        User::factory()->client()->create(['locale' => 'fr']);
        User::factory()->client()->create(['locale' => 'nl']);
        User::factory()->client()->create(['locale' => 'nl']);

        $segment = $this->makeSegment([
            'not' => ['field' => 'locale', 'op' => 'eq', 'value' => 'fr'],
        ]);

        $count = $this->engine()->compute($segment);

        $this->assertSame(2, $count);
    }

    public function test_neq_and_not_in_operators(): void
    {
        User::factory()->client()->count(2)->create();
        User::factory()->admin()->create();
        User::factory()->employe()->create();

        $neq = $this->makeSegment([
            'field' => 'role', 'op' => 'neq', 'value' => 'client',
        ]);
        $this->assertSame(2, $this->engine()->compute($neq));

        $notIn = $this->makeSegment([
            'field' => 'role', 'op' => 'not_in', 'value' => ['admin', 'employe'],
        ]);
        $this->assertSame(2, $this->engine()->compute($notIn));
    }

    public function test_comparison_operators_on_created_at(): void
    {
        User::factory()->client()->create(['created_at' => now()->subDays(40)]);
        User::factory()->client()->create(['created_at' => now()->subDays(5)]);

        $cutoff = now()->subDays(20)->toDateTimeString();

        $gt = $this->makeSegment(['field' => 'created_at', 'op' => 'gt', 'value' => $cutoff]);
        $this->assertSame(1, $this->engine()->compute($gt));

        $lte = $this->makeSegment(['field' => 'created_at', 'op' => 'lte', 'value' => $cutoff]);
        $this->assertSame(1, $this->engine()->compute($lte));

        $gte = $this->makeSegment(['field' => 'created_at', 'op' => 'gte', 'value' => $cutoff]);
        $this->assertSame(1, $this->engine()->compute($gte));

        $lt = $this->makeSegment(['field' => 'created_at', 'op' => 'lt', 'value' => $cutoff]);
        $this->assertSame(1, $this->engine()->compute($lt));
    }

    public function test_newer_than_days_operator(): void
    {
        User::factory()->client()->create(['created_at' => now()->subDays(2)]);
        User::factory()->client()->create(['created_at' => now()->subDays(90)]);

        $segment = $this->makeSegment([
            'field' => 'created_at', 'op' => 'newer_than_days', 'value' => 30,
        ]);

        $this->assertSame(1, $this->engine()->compute($segment));
    }

    public function test_is_null_and_is_not_null_operators(): void
    {
        User::factory()->client()->count(3)->create();

        // email is always populated -> is_not_null matches all, is_null matches none.
        $notNull = $this->makeSegment([
            'field' => 'email_domain', 'op' => 'is_not_null', 'value' => null,
        ]);
        $this->assertSame(3, $this->engine()->compute($notNull));

        $isNull = $this->makeSegment([
            'field' => 'email_domain', 'op' => 'is_null', 'value' => null,
        ]);
        $this->assertSame(0, $this->engine()->compute($isNull));
    }

    public function test_string_like_operators(): void
    {
        User::factory()->client()->create(['locale' => 'fr']);
        User::factory()->client()->create(['locale' => 'nl']);

        $contains = $this->makeSegment([
            'field' => 'locale', 'op' => 'contains', 'value' => 'r',
        ]);
        $this->assertSame(1, $this->engine()->compute($contains));

        $starts = $this->makeSegment([
            'field' => 'locale', 'op' => 'starts_with', 'value' => 'n',
        ]);
        $this->assertSame(1, $this->engine()->compute($starts));

        $ends = $this->makeSegment([
            'field' => 'locale', 'op' => 'ends_with', 'value' => 'l',
        ]);
        $this->assertSame(1, $this->engine()->compute($ends));
    }

    public function test_email_domain_ends_with(): void
    {
        User::factory()->client()->create(['email' => 'alice@acme.test']);
        User::factory()->client()->create(['email' => 'bob@acme.test']);
        User::factory()->client()->create(['email' => 'carol@other.test']);

        $segment = $this->makeSegment([
            'field' => 'email_domain', 'op' => 'ends_with', 'value' => '@acme.test',
        ]);

        $this->assertSame(2, $this->engine()->compute($segment));
    }

    public function test_preview_empty_rules_returns_zero(): void
    {
        User::factory()->client()->count(2)->create();

        $preview = $this->engine()->preview([]);

        $this->assertSame(0, $preview['count']);
        $this->assertSame([], $preview['sample']);
    }
}
