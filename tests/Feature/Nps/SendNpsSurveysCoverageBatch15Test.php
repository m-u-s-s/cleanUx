<?php

namespace Tests\Feature\Nps;

use App\Models\Booking;
use App\Models\NpsResponse;
use App\Models\User;
use App\Notifications\Nps\NpsSurveyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendNpsSurveysCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    private function makeEligibleBooking(int $daysAfter = 3): Booking
    {
        $client = User::factory()->client()->create();

        $booking = Booking::factory()->create([
            'status' => 'completed',
            'customer_user_id' => $client->id,
        ]);

        $booking->completed_at = now()->subDays($daysAfter);
        $booking->save();

        return $booking;
    }

    public function test_reports_when_no_completed_bookings_match_date(): void
    {
        Notification::fake();

        $this->artisan('nps:send-surveys', ['--days' => 3])
            ->expectsOutputToContain('No completed bookings found')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_sends_survey_to_eligible_client(): void
    {
        Notification::fake();

        $booking = $this->makeEligibleBooking();

        $this->artisan('nps:send-surveys', ['--days' => 3])
            ->assertExitCode(0);

        Notification::assertSentTo(
            $booking->customer,
            NpsSurveyNotification::class
        );
        Notification::assertSentTimes(NpsSurveyNotification::class, 1);
    }

    public function test_dry_run_lists_without_sending(): void
    {
        Notification::fake();

        $this->makeEligibleBooking();

        $this->artisan('nps:send-surveys', ['--days' => 3, '--dry-run' => true])
            ->expectsOutputToContain('dry-run')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_skips_booking_already_surveyed(): void
    {
        Notification::fake();

        $booking = $this->makeEligibleBooking();

        NpsResponse::query()->create([
            'user_id' => $booking->customer_user_id,
            'booking_id' => $booking->id,
            'survey_code' => 'post_booking',
            'score' => 9,
            'category' => NpsResponse::CATEGORY_PROMOTER,
            'locale' => 'fr',
            'responded_at' => now(),
            'metadata' => [],
        ]);

        $this->artisan('nps:send-surveys', ['--days' => 3])
            ->expectsOutputToContain('Skipped (already surveyed): 1')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_limit_option_caps_processed_bookings(): void
    {
        Notification::fake();

        $this->makeEligibleBooking();
        $this->makeEligibleBooking();

        $this->artisan('nps:send-surveys', ['--days' => 3, '--limit' => 1])
            ->assertExitCode(0);

        Notification::assertSentTimes(NpsSurveyNotification::class, 1);
    }
}
