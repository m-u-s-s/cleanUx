<?php

namespace Tests\Feature\Payments;

use App\Models\StripeReconciliationRun;
use App\Services\Payments\StripeReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_creates_record_even_without_stripe_credentials(): void
    {
        config(['cashier.secret' => null]);
        putenv('STRIPE_SECRET');

        $service = app(StripeReconciliationService::class);
        $run = $service->run(StripeReconciliationRun::SCOPE_PAYMENT_INTENTS);

        $this->assertInstanceOf(StripeReconciliationRun::class, $run);
        $this->assertSame('completed', $run->status);

        // When Stripe API is not available, run flags a warning mismatch
        $mismatches = (array) $run->mismatches;
        $this->assertGreaterThanOrEqual(1, count($mismatches));
        $this->assertSame('stripe_unavailable', $mismatches[0]['type']);
    }

    public function test_run_records_period_correctly(): void
    {
        $service = app(StripeReconciliationService::class);
        $from = now()->subDays(3)->startOfDay();
        $to = now()->endOfDay();

        $run = $service->run(StripeReconciliationRun::SCOPE_ALL, $from, $to);

        $this->assertEquals($from->toDateString(), $run->period_start->toDateString());
        $this->assertEquals($to->toDateString(), $run->period_end->toDateString());
    }
}
