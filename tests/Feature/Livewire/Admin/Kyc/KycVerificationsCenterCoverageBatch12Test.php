<?php

namespace Tests\Feature\Livewire\Admin\Kyc;

use App\Livewire\Admin\Kyc\KycVerificationsCenter;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\Providers\KycMockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class KycVerificationsCenterCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(KycProviderInterface::class, KycMockProvider::class);
        Notification::fake();

        $this->admin = User::factory()->admin()->create();
    }

    private function makeVerification(array $overrides = []): KycVerification
    {
        $defaults = [
            'user_id' => User::factory()->client()->create()->id,
            'provider' => 'mock',
            'status' => KycVerification::STATUS_PENDING,
            'decision' => KycVerification::DECISION_PENDING,
            'country_code' => 'BE',
            'requested_checks' => ['document', 'facial_similarity'],
            'started_at' => now(),
        ];

        return KycVerification::create(array_merge($defaults, $overrides));
    }

    public function test_renders_ok_with_kpis_and_list(): void
    {
        $this->makeVerification(['provider' => 'mock', 'status' => KycVerification::STATUS_PENDING]);
        $this->makeVerification(['provider' => 'onfido', 'status' => KycVerification::STATUS_CONSIDER, 'decision' => KycVerification::DECISION_MANUAL_REVIEW]);
        $this->makeVerification(['provider' => 'mock', 'status' => KycVerification::STATUS_CLEAR, 'decision' => KycVerification::DECISION_APPROVED, 'completed_at' => now()]);
        $this->makeVerification(['provider' => 'veriff', 'status' => KycVerification::STATUS_REJECTED, 'decision' => KycVerification::DECISION_REJECTED, 'rejection_reason' => 'Document not valid', 'completed_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(KycVerificationsCenter::class)
            ->assertOk()
            ->assertViewHas('kpis', function (array $kpis) {
                return $kpis['total'] === 4
                    && $kpis['pending'] === 1
                    && $kpis['approved'] === 1
                    && $kpis['rejected'] === 1
                    && $kpis['requiring_review'] >= 1;
            })
            ->assertViewHas('list', fn ($list) => $list->total() === 4)
            ->assertViewHas('selected', null);
    }

    public function test_filters_narrow_the_list(): void
    {
        $this->makeVerification(['provider' => 'mock', 'status' => KycVerification::STATUS_PENDING, 'decision' => KycVerification::DECISION_PENDING]);
        $this->makeVerification(['provider' => 'onfido', 'status' => KycVerification::STATUS_CLEAR, 'decision' => KycVerification::DECISION_APPROVED, 'completed_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(KycVerificationsCenter::class)
            ->set('filterStatus', KycVerification::STATUS_PENDING)
            ->assertViewHas('list', fn ($list) => $list->total() === 1)
            ->set('filterStatus', '')
            ->set('filterDecision', KycVerification::DECISION_APPROVED)
            ->assertViewHas('list', fn ($list) => $list->total() === 1)
            ->set('filterDecision', '')
            ->set('filterProvider', 'onfido')
            ->assertViewHas('list', fn ($list) => $list->total() === 1);
    }

    public function test_search_matches_external_ids_and_user(): void
    {
        $user = User::factory()->client()->create(['name' => 'Zorglub Search', 'email' => 'zorglub-search@example.com']);
        $this->makeVerification([
            'user_id' => $user->id,
            'external_applicant_id' => 'app_uniquefoo',
            'external_check_id' => 'chk_uniquebar',
        ]);
        $this->makeVerification(['external_applicant_id' => 'app_other']);

        Livewire::actingAs($this->admin)
            ->test(KycVerificationsCenter::class)
            ->set('search', 'uniquefoo')
            ->assertViewHas('list', fn ($list) => $list->total() === 1)
            ->set('search', 'Zorglub')
            ->assertViewHas('list', fn ($list) => $list->total() === 1);
    }

    public function test_select_loads_detail_and_close_clears_it(): void
    {
        $verification = $this->makeVerification();

        Livewire::actingAs($this->admin)
            ->test(KycVerificationsCenter::class)
            ->set('manualNote', 'leftover')
            ->call('select', $verification->id)
            ->assertSet('selectedId', $verification->id)
            ->assertSet('manualNote', '')
            ->assertViewHas('selected', fn ($s) => $s !== null && $s->id === $verification->id)
            ->call('closeDetail')
            ->assertSet('selectedId', null)
            ->assertViewHas('selected', null);
    }

    public function test_sync_status_dispatches_success_toast(): void
    {
        $user = User::factory()->employe()->create(['email' => 'clean-kyc@example.com']);
        $verification = $this->makeVerification([
            'user_id' => $user->id,
            'provider' => 'mock',
            'status' => KycVerification::STATUS_IN_REVIEW,
        ]);

        Livewire::actingAs($this->admin)
            ->test(KycVerificationsCenter::class)
            ->call('select', $verification->id)
            ->call('syncStatus')
            ->assertDispatched('toast');

        $verification->refresh();
        $this->assertSame(KycVerification::STATUS_CLEAR, $verification->status);
        $this->assertSame(KycVerification::DECISION_APPROVED, $verification->decision);
    }

    public function test_approve_marks_verification_clear(): void
    {
        $user = User::factory()->employe()->create(['email' => 'approve-kyc@example.com']);
        $verification = $this->makeVerification([
            'user_id' => $user->id,
            'status' => KycVerification::STATUS_CONSIDER,
            'decision' => KycVerification::DECISION_MANUAL_REVIEW,
        ]);

        Livewire::actingAs($this->admin)
            ->test(KycVerificationsCenter::class)
            ->call('select', $verification->id)
            ->set('manualNote', 'Validated on a video call')
            ->call('approve')
            ->assertDispatched('toast')
            ->assertSet('manualNote', '');

        $verification->refresh();
        $this->assertSame(KycVerification::DECISION_APPROVED, $verification->decision);
        $this->assertSame(KycVerification::STATUS_CLEAR, $verification->status);
        $this->assertSame((int) $this->admin->id, (int) $verification->reviewed_by_user_id);
    }

    public function test_reject_requires_a_reason(): void
    {
        $verification = $this->makeVerification();

        Livewire::actingAs($this->admin)
            ->test(KycVerificationsCenter::class)
            ->call('select', $verification->id)
            ->set('manualReason', 'no')
            ->call('reject')
            ->assertHasErrors(['manualReason']);

        $this->assertSame(KycVerification::DECISION_PENDING, $verification->fresh()->decision);
    }

    public function test_reject_with_valid_reason_marks_rejected(): void
    {
        $user = User::factory()->employe()->create(['email' => 'rej-kyc@example.com']);
        $verification = $this->makeVerification([
            'user_id' => $user->id,
            'status' => KycVerification::STATUS_CONSIDER,
            'decision' => KycVerification::DECISION_MANUAL_REVIEW,
        ]);

        Livewire::actingAs($this->admin)
            ->test(KycVerificationsCenter::class)
            ->call('select', $verification->id)
            ->set('manualReason', 'Documents are not valid and expired')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('manualReason', '');

        $verification->refresh();
        $this->assertSame(KycVerification::DECISION_REJECTED, $verification->decision);
        $this->assertSame(KycVerification::STATUS_REJECTED, $verification->status);
        $this->assertSame('Documents are not valid and expired', $verification->rejection_reason);
    }
}
