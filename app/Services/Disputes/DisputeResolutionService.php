<?php

namespace App\Services\Disputes;

use App\Events\Disputes\DisputeResolved;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\DisputeResolution;
use App\Models\PromoCode;
use App\Models\User;
use App\Notifications\Disputes\DisputeResolvedNotification;
use App\Services\Payments\StripeConnectPaymentService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DisputeResolutionService
{
    public function __construct(
        protected DisputeService $disputeService,
        protected StripeConnectPaymentService $stripeService,
    ) {}

    /**
     * @param  array{resolution_type:string, amount?:float, explanation?:string, replacement_booking_id?:int}  $data
     */
    public function apply(ComplaintCase $case, User $admin, array $data): DisputeResolution
    {
        $type = $data['resolution_type'];
        $amount = isset($data['amount']) ? (float) $data['amount'] : null;
        $explanation = $data['explanation'] ?? null;

        return DB::transaction(function () use ($case, $admin, $type, $amount, $explanation, $data) {
            $resolution = DisputeResolution::create([
                'complaint_case_id' => $case->id,
                'resolution_type' => $type,
                'amount' => $amount,
                'currency' => $case->booking?->currency ?? 'EUR',
                'explanation' => $explanation,
                'issued_by_user_id' => $admin->id,
                'status' => DisputeResolution::STATUS_PROPOSED,
                'replacement_booking_id' => $data['replacement_booking_id'] ?? null,
            ]);

            try {
                $this->execute($case, $resolution, $admin);

                $resolution->update([
                    'status' => DisputeResolution::STATUS_APPLIED,
                    'applied_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $resolution->update([
                    'status' => DisputeResolution::STATUS_FAILED,
                    'failed_at' => now(),
                    'failure_reason' => $e->getMessage(),
                ]);
                throw $e;
            }

            $case->update([
                'status' => ComplaintCase::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolution_category' => $type,
                'last_activity_at' => now(),
            ]);

            $this->disputeService->recordEvent($case, DisputeEvent::TYPE_RESOLVED, [
                'author_user_id' => $admin->id,
                'author_role' => DisputeEvent::ROLE_ADMIN,
                'body' => $explanation ?: 'Dispute résolue',
                'payload' => [
                    'resolution_type' => $type,
                    'amount' => $amount,
                    'resolution_id' => $resolution->id,
                ],
                'visibility' => DisputeEvent::VISIBILITY_ALL,
            ]);

            ActivityLogger::log('dispute.resolved', $case, [
                'admin_user_id' => $admin->id,
                'resolution_type' => $type,
                'amount' => $amount,
            ]);

            DisputeResolved::dispatch($case, $resolution);

            $this->notifyResolved($case);

            return $resolution->fresh();
        });
    }

    public function dismiss(ComplaintCase $case, User $admin, string $reason): DisputeResolution
    {
        return $this->apply($case, $admin, [
            'resolution_type' => DisputeResolution::TYPE_DISMISSED,
            'explanation' => $reason,
        ]);
    }

    protected function execute(ComplaintCase $case, DisputeResolution $resolution, User $admin): void
    {
        $booking = $case->booking ?? $case->rendezVous;

        switch ($resolution->resolution_type) {
            case DisputeResolution::TYPE_REFUND_FULL:
                $this->executeRefund($case, $resolution, $booking, null);
                break;

            case DisputeResolution::TYPE_REFUND_PARTIAL:
                if (! $resolution->amount || $resolution->amount <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Le montant est requis pour un refund partiel.',
                    ]);
                }
                $cents = (int) round(((float) $resolution->amount) * 100);
                $this->executeRefund($case, $resolution, $booking, $cents);
                break;

            case DisputeResolution::TYPE_CREDIT:
            case DisputeResolution::TYPE_PROMO_CODE:
                if (! $resolution->amount || $resolution->amount <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Le montant est requis pour un crédit/promo.',
                    ]);
                }
                $this->executePromoCredit($case, $resolution);
                break;

            case DisputeResolution::TYPE_REPLACEMENT_BOOKING:
            case DisputeResolution::TYPE_PROVIDER_WARNING:
            case DisputeResolution::TYPE_PROVIDER_SANCTION:
            case DisputeResolution::TYPE_NO_ACTION:
            case DisputeResolution::TYPE_DISMISSED:
            case DisputeResolution::TYPE_OTHER:
                // No financial action required, just record
                break;

            default:
                throw ValidationException::withMessages([
                    'resolution_type' => "Type de résolution inconnu : {$resolution->resolution_type}",
                ]);
        }
    }

    protected function executeRefund(ComplaintCase $case, DisputeResolution $resolution, $booking, ?int $cents): void
    {
        if (! $booking || empty($booking->stripe_payment_intent_id)) {
            // No Stripe to refund — just record the decision
            $resolution->update([
                'external_ref' => 'no_stripe_intent',
            ]);
            return;
        }

        if ($booking->payment_status !== 'captured') {
            throw ValidationException::withMessages([
                'resolution_type' => "Impossible de refund : booking en status {$booking->payment_status}",
            ]);
        }

        $refund = $this->stripeService->refundMissionPayment(
            $booking,
            $cents,
            'requested_by_customer',
        );

        if ($refund) {
            $resolution->update([
                'stripe_refund_id' => $refund->id ?? null,
                'external_ref' => $refund->id ?? null,
            ]);
        }
    }

    protected function executePromoCredit(ComplaintCase $case, DisputeResolution $resolution): void
    {
        $promoCode = PromoCode::create([
            'code' => 'SAV-' . strtoupper(Str::random(8)),
            'name' => 'Compensation SAV — ' . $case->reference,
            'description' => $resolution->explanation,
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => (float) $resolution->amount,
            'max_total_uses' => 1,
            'max_uses_per_user' => 1,
            'audience_scope' => PromoCode::SCOPE_SPECIFIC,
            'allowed_user_ids' => [(int) $case->client_id],
            'issued_to_user_id' => $case->client_id,
            'status' => PromoCode::STATUS_ACTIVE,
            'source' => PromoCode::SOURCE_SYSTEM,
            'valid_from' => now(),
            'valid_until' => now()->addDays(365),
            'metadata' => [
                'dispute_case_id' => $case->id,
                'dispute_reference' => $case->reference,
            ],
        ]);

        $resolution->update([
            'promo_code_id' => $promoCode->id,
            'external_ref' => $promoCode->code,
        ]);
    }

    protected function notifyResolved(ComplaintCase $case): void
    {
        try {
            if ($case->client) {
                $case->client->notify(new DisputeResolvedNotification($case->fresh()));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
