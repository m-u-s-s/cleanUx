<?php

namespace Tests\Feature\Client;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\RecurringBookingSeries;
use App\Models\RecurringTemplate;
use App\Models\User;
use App\Services\Client\Calendar\BookingRescheduleService;
use App\Services\Client\Templates\ApplyRecurringTemplateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase61Test extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(User $user, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_reference'        => 'CUX-' . strtoupper(Str::random(6)),
            'customer_user_id'         => $user->id,
            'client_id'                => $user->id,
            'customer_organization_id' => $user->organization_account_id,
            'scheduled_date'           => Carbon::now()->addDays(5)->toDateString(),
            'scheduled_time'           => '09:00:00',
            'status'                   => 'confirme',
            'currency'                 => 'EUR',
            'priority'                 => 'normal',
            'booking_mode'             => 'scheduled',
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────
    // BookingRescheduleService
    // ──────────────────────────────────────────────────────

    public function test_owner_can_reschedule_their_booking(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);

        $newDate = Carbon::now()->addDays(10);

        $updated = app(BookingRescheduleService::class)->reschedule(
            $user,
            $booking,
            $newDate,
            '14:00'
        );

        $this->assertSame($newDate->toDateString(), $updated->scheduled_date->toDateString());
        $this->assertStringContainsString('14:00', $updated->scheduled_time);
    }

    public function test_stranger_cannot_reschedule_others_booking(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $booking = $this->makeBooking($owner);

        $this->expectException(\DomainException::class);
        app(BookingRescheduleService::class)->reschedule(
            $stranger,
            $booking,
            Carbon::now()->addDays(10),
            '10:00'
        );
    }

    public function test_cannot_reschedule_completed_booking(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user, ['status' => 'termine']);

        $this->expectException(\DomainException::class);
        app(BookingRescheduleService::class)->reschedule(
            $user,
            $booking,
            Carbon::now()->addDays(10),
            '10:00'
        );
    }

    public function test_cannot_reschedule_cancelled_booking(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user, ['status' => 'annule']);

        $this->expectException(\DomainException::class);
        app(BookingRescheduleService::class)->reschedule(
            $user,
            $booking,
            Carbon::now()->addDays(10),
            '10:00'
        );
    }

    public function test_cannot_reschedule_to_past(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);

        $this->expectException(\DomainException::class);
        app(BookingRescheduleService::class)->reschedule(
            $user,
            $booking,
            Carbon::yesterday(),
            '10:00'
        );
    }

    public function test_cannot_reschedule_too_far_future(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);

        $this->expectException(\DomainException::class);
        app(BookingRescheduleService::class)->reschedule(
            $user,
            $booking,
            Carbon::now()->addMonths(8),
            '10:00'
        );
    }

    public function test_org_member_can_reschedule_org_booking(): void
    {
        $org = OrganizationAccount::factory()->create();
        $owner = User::factory()->create(['current_organization_id' => $org->id]);
        $colleague = User::factory()->create(['current_organization_id' => $org->id]);

        $booking = $this->makeBooking($owner, [
            'customer_organization_id' => $org->id,
        ]);

        $updated = app(BookingRescheduleService::class)->reschedule(
            $colleague,
            $booking,
            Carbon::now()->addDays(7),
            '11:30'
        );

        $this->assertSame(Carbon::now()->addDays(7)->toDateString(), $updated->scheduled_date->toDateString());
    }

    public function test_reschedule_creates_history_entry(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);

        app(BookingRescheduleService::class)->reschedule(
            $user,
            $booking,
            Carbon::now()->addDays(10),
            '14:00',
            'Test reason'
        );

        $this->assertDatabaseHas('booking_reschedule_history', [
            'booking_id' => $booking->id,
            'user_id'    => $user->id,
            'reason'     => 'Test reason',
        ]);
    }

    // ──────────────────────────────────────────────────────
    // RecurringTemplate
    // ──────────────────────────────────────────────────────

    public function test_template_human_description_weekly(): void
    {
        $tpl = RecurringTemplate::create([
            'slug' => 'test-1',
            'name' => 'Test',
            'frequency' => 'weekly',
            'interval' => 2,
            'days' => ['monday', 'thursday'],
            'default_time' => '09:00:00',
            'is_system' => true,
        ]);

        $desc = $tpl->human_description;
        $this->assertStringContainsString('2 semaines', $desc);
        $this->assertStringContainsString('lundi', $desc);
        $this->assertStringContainsString('jeudi', $desc);
        $this->assertStringContainsString('09:00', $desc);
    }

    public function test_for_user_scope_includes_system_templates(): void
    {
        $user = User::factory()->create();

        RecurringTemplate::create(['slug' => 'sys-1', 'name' => 'Sys', 'frequency' => 'weekly', 'is_system' => true, 'is_active' => true]);
        RecurringTemplate::create(['slug' => 'mine', 'name' => 'Mine', 'frequency' => 'weekly', 'owner_user_id' => $user->id, 'is_active' => true]);
        RecurringTemplate::create(['slug' => 'other', 'name' => 'Other', 'frequency' => 'weekly', 'owner_user_id' => User::factory()->create()->id, 'is_active' => true]);

        $templates = RecurringTemplate::query()
            ->forUser($user->id)
            ->active()
            ->get();

        $slugs = $templates->pluck('slug')->all();
        $this->assertContains('sys-1', $slugs);
        $this->assertContains('mine',  $slugs);
        $this->assertNotContains('other', $slugs);
    }

    public function test_increment_usage_updates_count(): void
    {
        $tpl = RecurringTemplate::create([
            'slug' => 'pop',
            'name' => 'Popular',
            'frequency' => 'weekly',
            'is_system' => true,
            'usage_count' => 5,
        ]);

        $tpl->incrementUsage();

        $this->assertSame(6, $tpl->fresh()->usage_count);
    }

    // ──────────────────────────────────────────────────────
    // ApplyRecurringTemplateService
    // ──────────────────────────────────────────────────────

    public function test_apply_template_creates_series(): void
    {
        $user = User::factory()->create();
        $tpl = RecurringTemplate::create([
            'slug' => 'apply-test',
            'name' => 'Apply Test',
            'frequency' => 'weekly',
            'interval' => 1,
            'days' => ['monday'],
            'default_time' => '08:00:00',
            'is_system' => true,
            'is_active' => true,
        ]);

        $series = app(ApplyRecurringTemplateService::class)->apply($user, $tpl, [
            'starts_at' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $this->assertInstanceOf(RecurringBookingSeries::class, $series);
        $this->assertSame('weekly', $series->frequency);
        $this->assertSame($user->id, $series->customer_user_id);
        $this->assertSame(['monday'], $series->days);
    }

    public function test_apply_template_increments_usage_count(): void
    {
        $user = User::factory()->create();
        $tpl = RecurringTemplate::create([
            'slug' => 'usage',
            'name' => 'Usage',
            'frequency' => 'weekly',
            'days' => ['friday'],
            'is_system' => true,
            'is_active' => true,
            'usage_count' => 0,
        ]);

        app(ApplyRecurringTemplateService::class)->apply($user, $tpl, [
            'starts_at' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $this->assertSame(1, $tpl->fresh()->usage_count);
    }

    public function test_apply_template_rejects_inactive(): void
    {
        $user = User::factory()->create();
        $tpl = RecurringTemplate::create([
            'slug' => 'inactive',
            'name' => 'Inactive',
            'frequency' => 'weekly',
            'is_active' => false,
        ]);

        $this->expectException(\DomainException::class);
        app(ApplyRecurringTemplateService::class)->apply($user, $tpl, [
            'starts_at' => Carbon::now()->addDays(5)->toDateString(),
        ]);
    }

    public function test_apply_template_rejects_invalid_date_range(): void
    {
        $user = User::factory()->create();
        $tpl = RecurringTemplate::create([
            'slug' => 'bad-dates',
            'name' => 'Bad',
            'frequency' => 'weekly',
            'days' => ['monday'],
            'is_active' => true,
            'is_system' => true,
        ]);

        $this->expectException(\DomainException::class);
        app(ApplyRecurringTemplateService::class)->apply($user, $tpl, [
            'starts_at' => Carbon::now()->addDays(10)->toDateString(),
            'ends_at'   => Carbon::now()->addDays(5)->toDateString(),
        ]);
    }
}
