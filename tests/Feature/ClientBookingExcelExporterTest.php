<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Client\Exports\ClientBookingExcelExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Coverage for ClientBookingExcelExporter.
 *
 * The public export() method depends on phpoffice/phpspreadsheet, which is not
 * installed in this environment, so the .xlsx generation path is not exercisable
 * here. This suite covers the reachable business logic of the exporter — the
 * query scoping/filtering (buildBookingQuery) plus the pure helpers
 * (describePeriod, statusColor) — via reflection.
 */
class ClientBookingExcelExporterTest extends TestCase
{
    use RefreshDatabase;

    private function exporter(): ClientBookingExcelExporter
    {
        return app(ClientBookingExcelExporter::class);
    }

    private function invoke(string $method, array $args)
    {
        $ref = new ReflectionMethod(ClientBookingExcelExporter::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($this->exporter(), $args);
    }

    // ──────────────────────────────────────────────────────
    // describePeriod
    // ──────────────────────────────────────────────────────

    public function test_describe_period_returns_default_when_no_filters(): void
    {
        $this->assertSame('Toutes périodes', $this->invoke('describePeriod', [[]]));
    }

    public function test_describe_period_with_from_only(): void
    {
        $label = $this->invoke('describePeriod', [['from' => '2026-01-15']]);

        $this->assertSame('À partir du 15/01/2026', $label);
    }

    public function test_describe_period_with_to_only(): void
    {
        $label = $this->invoke('describePeriod', [['to' => '2026-03-20']]);

        $this->assertSame("Jusqu'au 20/03/2026", $label);
    }

    public function test_describe_period_with_from_and_to(): void
    {
        $label = $this->invoke('describePeriod', [['from' => '2026-01-01', 'to' => '2026-12-31']]);

        $this->assertSame('Du 01/01/2026 au 31/12/2026', $label);
    }

    // ──────────────────────────────────────────────────────
    // statusColor
    // ──────────────────────────────────────────────────────

    public function test_status_color_maps_known_statuses(): void
    {
        $this->assertSame('F59E0B', $this->invoke('statusColor', ['en_attente']));
        $this->assertSame('F59E0B', $this->invoke('statusColor', ['pending']));
        $this->assertSame('3B82F6', $this->invoke('statusColor', ['confirme']));
        $this->assertSame('8B5CF6', $this->invoke('statusColor', ['en_route']));
        $this->assertSame('06B6D4', $this->invoke('statusColor', ['sur_place']));
        $this->assertSame('10B981', $this->invoke('statusColor', ['termine']));
        $this->assertSame('EF4444', $this->invoke('statusColor', ['annule']));
    }

    public function test_status_color_falls_back_to_grey_for_unknown_status(): void
    {
        $this->assertSame('64748B', $this->invoke('statusColor', ['some_unknown_status']));
    }

    // ──────────────────────────────────────────────────────
    // buildBookingQuery — scoping
    // ──────────────────────────────────────────────────────

    public function test_query_for_particulier_scopes_to_own_bookings_only(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();

        $mine = Booking::factory()->create(['client_id' => $user->id]);
        $alsoMine = Booking::factory()->create([
            'client_id' => null,
            'customer_user_id' => $user->id,
        ]);
        Booking::factory()->create(['client_id' => $other->id]);

        $query = $this->invoke('buildBookingQuery', [$user, []]);
        $this->assertInstanceOf(Builder::class, $query);

        $ids = $query->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertContains($alsoMine->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_query_for_organization_user_scopes_to_org_or_self(): void
    {
        $account = OrganizationAccount::factory()->create();
        $otherAccount = OrganizationAccount::factory()->create();
        $user = User::factory()->entreprise()->create(['organization_account_id' => $account->id]);

        $byOrg = Booking::factory()->create([
            'client_id' => null,
            'customer_organization_id' => $account->id,
        ]);
        $bySelf = Booking::factory()->create([
            'client_id' => null,
            'customer_user_id' => $user->id,
        ]);
        Booking::factory()->create([
            'client_id' => null,
            'customer_organization_id' => $otherAccount->id,
        ]);

        $ids = $this->invoke('buildBookingQuery', [$user, []])->pluck('id')->all();

        $this->assertContains($byOrg->id, $ids);
        $this->assertContains($bySelf->id, $ids);
        $this->assertCount(2, $ids);
    }

    // ──────────────────────────────────────────────────────
    // buildBookingQuery — filters
    // ──────────────────────────────────────────────────────

    public function test_query_applies_date_range_filters(): void
    {
        $user = User::factory()->client()->create();

        $inside = Booking::factory()->create([
            'client_id' => $user->id,
            'scheduled_date' => '2026-06-15',
        ]);
        $tooEarly = Booking::factory()->create([
            'client_id' => $user->id,
            'scheduled_date' => '2026-01-01',
        ]);
        $tooLate = Booking::factory()->create([
            'client_id' => $user->id,
            'scheduled_date' => '2026-12-31',
        ]);

        $ids = $this->invoke('buildBookingQuery', [$user, [
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]])->pluck('id')->all();

        $this->assertSame([$inside->id], $ids);
        $this->assertNotContains($tooEarly->id, $ids);
        $this->assertNotContains($tooLate->id, $ids);
    }

    public function test_query_applies_status_filter(): void
    {
        $user = User::factory()->client()->create();

        $done = Booking::factory()->create([
            'client_id' => $user->id,
            'status' => 'termine',
        ]);
        Booking::factory()->create([
            'client_id' => $user->id,
            'status' => 'en_attente',
        ]);

        $ids = $this->invoke('buildBookingQuery', [$user, [
            'statuses' => ['termine'],
        ]])->pluck('id')->all();

        $this->assertSame([$done->id], $ids);
    }

    public function test_query_applies_site_filter(): void
    {
        $user = User::factory()->client()->create();
        $siteA = OrganizationSite::factory()->create();
        $siteB = OrganizationSite::factory()->create();

        $targeted = Booking::factory()->create([
            'client_id' => $user->id,
            'organization_site_id' => $siteA->id,
        ]);
        Booking::factory()->create([
            'client_id' => $user->id,
            'organization_site_id' => $siteB->id,
        ]);

        $ids = $this->invoke('buildBookingQuery', [$user, [
            'site_ids' => $siteA->id,
        ]])->pluck('id')->all();

        $this->assertSame([$targeted->id], $ids);
    }
}
