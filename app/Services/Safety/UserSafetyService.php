<?php

namespace App\Services\Safety;

use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service Block/Report user — Trust & Safety basique.
 *
 * Block : user A bloque user B → B ne peut plus booker A, ni chat avec A.
 * Report : user A signale user B avec catégorie → admin review.
 */
class UserSafetyService
{
    public function block(User $blocker, User $blocked, ?string $reason = null): UserBlock
    {
        if ($blocker->id === $blocked->id) {
            throw ValidationException::withMessages(['blocked' => ['Vous ne pouvez pas vous bloquer vous-même.']]);
        }

        return UserBlock::query()->firstOrCreate(
            ['blocker_user_id' => $blocker->id, 'blocked_user_id' => $blocked->id],
            ['reason' => $reason],
        );
    }

    public function unblock(User $blocker, User $blocked): void
    {
        UserBlock::query()
            ->where('blocker_user_id', $blocker->id)
            ->where('blocked_user_id', $blocked->id)
            ->delete();
    }

    public function isBlocked(User $blocker, User $other): bool
    {
        return UserBlock::query()
            ->where('blocker_user_id', $blocker->id)
            ->where('blocked_user_id', $other->id)
            ->exists();
    }

    public function isMutuallyBlocked(User $a, User $b): bool
    {
        return UserBlock::query()
            ->where(function ($q) use ($a, $b) {
                $q->where(function ($w) use ($a, $b) {
                    $w->where('blocker_user_id', $a->id)->where('blocked_user_id', $b->id);
                })->orWhere(function ($w) use ($a, $b) {
                    $w->where('blocker_user_id', $b->id)->where('blocked_user_id', $a->id);
                });
            })
            ->exists();
    }

    public function report(
        User $reporter,
        User $reported,
        string $category,
        string $description,
        array $evidence = []
    ): UserReport {
        if ($reporter->id === $reported->id) {
            throw ValidationException::withMessages(['reported' => ['Vous ne pouvez pas vous signaler vous-même.']]);
        }
        if (! array_key_exists($category, UserReport::CATEGORIES)) {
            throw ValidationException::withMessages(['category' => ['Catégorie de signalement invalide.']]);
        }
        if (mb_strlen(trim($description)) < 10) {
            throw ValidationException::withMessages(['description' => ['Description trop courte (min 10 caractères).']]);
        }

        return DB::transaction(function () use ($reporter, $reported, $category, $description, $evidence) {
            // Idempotency : un user ne peut pas spammer 20 reports identiques sur 24h
            $recent = UserReport::query()
                ->where('reporter_user_id', $reporter->id)
                ->where('reported_user_id', $reported->id)
                ->where('category', $category)
                ->where('created_at', '>=', now()->subHours(24))
                ->first();
            if ($recent) {
                return $recent;
            }

            return UserReport::query()->create([
                'code' => UserReport::generateCode(),
                'reporter_user_id' => $reporter->id,
                'reported_user_id' => $reported->id,
                'category' => $category,
                'description' => $description,
                'evidence' => $evidence,
                'status' => UserReport::STATUS_PENDING,
            ]);
        });
    }

    public function resolveReport(UserReport $report, User $admin, string $resolution, ?string $notes = null): UserReport
    {
        $allowed = [
            UserReport::STATUS_RESOLVED_ACTION,
            UserReport::STATUS_RESOLVED_NO_ACTION,
            UserReport::STATUS_DISMISSED,
        ];
        if (! in_array($resolution, $allowed, true)) {
            throw ValidationException::withMessages(['resolution' => ['Résolution invalide.']]);
        }

        $report->update([
            'status' => $resolution,
            'reviewed_by_admin_id' => $admin->id,
            'admin_notes' => $notes,
            'reviewed_at' => now(),
        ]);
        return $report->fresh();
    }
}
