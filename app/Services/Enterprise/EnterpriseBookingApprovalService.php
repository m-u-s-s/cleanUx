<?php

namespace App\Services\Enterprise;

use App\Models\EnterpriseBookingApproval;
use App\Models\RendezVous;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use Illuminate\Support\Facades\DB;

class EnterpriseBookingApprovalService
{
    public function createForBooking(RendezVous $rendezVous, ?User $requestedBy = null, ?string $note = null): EnterpriseBookingApproval
    {
        return DB::transaction(function () use ($rendezVous, $requestedBy, $note) {
            $approval = EnterpriseBookingApproval::query()->updateOrCreate(
                ['rendez_vous_id' => $rendezVous->id],
                [
                    'organization_account_id' => $rendezVous->organization_account_id,
                    'organization_site_id' => $rendezVous->organization_site_id,
                    'requested_by_user_id' => $requestedBy?->id,
                    'status' => 'pending_manager',
                    'request_note' => $note,
                ]
            );

            $rendezVous->update([
                'status' => BookingStatus::EN_ATTENTE,
            ]);

            ActivityLogger::log('enterprise_approval_created', $rendezVous, [
                'approval_id' => $approval->id,
                'status' => $approval->status,
            ]);

            return $approval;
        });
    }

    public function approveManager(EnterpriseBookingApproval $approval, User $manager, ?string $note = null): EnterpriseBookingApproval
    {
        return DB::transaction(function () use ($approval, $manager, $note) {
            if ($approval->status !== 'pending_manager') {
                return $approval;
            }

            $approval->update([
                'status' => 'pending_finance',
                'manager_approved_by_user_id' => $manager->id,
                'manager_note' => $note,
                'manager_approved_at' => now(),
            ]);

            ActivityLogger::log('enterprise_approval_manager_approved', $approval->rendezVous, [
                'approval_id' => $approval->id,
                'manager_id' => $manager->id,
            ]);

            return $approval->fresh();
        });
    }

    public function approveFinance(EnterpriseBookingApproval $approval, User $financeUser, ?string $note = null): EnterpriseBookingApproval
    {
        return DB::transaction(function () use ($approval, $financeUser, $note) {
            if ($approval->status !== 'pending_finance') {
                return $approval;
            }

            $approval->update([
                'status' => 'approved',
                'finance_approved_by_user_id' => $financeUser->id,
                'finance_note' => $note,
                'finance_approved_at' => now(),
                'approved_at' => now(),
            ]);

            $approval->rendezVous?->update([
                'status' => BookingStatus::CONFIRME,
            ]);

            ActivityLogger::log('enterprise_approval_finance_approved', $approval->rendezVous, [
                'approval_id' => $approval->id,
                'finance_user_id' => $financeUser->id,
            ]);

            return $approval->fresh();
        });
    }

    public function reject(EnterpriseBookingApproval $approval, User $user, string $reason): EnterpriseBookingApproval
    {
        return DB::transaction(function () use ($approval, $user, $reason) {
            if (in_array($approval->status, ['approved', 'rejected', 'cancelled'], true)) {
                return $approval;
            }

            $approval->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_at' => now(),
            ]);

            $approval->rendezVous?->update([
                'status' => BookingStatus::REFUSE,
            ]);

            ActivityLogger::log('enterprise_approval_rejected', $approval->rendezVous, [
                'approval_id' => $approval->id,
                'rejected_by_user_id' => $user->id,
                'reason' => $reason,
            ]);

            return $approval->fresh();
        });
    }
}