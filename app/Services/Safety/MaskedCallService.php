<?php

namespace App\Services\Safety;

use App\Models\Booking;
use App\Models\MaskedCallSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Twilio\Rest\Client;

/**
 * Service de proxy téléphonique anonyme (Twilio Proxy).
 *
 * Protège la PII client/provider : ne se voient JAMAIS le vrai numéro de l'autre.
 * Chacun appelle un numéro Twilio Proxy temporaire (expiration ~ fin mission + 24h).
 *
 * Workflow :
 *   1. Booking créé → openSession(client, provider, booking)
 *   2. Twilio Proxy crée une `Session` avec 2 Participants (client/provider phones)
 *   3. Twilio assigne un numéro proxy unique (numéro Twilio Phone)
 *   4. Notifications envoient `proxy_phone_number` aux deux parties (vs vrai numéro)
 *   5. Booking complet + 24h → closeSession() — Twilio libère le numéro
 *
 * Soft-fail : si TWILIO_PROXY_SERVICE_SID absent, persiste seulement la session
 * en DB avec proxy_phone_number = null (mode dev/test).
 */
class MaskedCallService
{
    /**
     * Crée (ou récupère) une session Twilio Proxy pour ce booking.
     */
    public function openSession(User $client, User $provider, ?Booking $booking = null): MaskedCallSession
    {
        if (! $client->phone || ! $provider->phone) {
            throw ValidationException::withMessages([
                'phone' => ['Le client et le prestataire doivent avoir un numéro de téléphone.'],
            ]);
        }

        // Idempotency : 1 session active par (booking, client, provider)
        if ($booking) {
            $existing = MaskedCallSession::query()
                ->where('booking_id', $booking->id)
                ->where('client_user_id', $client->id)
                ->where('provider_user_id', $provider->id)
                ->where('status', MaskedCallSession::STATUS_ACTIVE)
                ->first();
            if ($existing && $existing->isActive()) {
                return $existing;
            }
        }

        $session = MaskedCallSession::query()->create([
            'code' => MaskedCallSession::generateCode(),
            'booking_id' => $booking?->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'client_phone_masked' => $this->mask($client->phone),
            'provider_phone_masked' => $this->mask($provider->phone),
            'status' => MaskedCallSession::STATUS_ACTIVE,
            'expires_at' => Carbon::now()->addHours((int) config('masked_calls.session_ttl_hours', 24)),
        ]);

        // Appel Twilio Proxy si configuré
        $this->createTwilioProxySession($session, $client, $provider);

        return $session;
    }

    public function closeSession(MaskedCallSession $session, ?string $reason = null): MaskedCallSession
    {
        if ($session->status !== MaskedCallSession::STATUS_ACTIVE) {
            return $session;
        }

        $this->closeTwilioProxySession($session);

        $meta = $session->metadata ?? [];
        if ($reason) {
            $meta['close_reason'] = $reason;
        }
        $session->update([
            'status' => MaskedCallSession::STATUS_CLOSED,
            'closed_at' => now(),
            'metadata' => $meta,
        ]);

        return $session->fresh();
    }

    public function scanExpired(): int
    {
        return MaskedCallSession::query()
            ->where('status', MaskedCallSession::STATUS_ACTIVE)
            ->where('expires_at', '<', now())
            ->update([
                'status' => MaskedCallSession::STATUS_EXPIRED,
                'closed_at' => now(),
            ]);
    }

    protected function mask(string $phone): string
    {
        $clean = preg_replace('/\D/', '', $phone);
        if (strlen($clean) < 4) {
            return '****';
        }

        return str_repeat('*', strlen($clean) - 4).substr($clean, -4);
    }

    /**
     * Appelle l'API Twilio Proxy pour créer une session + assigner un numéro proxy.
     * Soft-fail : si SDK Twilio absent ou config manquante, retourne sans erreur.
     */
    protected function createTwilioProxySession(MaskedCallSession $session, User $client, User $provider): void
    {
        try {
            // Ces trois clés existent dans config/masked_calls.php et config/sms.php :
            // les replis env() qui les doublaient étaient morts, et rendaient null
            // dès que `config:cache` avait tourné.
            $serviceSid = (string) config('masked_calls.twilio_service_sid');
            $accountSid = (string) config('sms.providers.twilio.sid');
            $authToken = (string) config('sms.providers.twilio.token');

            if ($serviceSid === '' || $accountSid === '' || $authToken === '') {
                Log::info('[masked_call] Twilio Proxy not configured, session persisted DB-only', [
                    'session_id' => $session->id,
                ]);

                return;
            }

            if (! class_exists(Client::class)) {
                Log::warning('[masked_call] Twilio SDK absent');

                return;
            }

            $twilio = new Client($accountSid, $authToken);

            $twilioSession = $twilio->proxy->v1->services($serviceSid)->sessions->create([
                'uniqueName' => $session->code,
                'ttl' => (int) ($session->expires_at?->diffInSeconds(now()) ?? 86400),
                'mode' => 'voice-and-message',
                'status' => 'open',
            ]);

            // Add participants
            $twilio->proxy->v1->services($serviceSid)
                ->sessions($twilioSession->sid)
                ->participants->create($client->phone, [
                    'friendlyName' => "Client #{$client->id}",
                ]);

            $twilio->proxy->v1->services($serviceSid)
                ->sessions($twilioSession->sid)
                ->participants->create($provider->phone, [
                    'friendlyName' => "Provider #{$provider->id}",
                ]);

            // Le numéro proxy est attribué dynamiquement par Twilio Proxy ; on récupère via
            // les participants (chacun reçoit un proxy_identifier différent).
            $session->update([
                'twilio_session_sid' => $twilioSession->sid,
                'metadata' => array_merge($session->metadata ?? [], [
                    'twilio_created_at' => now()->toIso8601String(),
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[masked_call] Twilio Proxy create session failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function closeTwilioProxySession(MaskedCallSession $session): void
    {
        if (! $session->twilio_session_sid) {
            return;
        }
        try {
            $serviceSid = (string) config('masked_calls.twilio_service_sid');
            $accountSid = (string) config('sms.providers.twilio.sid');
            $authToken = (string) config('sms.providers.twilio.token');
            if ($serviceSid === '' || $accountSid === '' || ! class_exists(Client::class)) {
                return;
            }
            $twilio = new Client($accountSid, $authToken);
            $twilio->proxy->v1->services($serviceSid)
                ->sessions($session->twilio_session_sid)
                ->update(['status' => 'closed']);
        } catch (\Throwable $e) {
            Log::warning('[masked_call] Twilio close failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
