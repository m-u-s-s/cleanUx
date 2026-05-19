<?php

namespace App\Services\Gdpr;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\Feedback;
use App\Models\GdprDataRequest;
use App\Models\KycVerification;
use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Models\Referral;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Collecte TOUTES les données d'un utilisateur et les sérialise dans un
 * fichier JSON (et optionnellement un ZIP avec les pièces jointes).
 *
 * Respecte l'art. 15 (accès) et 20 (portabilité) du RGPD :
 *   - Format machine-readable
 *   - Donné directement à l'utilisateur via URL signée
 */
class DataExportService
{
    public function execute(GdprDataRequest $request): GdprDataRequest
    {
        if ($request->type !== GdprDataRequest::TYPE_EXPORT) {
            throw new \InvalidArgumentException('Request type must be export.');
        }

        $request->update(['status' => GdprDataRequest::STATUS_PROCESSING]);

        try {
            $data = $this->collectFor($request->user);
            $path = $this->writeJsonFile($request, $data);
            $size = Storage::disk($this->disk())->size($path);

            $expiry = now()->addDays((int) config('gdpr.export_expiry_days', 30));

            $request->update([
                'status' => GdprDataRequest::STATUS_FULFILLED,
                'export_file_path' => $path,
                'export_file_size' => $size,
                'export_format' => 'json',
                'fulfilled_at' => now(),
                'expires_at' => $expiry,
            ]);

            ActivityLogger::log('gdpr.export_fulfilled', $request, [
                'user_id' => $request->user_id,
                'size' => $size,
            ]);
        } catch (\Throwable $e) {
            $request->update([
                'status' => GdprDataRequest::STATUS_REJECTED,
                'admin_response' => 'Export error: ' . $e->getMessage(),
            ]);
            throw $e;
        }

        return $request->fresh();
    }

    /**
     * Collecte structurée. Étendre selon les modules ajoutés.
     */
    public function collectFor(User $user): array
    {
        return [
            'export_metadata' => [
                'generated_at' => now()->toIso8601String(),
                'app_name' => config('app.name'),
                'user_id' => $user->id,
                'rgpd_articles' => ['15', '20'],
            ],
            'profile' => $this->collectProfile($user),
            'bookings' => $this->collectBookings($user),
            'feedback_given' => $this->collectFeedbackGiven($user),
            'feedback_received' => $this->collectFeedbackReceived($user),
            'disputes' => $this->collectDisputes($user),
            'referrals' => $this->collectReferrals($user),
            'kyc_verifications' => $this->collectKyc($user),
            'wallet_transactions' => $this->collectWallet($user),
            'payouts' => $this->collectPayouts($user),
            'notifications' => $this->collectNotifications($user),
            'login_history' => $this->collectLoginHistory($user),
        ];
    }

    protected function collectProfile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'role' => $user->role,
            'platform_role' => $user->platform_role,
            'account_type' => $user->account_type,
            'plan_type' => $user->plan_type,
            'created_at' => $user->created_at,
            'metadata' => $user->metadata,
            'customer_profile' => $user->customerProfile?->only([
                'customer_type', 'plan_type', 'plan_status',
            ]),
            'provider_profile' => $user->providerProfile?->only([
                'provider_type', 'status', 'verification_status', 'bio',
                'rating_avg', 'rating_count', 'hourly_rate',
                'kyc_completed_at', 'verified_at',
            ]),
        ];
    }

    protected function collectBookings(User $user): array
    {
        if (! Schema::hasTable('bookings')) return [];

        return Booking::query()
            ->where('client_id', $user->id)
            ->orWhere('employe_id', $user->id)
            ->limit(2000)
            ->get(['id', 'booking_reference', 'date', 'heure', 'status', 'devis_estime', 'currency', 'adresse', 'ville', 'code_postal', 'created_at'])
            ->toArray();
    }

    protected function collectFeedbackGiven(User $user): array
    {
        if (! Schema::hasTable('feedback')) return [];

        return Feedback::query()
            ->where('client_id', $user->id)
            ->limit(500)
            ->get(['id', 'rating', 'commentaire', 'comment', 'punctuality_score', 'quality_score', 'communication_score', 'value_score', 'direction', 'created_at'])
            ->toArray();
    }

    protected function collectFeedbackReceived(User $user): array
    {
        if (! Schema::hasTable('feedback')) return [];

        return Feedback::query()
            ->where('employe_id', $user->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->where('status', Feedback::STATUS_PUBLISHED)
            ->limit(500)
            ->get(['id', 'rating', 'comment', 'commentaire', 'provider_response', 'created_at'])
            ->toArray();
    }

    protected function collectDisputes(User $user): array
    {
        if (! Schema::hasTable('complaint_cases')) return [];

        return ComplaintCase::query()
            ->where('client_id', $user->id)
            ->orWhere('provider_user_id', $user->id)
            ->limit(500)
            ->get(['id', 'reference', 'category', 'priority', 'status', 'subject', 'description', 'created_at', 'resolved_at'])
            ->toArray();
    }

    protected function collectReferrals(User $user): array
    {
        if (! Schema::hasTable('referrals')) return [];

        return Referral::query()
            ->where('referrer_user_id', $user->id)
            ->orWhere('referee_user_id', $user->id)
            ->limit(500)
            ->get(['id', 'status', 'referee_email', 'invited_at', 'signed_up_at', 'qualified_at', 'rewarded_at'])
            ->toArray();
    }

    protected function collectKyc(User $user): array
    {
        if (! Schema::hasTable('kyc_verifications')) return [];

        return KycVerification::query()
            ->where('user_id', $user->id)
            ->limit(50)
            ->get(['id', 'provider', 'status', 'decision', 'score', 'country_code', 'started_at', 'completed_at', 'rejection_reason'])
            ->toArray();
    }

    protected function collectWallet(User $user): array
    {
        if (! Schema::hasTable('provider_wallet_transactions')) return [];

        return ProviderWalletTransaction::query()
            ->where('provider_user_id', $user->id)
            ->limit(2000)
            ->get(['id', 'type', 'direction', 'amount', 'currency', 'status', 'description', 'occurred_at'])
            ->toArray();
    }

    protected function collectPayouts(User $user): array
    {
        if (! Schema::hasTable('provider_payouts')) return [];

        return ProviderPayout::query()
            ->where('provider_user_id', $user->id)
            ->limit(500)
            ->get(['id', 'amount', 'currency', 'status', 'provider', 'period_start', 'period_end', 'created_at'])
            ->toArray();
    }

    protected function collectNotifications(User $user): array
    {
        if (! Schema::hasTable('notifications')) return [];

        return \DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->limit(500)
            ->get(['id', 'type', 'data', 'read_at', 'created_at'])
            ->toArray();
    }

    protected function collectLoginHistory(User $user): array
    {
        if (! Schema::hasTable('activity_logs')) return [];

        return \DB::table('activity_logs')
            ->where('user_id', $user->id)
            ->whereIn('action', ['user.login', 'user.logout', 'user.password_reset'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->toArray();
    }

    protected function writeJsonFile(GdprDataRequest $request, array $data): string
    {
        $path = sprintf(
            '%s/%s-%s-%s.json',
            trim((string) config('gdpr.export_path', 'gdpr-exports'), '/'),
            $request->reference,
            $request->user_id,
            Str::lower(Str::random(8)),
        );

        Storage::disk($this->disk())->put(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        return $path;
    }

    protected function disk(): string
    {
        return (string) config('gdpr.export_disk', 'local');
    }
}
