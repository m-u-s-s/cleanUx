<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Assistant\Tools\AssistantToolDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantToolDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function createConversation(User $user): AssistantConversation
    {
        return AssistantConversation::create([
            'user_id'      => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status'       => AssistantConversation::STATUS_OPEN,
        ]);
    }

    public function test_read_tool_executes_immediately_without_action_record(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $conv = $this->createConversation($user);

        $dispatcher = app(AssistantToolDispatcher::class);
        $result = $dispatcher->dispatch($user, $conv, [
            'id'    => 'toolu_test_1',
            'name'  => 'list_my_bookings',
            'input' => ['status' => 'all', 'limit' => 5],
        ]);

        $this->assertArrayHasKey('count',    $result);
        $this->assertArrayHasKey('bookings', $result);

        // Aucun AssistantAction créé pour les tools en lecture
        $this->assertSame(0, AssistantAction::count());
    }

    public function test_write_tool_creates_pending_action_and_does_not_execute(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $conv = $this->createConversation($user);

        $dispatcher = app(AssistantToolDispatcher::class);
        $result = $dispatcher->dispatch($user, $conv, [
            'id'    => 'toolu_test_2',
            'name'  => 'create_booking',
            'input' => [
                'scheduled_date' => '2026-06-15',
                'scheduled_time' => '10:00',
                'place_type'     => 'apartment',
                'surface_m2'     => 75,
                'address'        => 'Rue des Chats 12',
                'city'           => 'Bruxelles',
                'postal_code'    => '1000',
                'frequency'      => 'unique',
            ],
        ]);

        $this->assertTrue($result['needs_user_confirmation']);
        $this->assertArrayHasKey('assistant_action_id', $result);

        // Aucune Booking créée encore
        $this->assertSame(0, Booking::count());

        // Une AssistantAction en pending
        $this->assertSame(1, AssistantAction::count());
        $action = AssistantAction::first();
        $this->assertSame(AssistantAction::STATUS_PENDING_CONFIRMATION, $action->status);
        $this->assertSame('create_booking', $action->action_type);
    }

    public function test_confirm_and_execute_creates_real_booking(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $conv = $this->createConversation($user);

        $dispatcher = app(AssistantToolDispatcher::class);
        $dispatch = $dispatcher->dispatch($user, $conv, [
            'id'    => 'toolu_test_3',
            'name'  => 'create_booking',
            'input' => [
                'scheduled_date' => '2026-06-20',
                'scheduled_time' => '14:00',
                'place_type'     => 'house',
                'surface_m2'     => 120,
                'address'        => '7 Avenue Victor',
                'city'           => 'Liège',
                'postal_code'    => '4000',
            ],
        ]);

        $actionId = $dispatch['assistant_action_id'];

        $confirmResult = $dispatcher->confirmAndExecute($user, $actionId);

        $this->assertTrue($confirmResult['ok']);
        $this->assertSame(1, Booking::count());

        $booking = Booking::first();
        $this->assertSame('Liège', $booking->city);
        $this->assertSame(120, (int) $booking->surface_m2);
        $this->assertSame('pending', $booking->status);

        $action = AssistantAction::find($actionId);
        $this->assertSame(AssistantAction::STATUS_EXECUTED, $action->status);
        $this->assertNotNull($action->executed_at);
    }

    public function test_user_cannot_confirm_another_users_action(): void
    {
        $userA = User::factory()->create(['role' => 'client']);
        $userB = User::factory()->create(['role' => 'client']);
        $conv  = $this->createConversation($userA);

        $dispatcher = app(AssistantToolDispatcher::class);

        $dispatch = $dispatcher->dispatch($userA, $conv, [
            'id'    => 'toolu_test_4',
            'name'  => 'create_booking',
            'input' => [
                'scheduled_date' => '2026-06-25',
                'scheduled_time' => '09:00',
                'place_type'     => 'office',
                'surface_m2'     => 40,
                'address'        => 'Rue de la Loi 1',
                'city'           => 'Bruxelles',
                'postal_code'    => '1000',
            ],
        ]);

        $actionId = $dispatch['assistant_action_id'];

        $confirmResult = $dispatcher->confirmAndExecute($userB, $actionId);

        $this->assertFalse($confirmResult['ok']);
        $this->assertSame(0, Booking::count());
    }

    public function test_unknown_tool_name_returns_error(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $conv = $this->createConversation($user);

        $dispatcher = app(AssistantToolDispatcher::class);
        $result = $dispatcher->dispatch($user, $conv, [
            'id'    => 'toolu_test_5',
            'name'  => 'this_tool_does_not_exist',
            'input' => [],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('inconnu', strtolower($result['error']));
    }
}
