<?php

namespace Database\Factories;

use App\Models\OrganizationAccount;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'user_id' => User::factory(),
            'started_at' => now()->subHours(3),
            'ended_at' => now(),
            'worked_minutes' => 180,
            'paused_minutes' => 0,
            'source' => TimeEntry::SOURCE_AUTO,
            'status' => TimeEntry::STATUS_RECORDED,
        ];
    }
}
