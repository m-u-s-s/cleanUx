<?php

namespace App\Support\Chat;

use App\Models\Booking;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Services\ChatV2\ChatService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-création / archivage du thread Chat associé à un booking.
 *
 * Soft-fail si module chat_v2 absent OU désactivé OU table manquante.
 * Pas de side-effect business si quelque chose plante.
 */
class BookingChatAutoCreator
{
    public static function ensureThreadForBooking(Booking $booking): ?ChatThread
    {
        if (! self::moduleReady()) {
            return null;
        }
        try {
            $clientId = (int) ($booking->client_id ?? $booking->customer_user_id ?? 0);
            $providerId = (int) ($booking->employe_id ?? $booking->assigned_provider_user_id ?? 0);
            if ($clientId <= 0) {
                return null;
            }
            $participants = [['user_id' => $clientId, 'role' => ChatParticipant::ROLE_CLIENT]];
            if ($providerId > 0 && $providerId !== $clientId) {
                $participants[] = ['user_id' => $providerId, 'role' => ChatParticipant::ROLE_PROVIDER];
            }
            return app(ChatService::class)->startThread(
                contextType: 'booking',
                contextId: (int) $booking->id,
                participants: $participants,
                title: 'Booking #' . $booking->id,
                metadata: ['auto_created' => true, 'source' => 'BookingObserver'],
            );
        } catch (\Throwable $e) {
            Log::warning('[chat_auto] ensureThreadForBooking failed', [
                'booking_id' => $booking->id, 'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public static function archiveThreadIfBookingCompleted(Booking $booking): void
    {
        if (! self::moduleReady()) {
            return;
        }
        if (! (bool) config('chat_v2.auto_close_on_booking_completed', true)) {
            return;
        }
        try {
            $thread = ChatThread::query()
                ->forContext('booking', (int) $booking->id)
                ->first();
            if ($thread && $thread->isOpen()) {
                app(ChatService::class)->archiveThread($thread);
            }
        } catch (\Throwable $e) {
            Log::warning('[chat_auto] archive failed', [
                'booking_id' => $booking->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function moduleReady(): bool
    {
        if (! (bool) config('chat_v2.enabled', true)) {
            return false;
        }
        return Schema::hasTable('chat_threads') && Schema::hasTable('chat_participants');
    }
}
