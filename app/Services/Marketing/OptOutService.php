<?php

namespace App\Services\Marketing;

use App\Models\MarketingOptOut;
use App\Models\User;
use App\Support\ActivityLogger;

/**
 * Gestion des opt-outs marketing (RGPD-compliant).
 *
 *   - optOut(user, channel) : crée un record. channel='all' opt-out tous canaux.
 *   - optIn(user, channel)  : supprime le record.
 *   - isOptedOut(user, channel) : true si user OU 'all' présent.
 */
class OptOutService
{
    public function optOut(User $user, string $channel, ?string $reason = null, ?string $ipAddress = null): MarketingOptOut
    {
        $row = MarketingOptOut::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->first();

        if ($row) {
            return $row;
        }

        $row = MarketingOptOut::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'opted_out_at' => now(),
            'reason' => $reason,
            'ip_hash' => $ipAddress ? hash('sha256', $ipAddress) : null,
        ]);

        ActivityLogger::log('marketing.opt_out', $row, [
            'user_id' => $user->id,
            'channel' => $channel,
        ]);

        return $row;
    }

    public function optIn(User $user, string $channel): bool
    {
        $deleted = MarketingOptOut::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->delete();

        if ($deleted) {
            ActivityLogger::log('marketing.opt_in', $user, [
                'user_id' => $user->id,
                'channel' => $channel,
            ]);
        }

        return $deleted > 0;
    }

    public function isOptedOut(User $user, string $channel): bool
    {
        return MarketingOptOut::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($channel) {
                $q->where('channel', $channel)->orWhere('channel', MarketingOptOut::CHANNEL_ALL);
            })
            ->exists();
    }

    /**
     * @return array<string,bool>  ['email' => false, 'sms' => true, 'push' => false, 'all' => false]
     */
    public function preferences(User $user): array
    {
        $rows = MarketingOptOut::query()->forUser($user->id)->pluck('channel')->all();
        return [
            'email' => in_array('email', $rows, true) || in_array('all', $rows, true),
            'sms'   => in_array('sms', $rows, true) || in_array('all', $rows, true),
            'push'  => in_array('push', $rows, true) || in_array('all', $rows, true),
            'all'   => in_array('all', $rows, true),
        ];
    }
}
