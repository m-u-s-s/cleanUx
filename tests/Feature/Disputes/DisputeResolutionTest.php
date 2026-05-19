<?php

namespace Tests\Feature\Disputes;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\DisputeResolution;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Disputes\DisputeResolutionService;
use App\Services\Disputes\DisputeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected User $admin;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
        $this->admin = User::factory()->admin()->create();
        $this->booking = Booking::create([
            'client_id' => $this->client->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => 80,
        ]);
    }

    public function test_credit_resolution_creates_nominative_promo_code(): void
    {
        $case = $this->openCase('quality', 'high');

        app(DisputeResolutionService::class)->apply($case, $this->admin, [
            'resolution_type' => DisputeResolution::TYPE_CREDIT,
            'amount' => 30.0,
            'explanation' => 'Compensation pour qualité insuffisante',
        ]);

        $case->refresh();
        $this->assertSame(ComplaintCase::STATUS_RESOLVED, $case->status);
        $this->assertNotNull($case->resolved_at);

        $resolution = $case->resolutions()->latest()->first();
        $this->assertSame(DisputeResolution::STATUS_APPLIED, $resolution->status);
        $this->assertNotNull($resolution->promo_code_id);

        $promo = PromoCode::find($resolution->promo_code_id);
        $this->assertNotNull($promo);
        $this->assertSame((int) $this->client->id, (int) $promo->issued_to_user_id);
        $this->assertSame(PromoCode::TYPE_FIXED, $promo->discount_type);
        $this->assertEqualsWithDelta(30.0, (float) $promo->discount_value, 0.01);
        $this->assertStringStartsWith('SAV-', $promo->code);
    }

    public function test_refund_partial_requires_amount(): void
    {
        $case = $this->openCase('payment', 'high');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(DisputeResolutionService::class)->apply($case, $this->admin, [
            'resolution_type' => DisputeResolution::TYPE_REFUND_PARTIAL,
            'amount' => 0,
            'explanation' => 'Test',
        ]);
    }

    public function test_dismiss_marks_resolved_without_action(): void
    {
        $case = $this->openCase('quality', 'normal');

        $resolution = app(DisputeResolutionService::class)->dismiss(
            $case,
            $this->admin,
            'Demande non fondée après enquête.',
        );

        $this->assertSame(DisputeResolution::TYPE_DISMISSED, $resolution->resolution_type);
        $this->assertSame(DisputeResolution::STATUS_APPLIED, $resolution->status);

        $case->refresh();
        $this->assertSame(ComplaintCase::STATUS_RESOLVED, $case->status);
    }

    public function test_no_action_resolution_records_decision(): void
    {
        $case = $this->openCase('communication', 'normal');

        app(DisputeResolutionService::class)->apply($case, $this->admin, [
            'resolution_type' => DisputeResolution::TYPE_NO_ACTION,
            'explanation' => 'Litige clos sans action financière',
        ]);

        $case->refresh();
        $this->assertSame(ComplaintCase::STATUS_RESOLVED, $case->status);
        $this->assertSame(1, $case->resolutions()->count());
    }

    protected function openCase(string $category, string $priority): ComplaintCase
    {
        return app(DisputeService::class)->open($this->client, [
            'subject' => 'Test ' . $category,
            'description' => 'Description suffisante pour passer validation',
            'category' => $category,
            'priority' => $priority,
            'severity' => 'medium',
            'booking_id' => $this->booking->id,
        ]);
    }
}
