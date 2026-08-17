<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\Push\Providers\ApnsPushProvider;
use App\Services\Push\Providers\ExpoPushProvider;
use App\Services\Push\Providers\FcmPushProvider;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service Push unifié (Phase Push v2 — prod-ready).
 *
 *   - Provider-agnostic (FCM / APNs / Mock)
 *   - Ledger PushNotification + idempotency
 *   - Opt-out par catégorie (cf. DeviceToken::preferences)
 *   - Token invalidation auto sur erreurs provider (UNREGISTERED, BadDeviceToken)
 *   - Rate limiting per token + per user
 *   - Audit via ActivityLogger
 */
class PushService
{
    public function __construct(protected PushProviderInterface $provider) {}

    /**
     * Sélectionne le provider adapté à la plateforme du device token.
     *
     * Multi-platform routing :
     *   - ios (native APNs) → APNs si configuré, sinon FCM (qui sait router APNs)
     *   - android (FCM) → FCM
     *   - web (Web Push API) → FCM si configuré, sinon Web Push (sender propre)
     *
     * Fallback : si aucun provider disponible pour la platform, utilise le bind par défaut.
     */
    protected function providerFor(DeviceToken $token): PushProviderInterface
    {
        try {
            /*
             * LE FOURNISSEUR DU JETON PASSE AVANT SA PLATEFORME.
             *
             * Un jeton Expo — `ExponentPushToken[…]` — n'est ni un jeton FCM ni un jeton APNs :
             * Expo garde la correspondance de son côté et route lui-même vers Google et Apple.
             * L'aiguillage par plateforme ci-dessous l'enverrait donc à FCM, qui le rejetterait.
             *
             * On lit `device_tokens.provider`, écrit à l'enregistrement par l'application
             * elle-même. C'est elle qui sait de quel service vient son jeton ; le deviner depuis la
             * plateforme rouvrirait l'écart que ce lot vient de fermer.
             */
            if (strtolower((string) ($token->provider ?? '')) === 'expo'
                && class_exists(ExpoPushProvider::class)) {
                return app(ExpoPushProvider::class);
            }

            $platform = strtolower((string) ($token->platform ?? ''));
            $apnsConfigured = (bool) config('push.apns.key_path') || (bool) config('push.apns.key_id');
            $fcmConfigured = (bool) config('push.fcm.credentials_path') || (bool) config('push.fcm.project_id');

            // Provider à instancier explicitement par plateforme
            if ($platform === 'ios' && $apnsConfigured && class_exists(ApnsPushProvider::class)) {
                return app(ApnsPushProvider::class);
            }
            if (in_array($platform, ['android', 'web'], true) && $fcmConfigured && class_exists(FcmPushProvider::class)) {
                return app(FcmPushProvider::class);
            }
            if ($platform === 'ios' && $fcmConfigured && class_exists(FcmPushProvider::class)) {
                // FCM peut router vers APNs via FCM iOS topic
                return app(FcmPushProvider::class);
            }
        } catch (\Throwable $e) {
            Log::warning('[push] platform routing fallback', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback sur le provider injecté (Mock ou single-binding)
        return $this->provider;
    }

    /**
     * Envoi push à un device token spécifique.
     */
    public function dispatch(
        DeviceToken $token,
        ?string $title,
        string $body,
        array $data = [],
        string $category = PushNotification::CATEGORY_TRANSACTIONAL,
        ?string $idempotencyKey = null,
        ?string $locale = null,
        ?Model $source = null,
    ): ?PushNotification {
        if (! Config::get('push.enabled', true)) {
            return null;
        }

        if (! $token->isActive()) {
            return $this->recordSkipped($token, $title, $body, $data, $category, $idempotencyKey, $locale, $source,
                PushNotification::STATUS_INVALID_TOKEN, 'Token invalidated');
        }

        if (! $token->isOptedInFor($category)) {
            return $this->recordSkipped($token, $title, $body, $data, $category, $idempotencyKey, $locale, $source,
                PushNotification::STATUS_OPTED_OUT, 'User opted-out for category');
        }

        if ($idempotencyKey) {
            $existing = PushNotification::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($this->isRateLimited($token)) {
            return $this->recordSkipped($token, $title, $body, $data, $category, $idempotencyKey, $locale, $source,
                PushNotification::STATUS_RATE_LIMITED, 'Rate limit reached');
        }

        return DB::transaction(function () use ($token, $title, $body, $data, $category, $idempotencyKey, $locale, $source) {
            $notification = PushNotification::create([
                'user_id' => $token->user_id,
                'device_token_id' => $token->id,
                'provider' => $this->providerFor($token)->name(),
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'locale' => $locale ?? $token->locale ?? app()->getLocale(),
                'category' => $category,
                'status' => PushNotification::STATUS_QUEUED,
                'attempts' => 0,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'queued_at' => now(),
            ]);

            try {
                $result = $this->providerFor($token)->send(new PushSendRequest(
                    token: $token->token,
                    platform: $token->platform,
                    title: $title,
                    body: $body,
                    data: $data,
                    idempotencyKey: $idempotencyKey,
                    category: $category,
                    locale: $notification->locale,
                ));

                if ($result->accepted) {
                    $notification->forceFill([
                        'external_id' => $result->externalId,
                        'status' => $result->status,
                        'attempts' => 1,
                        'sent_at' => now(),
                        'metadata' => array_merge((array) $notification->metadata, ['raw' => $result->raw]),
                    ])->save();

                    $token->forceFill(['last_used_at' => now()])->save();
                } else {
                    $notification->forceFill([
                        'status' => $result->tokenInvalid
                            ? PushNotification::STATUS_INVALID_TOKEN
                            : PushNotification::STATUS_FAILED,
                        'attempts' => 1,
                        'failed_at' => now(),
                        'failure_code' => $result->failureCode,
                        'failed_reason' => $result->failureReason,
                        'metadata' => array_merge((array) $notification->metadata, ['raw' => $result->raw]),
                    ])->save();

                    if ($result->tokenInvalid) {
                        $token->invalidate($result->failureCode ?? 'provider_invalid');
                    }
                }
            } catch (\Throwable $e) {
                Log::error('PushService::dispatch error', [
                    'token_id' => $token->id,
                    'error' => $e->getMessage(),
                ]);

                $notification->forceFill([
                    'status' => PushNotification::STATUS_FAILED,
                    'attempts' => 1,
                    'failed_at' => now(),
                    'failed_reason' => $e->getMessage(),
                ])->save();
            }

            ActivityLogger::log('push.dispatched', $notification, [
                'token_id' => $token->id,
                'platform' => $token->platform,
                'status' => $notification->status,
                'category' => $category,
            ]);

            return $notification->fresh();
        });
    }

    /**
     * Envoie à tous les devices actifs d'un user, opt-in pour la catégorie.
     *
     * @return array<int, PushNotification>
     */
    public function dispatchToUser(
        User $user,
        ?string $title,
        string $body,
        array $data = [],
        string $category = PushNotification::CATEGORY_TRANSACTIONAL,
        ?string $idempotencyKey = null,
        ?Model $source = null,
    ): array {
        $tokens = DeviceToken::query()
            ->active()
            ->forUser($user->id)
            ->get();

        $results = [];
        foreach ($tokens as $i => $token) {
            $perDeviceKey = $idempotencyKey ? "{$idempotencyKey}:dev{$i}" : null;
            $result = $this->dispatch(
                $token,
                $title,
                $body,
                $data,
                $category,
                $perDeviceKey,
                $user->preferredLocale() ?? null,
                $source,
            );
            if ($result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    public function provider(): PushProviderInterface
    {
        return $this->provider;
    }

    protected function isRateLimited(DeviceToken $token): bool
    {
        $perTokenPerMin = (int) Config::get('push.rate_limits.per_token_per_minute', 10);
        $countToken = PushNotification::query()
            ->recentForToken($token->id, now()->subMinute())
            ->count();
        if ($countToken >= $perTokenPerMin) {
            return true;
        }

        if ($token->user_id) {
            $perUserPerMin = (int) Config::get('push.rate_limits.per_user_per_minute', 30);
            $countUser = PushNotification::query()
                ->where('user_id', $token->user_id)
                ->where('queued_at', '>=', now()->subMinute())
                ->count();
            if ($countUser >= $perUserPerMin) {
                return true;
            }
        }

        return false;
    }

    protected function recordSkipped(
        DeviceToken $token,
        ?string $title,
        string $body,
        array $data,
        string $category,
        ?string $idempotencyKey,
        ?string $locale,
        ?Model $source,
        string $status,
        string $reason,
    ): PushNotification {
        return PushNotification::create([
            'user_id' => $token->user_id,
            'device_token_id' => $token->id,
            'provider' => $this->provider->name(),
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'locale' => $locale ?? $token->locale,
            'category' => $category,
            'status' => $status,
            'failed_reason' => $reason,
            'attempts' => 0,
            'source_type' => $source ? get_class($source) : null,
            'source_id' => $source?->getKey(),
            'idempotency_key' => $idempotencyKey,
            'queued_at' => now(),
            'failed_at' => now(),
        ]);
    }
}
