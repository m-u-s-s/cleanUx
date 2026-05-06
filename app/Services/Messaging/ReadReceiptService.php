<?php

namespace App\Services\Messaging;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4.1 — Marquage de lecture par utilisateur.
 *
 * Stratégie :
 *   - On NE crée PAS un MessageRead par message (volume insoutenable).
 *   - À la place, on stocke un MessageRead pour le DERNIER message lu d'un canal.
 *   - Le compteur "non lus" = SELECT COUNT(*) FROM messages WHERE channel_id = ?
 *     AND id > (SELECT message_id FROM message_reads WHERE user_id = ? AND ...).
 *
 * NB: la migration ajoute un UNIQUE(message_id, user_id), ce qui empêche les
 * doublons. Mais on ne crée qu'1 ligne par canal lu (par user). On supprime
 * les anciennes lignes du même canal pour ce user à chaque markRead.
 */
class ReadReceiptService
{
    /**
     * Marque le message le plus récent du canal comme lu pour cet utilisateur.
     */
    public function markChannelAsRead(User $user, Channel $channel): ?MessageRead
    {
        $latestMessage = Message::query()
            ->where('channel_id', $channel->id)
            ->latest('id')
            ->first();

        if (! $latestMessage) {
            return null;
        }

        return $this->markRead($user, $latestMessage);
    }

    public function markRead(User $user, Message $message): MessageRead
    {
        return DB::transaction(function () use ($user, $message) {
            // Supprime les anciennes "marker" lectures du même canal pour ce user
            // (on ne garde que la dernière)
            $oldMessageIds = Message::query()
                ->where('channel_id', $message->channel_id)
                ->where('id', '<', $message->id)
                ->pluck('id');

            if ($oldMessageIds->isNotEmpty()) {
                MessageRead::query()
                    ->where('user_id', $user->id)
                    ->whereIn('message_id', $oldMessageIds)
                    ->delete();
            }

            // Update or create pour le message courant
            return MessageRead::updateOrCreate(
                [
                    'message_id' => $message->id,
                    'user_id'    => $user->id,
                ],
                [
                    'read_at' => now(),
                ]
            );
        });
    }

    /**
     * Nombre de messages non lus dans un canal pour un user.
     */
    public function unreadCount(User $user, Channel $channel): int
    {
        $lastReadMessageId = MessageRead::query()
            ->where('user_id', $user->id)
            ->whereIn('message_id', function ($q) use ($channel) {
                $q->select('id')->from('messages')->where('channel_id', $channel->id);
            })
            ->max('message_id');

        $query = Message::query()
            ->where('channel_id', $channel->id)
            ->whereNull('deleted_at')
            ->where('user_id', '!=', $user->id); // pas ses propres messages

        if ($lastReadMessageId) {
            $query->where('id', '>', $lastReadMessageId);
        }

        return $query->count();
    }

    /**
     * Compteurs unread pour TOUS les canaux d'un user en une requête (pour sidebar).
     *
     * @return array<int, int> [channel_id => unread_count]
     */
    public function unreadCountsForUserChannels(User $user, array $channelIds): array
    {
        if (empty($channelIds)) {
            return [];
        }

        // Sous-requête : pour chaque (channel_id, user_id), trouve le max(message_id) lu
        $lastReads = DB::table('message_reads')
            ->join('messages', 'messages.id', '=', 'message_reads.message_id')
            ->where('message_reads.user_id', $user->id)
            ->whereIn('messages.channel_id', $channelIds)
            ->groupBy('messages.channel_id')
            ->select('messages.channel_id', DB::raw('MAX(messages.id) as last_read_message_id'))
            ->get()
            ->keyBy('channel_id');

        $counts = [];
        foreach ($channelIds as $cid) {
            $lastReadId = $lastReads[$cid]->last_read_message_id ?? 0;
            $counts[$cid] = Message::query()
                ->where('channel_id', $cid)
                ->whereNull('deleted_at')
                ->where('user_id', '!=', $user->id)
                ->where('id', '>', $lastReadId)
                ->count();
        }

        return $counts;
    }
}
