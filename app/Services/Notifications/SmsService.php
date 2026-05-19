<?php

namespace App\Services\Notifications;

use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsSendRequest;
use App\Services\Sms\SmsStatusUpdate;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service SMS unifié (Phase Sms v2 — prod-ready).
 *
 * Améliorations vs. v1 :
 *   - Provider-agnostic via SmsProviderInterface
 *   - Storage en DB de chaque envoi (sms_messages) pour audit + idempotence
 *   - Rate limiting per phone (anti-toll fraud)
 *   - E.164 validation
 *   - DLR webhook handling séparé via applyStatusUpdate()
 *   - Conserve la signature legacy `send(?string, string)` pour rétrocompat
 */
class SmsService
{
    public function __construct(protected SmsProviderInterface $provider)
    {
    }

    /**
     * Envoi simple (legacy signature, conservée pour le code existant qui l'utilise).
     */
    public function send(?string $to, string $message): ?SmsMessage
    {
        if (! $to) {
            return null;
        }

        if (! Config::get('sms.enabled', true)) {
            return null;
        }

        return $this->dispatch($to, $message);
    }

    /**
     * Envoi enrichi avec contexte métier.
     */
    public function dispatch(
        string $toPhone,
        string $body,
        ?User $user = null,
        ?Model $source = null,
        string $category = SmsMessage::CATEGORY_TRANSACTIONAL,
        ?string $idempotencyKey = null,
        ?string $locale = null,
    ): ?SmsMessage {
        $toPhone = $this->normalizePhone($toPhone);
        if (! $this->isValidE164($toPhone)) {
            Log::warning('SmsService::dispatch invalid phone format', ['phone' => $toPhone]);
            return null;
        }

        if ($idempotencyKey) {
            $existing = SmsMessage::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($this->isRateLimited($toPhone, $user)) {
            return $this->recordRateLimited($toPhone, $body, $user, $source, $category, $idempotencyKey, $locale);
        }

        return DB::transaction(function () use ($toPhone, $body, $user, $source, $category, $idempotencyKey, $locale) {
            $message = SmsMessage::create([
                'provider' => $this->provider->name(),
                'to_phone' => $toPhone,
                'body' => $body,
                'locale' => $locale ?? ($user?->preferredLocale() ?? app()->getLocale()),
                'status' => SmsMessage::STATUS_QUEUED,
                'attempts' => 0,
                'category' => $category,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'user_id' => $user?->id,
                'idempotency_key' => $idempotencyKey,
                'queued_at' => now(),
            ]);

            try {
                $result = $this->provider->send(new SmsSendRequest(
                    toPhone: $toPhone,
                    body: $body,
                    idempotencyKey: $idempotencyKey,
                ));

                if ($result->accepted) {
                    $message->forceFill([
                        'external_id' => $result->externalId,
                        'status' => $result->status,
                        'attempts' => 1,
                        'sent_at' => now(),
                        'cost_eur' => $result->cost,
                        'metadata' => array_merge((array) $message->metadata, ['raw' => $result->raw]),
                    ])->save();
                } else {
                    $message->forceFill([
                        'status' => SmsMessage::STATUS_FAILED,
                        'attempts' => 1,
                        'failed_at' => now(),
                        'failed_reason' => $result->failureReason,
                        'failure_code' => $result->failureCode,
                        'metadata' => array_merge((array) $message->metadata, ['raw' => $result->raw]),
                    ])->save();
                }
            } catch (\Throwable $e) {
                Log::error('SmsService::dispatch error', [
                    'phone' => $toPhone,
                    'error' => $e->getMessage(),
                ]);
                $message->forceFill([
                    'status' => SmsMessage::STATUS_FAILED,
                    'attempts' => 1,
                    'failed_at' => now(),
                    'failed_reason' => $e->getMessage(),
                ])->save();
            }

            ActivityLogger::log('sms.dispatched', $message, [
                'phone' => $toPhone,
                'status' => $message->status,
                'category' => $category,
            ]);

            return $message->fresh();
        });
    }

    public function applyStatusUpdate(SmsStatusUpdate $update): ?SmsMessage
    {
        $message = SmsMessage::query()
            ->where('provider', $this->provider->name())
            ->where('external_id', $update->externalId)
            ->first();

        if (! $message) {
            return null;
        }

        $message->forceFill([
            'status' => $update->status,
            'failed_reason' => $update->failureReason,
            'failure_code' => $update->failureCode,
            'delivered_at' => $update->status === SmsMessage::STATUS_DELIVERED ? now() : $message->delivered_at,
            'failed_at' => in_array($update->status, [
                SmsMessage::STATUS_FAILED,
                SmsMessage::STATUS_UNDELIVERED,
            ], true) ? now() : $message->failed_at,
            'metadata' => array_merge((array) $message->metadata, ['dlr' => $update->raw]),
        ])->save();

        return $message->fresh();
    }

    public function provider(): SmsProviderInterface
    {
        return $this->provider;
    }

    public function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        $phone = preg_replace('/[\s\.\-\(\)]/', '', $phone);

        return (string) $phone;
    }

    public function isValidE164(string $phone): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{1,14}$/', $phone);
    }

    protected function isRateLimited(string $phone, ?User $user): bool
    {
        $perHour = (int) Config::get('sms.rate_limits.per_phone_per_hour', 5);
        $perDay = (int) Config::get('sms.rate_limits.per_phone_per_day', 20);

        $sentHourly = SmsMessage::query()
            ->recentForPhone($phone, now()->subHour())
            ->count();

        if ($sentHourly >= $perHour) {
            return true;
        }

        $sentDaily = SmsMessage::query()
            ->recentForPhone($phone, now()->subDay())
            ->count();

        if ($sentDaily >= $perDay) {
            return true;
        }

        if ($user) {
            $perUserHour = (int) Config::get('sms.rate_limits.per_user_per_hour', 10);
            $sentByUserHour = SmsMessage::query()
                ->where('user_id', $user->id)
                ->where('queued_at', '>=', now()->subHour())
                ->count();
            if ($sentByUserHour >= $perUserHour) {
                return true;
            }
        }

        return false;
    }

    protected function recordRateLimited(
        string $toPhone,
        string $body,
        ?User $user,
        ?Model $source,
        string $category,
        ?string $idempotencyKey,
        ?string $locale,
    ): SmsMessage {
        return SmsMessage::create([
            'provider' => $this->provider->name(),
            'to_phone' => $toPhone,
            'body' => Str::limit($body, 200, ''),
            'locale' => $locale ?? ($user?->preferredLocale() ?? app()->getLocale()),
            'status' => SmsMessage::STATUS_RATE_LIMITED,
            'category' => $category,
            'source_type' => $source ? get_class($source) : null,
            'source_id' => $source?->getKey(),
            'user_id' => $user?->id,
            'idempotency_key' => $idempotencyKey,
            'queued_at' => now(),
            'failed_at' => now(),
            'failed_reason' => 'Rate limit reached for phone',
        ]);
    }
}
