<?php

namespace App\Services\Assistant\Actions;

use App\Models\User;

/**
 * Registry of data-fetching actions the chatbot assistant can execute.
 *
 * Each action descriptor is passed to the LLM as part of the system prompt
 * so the model can decide which actions to invoke based on user intent.
 *
 * Actions are read-only — they never mutate state.
 */
class AssistantActionRegistry
{
    /**
     * Returns the list of actions available for the given user.
     *
     * @return array<int, array{name: string, description: string, requires_confirmation: bool, parameters?: array<string, string>}>
     */
    public function availableActions(User $user): array
    {
        $actions = [];

        if ($user->isClient() || $user->isEntreprise()) {
            $actions[] = [
                'name'                  => 'list_bookings',
                'description'           => 'List the user\'s recent bookings with status',
                'requires_confirmation' => false,
            ];
            $actions[] = [
                'name'                  => 'booking_detail',
                'description'           => 'Get details of a specific booking by ID or reference',
                'parameters'            => ['booking_id' => 'integer'],
                'requires_confirmation' => false,
            ];
            $actions[] = [
                'name'                  => 'loyalty_balance',
                'description'           => 'Get the user\'s loyalty points balance and tier',
                'requires_confirmation' => false,
            ];
        }

        if ($user->isEmploye()) {
            $actions[] = [
                'name'                  => 'next_mission',
                'description'           => 'Get the provider\'s next upcoming mission',
                'requires_confirmation' => false,
            ];
            $actions[] = [
                'name'                  => 'earnings_summary',
                'description'           => 'Get the provider\'s earnings summary (this week, this month)',
                'requires_confirmation' => false,
            ];
            $actions[] = [
                'name'                  => 'availability_slots',
                'description'           => 'List the provider\'s availability slots',
                'requires_confirmation' => false,
            ];
        }

        if ($user->isAdmin()) {
            $actions[] = [
                'name'                  => 'platform_kpis',
                'description'           => 'Get today\'s platform KPIs (bookings, missions, revenue, disputes)',
                'requires_confirmation' => false,
            ];
            $actions[] = [
                'name'                  => 'trade_stats',
                'description'           => 'Get booking statistics grouped by trade/métier',
                'requires_confirmation' => false,
            ];
            $actions[] = [
                'name'                  => 'resolve_dispute',
                'description'           => 'Resolve a dispute case with a resolution text',
                'parameters'            => ['dispute_id' => 'integer', 'resolution' => 'string'],
                'requires_confirmation' => true,
            ];
        }

        // Write actions — clients
        if ($user->isClient() || $user->isEntreprise()) {
            $actions[] = [
                'name'                  => 'create_booking',
                'description'           => 'Create a new booking for the client',
                'parameters'            => [
                    'trade_slug'         => 'string',
                    'service_catalog_id' => 'integer',
                    'address'            => 'string',
                    'city'               => 'string',
                    'postal_code'        => 'string',
                    'scheduled_date'     => 'string (YYYY-MM-DD)',
                    'scheduled_time'     => 'string (HH:MM)',
                ],
                'requires_confirmation' => true,
            ];
            $actions[] = [
                'name'                  => 'cancel_booking',
                'description'           => 'Cancel an existing booking owned by the client',
                'parameters'            => ['booking_id' => 'integer'],
                'requires_confirmation' => true,
            ];
        }

        // Write actions — providers
        if ($user->isEmploye()) {
            $actions[] = [
                'name'                  => 'update_availability',
                'description'           => 'Toggle the provider online/offline status',
                'parameters'            => ['status' => 'online|offline|break'],
                'requires_confirmation' => false,
            ];
        }

        return $actions;
    }

    /**
     * Returns a compact string representation suitable for injection into a system prompt.
     */
    public function toPromptBlock(User $user): string
    {
        $actions = $this->availableActions($user);

        if (empty($actions)) {
            return '';
        }

        $lines = array_map(
            fn (array $a) => "- {$a['name']}: {$a['description']}",
            $actions
        );

        return "Actions disponibles :\n" . implode("\n", $lines);
    }
}
