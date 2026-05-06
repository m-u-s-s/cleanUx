<?php

namespace App\Services\Messaging;

use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;

/**
 * Toggle de réaction emoji sur un message.
 *
 * Whitelist anti-abus : on ne valide pas exhaustivement l'emoji (le navigateur
 * renvoie un caractère unicode quelconque) mais on cap la longueur à 32 chars.
 */
class ReactionService
{
    public const ALLOWED_PRESET = ['👍', '❤️', '🔥', '😂', '🎉', '👀', '✅', '❌', '🙏', '💡'];

    public function toggle(Message $message, User $user, string $emoji): array
    {
        $emoji = trim($emoji);

        if ($emoji === '' || mb_strlen($emoji) > 32) {
            throw new \DomainException("Emoji invalide.");
        }

        $existing = MessageReaction::where([
            'message_id' => $message->id,
            'user_id'    => $user->id,
            'emoji'      => $emoji,
        ])->first();

        if ($existing) {
            $existing->delete();
            return ['action' => 'removed', 'emoji' => $emoji];
        }

        MessageReaction::create([
            'message_id' => $message->id,
            'user_id'    => $user->id,
            'emoji'      => $emoji,
        ]);

        return ['action' => 'added', 'emoji' => $emoji];
    }

    /**
     * Retourne le résumé pour l'UI :
     *   [
     *     ['emoji' => '👍', 'count' => 3, 'me' => true,  'users' => [...names]],
     *     ['emoji' => '🎉', 'count' => 1, 'me' => false, 'users' => [...]],
     *   ]
     */
    public function summarize(Message $message, ?User $forUser = null): array
    {
        $rows = $message->reactions()
            ->with('user:id,name')
            ->get();

        $grouped = $rows->groupBy('emoji');

        return $grouped->map(function ($group, $emoji) use ($forUser) {
            $userIds = $group->pluck('user_id')->all();
            return [
                'emoji' => $emoji,
                'count' => count($userIds),
                'me'    => $forUser && in_array($forUser->id, $userIds, true),
                'users' => $group->pluck('user.name')->filter()->take(8)->values()->all(),
            ];
        })->values()->all();
    }
}
