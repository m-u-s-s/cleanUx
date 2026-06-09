<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\DisponibilitesEmploye;
use App\Models\Disponibilite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DisponibilitesEmployeCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    private function employe(): User
    {
        return User::factory()->employe()->create([
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    public function test_mount_initialises_week_and_date(): void
    {
        $this->actingAs($this->employe());

        Livewire::test(DisponibilitesEmploye::class)
            ->assertOk()
            ->assertSet('weekStart', now()->startOfWeek()->toDateString())
            ->assertSet('date', now()->toDateString())
            ->assertSet('heure_debut', '08:00')
            ->assertSet('heure_fin', '12:00');
    }

    public function test_previous_and_next_week_navigation(): void
    {
        $this->actingAs($this->employe());

        $start = now()->startOfWeek()->toDateString();

        Livewire::test(DisponibilitesEmploye::class)
            ->call('previousWeek')
            ->assertSet('weekStart', now()->startOfWeek()->subWeek()->toDateString())
            ->call('nextWeek')
            ->assertSet('weekStart', $start)
            ->call('nextWeek')
            ->assertSet('weekStart', now()->startOfWeek()->addWeek()->toDateString());
    }

    public function test_save_creates_a_new_slot(): void
    {
        $user = $this->employe();
        $this->actingAs($user);

        Livewire::test(DisponibilitesEmploye::class)
            ->set('date', now()->toDateString())
            ->set('heure_debut', '08:00')
            ->set('heure_fin', '10:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('editingId', null)
            ->assertSet('heure_debut', '08:00')
            ->assertSet('heure_fin', '12:00');

        $this->assertDatabaseHas('disponibilites', [
            'user_id' => $user->id,
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
        ]);
    }

    public function test_save_validation_rejects_end_before_start(): void
    {
        $this->actingAs($this->employe());

        Livewire::test(DisponibilitesEmploye::class)
            ->set('date', now()->toDateString())
            ->set('heure_debut', '10:00')
            ->set('heure_fin', '09:00')
            ->call('save')
            ->assertHasErrors(['heure_fin']);
    }

    public function test_save_detects_overlapping_slot(): void
    {
        $user = $this->employe();
        $this->actingAs($user);

        // Insert with an exact 'Y-m-d' date string so the component's
        // where('date', $this->date) equality match fires under SQLite.
        DB::table('disponibilites')->insert([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'heure_debut' => '08:00:00',
            'heure_fin' => '12:00:00',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(DisponibilitesEmploye::class)
            ->set('date', now()->toDateString())
            ->set('heure_debut', '09:00')
            ->set('heure_fin', '11:00')
            ->call('save')
            ->assertHasErrors(['heure_debut']);
    }

    public function test_edit_loads_slot_then_save_updates(): void
    {
        $user = $this->employe();
        $this->actingAs($user);

        $slot = Disponibilite::factory()->forEmploye($user)->create([
            'date' => now()->toDateString(),
            'heure_debut' => '08:00:00',
            'heure_fin' => '10:00:00',
        ]);

        Livewire::test(DisponibilitesEmploye::class)
            ->call('edit', $slot->id)
            ->assertSet('editingId', $slot->id)
            ->assertSet('heure_debut', '08:00')
            ->assertSet('heure_fin', '10:00')
            ->set('heure_fin', '11:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('11:00', $slot->fresh()->heure_fin);
    }

    public function test_delete_removes_slot_and_resets_when_editing(): void
    {
        $user = $this->employe();
        $this->actingAs($user);

        $slot = Disponibilite::factory()->forEmploye($user)->create([
            'date' => now()->toDateString(),
            'heure_debut' => '08:00:00',
            'heure_fin' => '10:00:00',
        ]);

        Livewire::test(DisponibilitesEmploye::class)
            ->call('edit', $slot->id)
            ->assertSet('editingId', $slot->id)
            ->call('delete', $slot->id)
            ->assertSet('editingId', null)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('disponibilites', ['id' => $slot->id]);
    }

    public function test_block_day_removes_all_slots_for_the_day(): void
    {
        $user = $this->employe();
        $this->actingAs($user);

        $date = now()->toDateString();
        Disponibilite::factory()->forEmploye($user)->count(3)->create([
            'date' => $date,
            'heure_debut' => '08:00:00',
            'heure_fin' => '09:00:00',
        ]);

        Livewire::test(DisponibilitesEmploye::class)
            ->call('blockDay', $date)
            ->assertDispatched('toast');

        $this->assertSame(0, Disponibilite::where('user_id', $user->id)->whereDate('date', $date)->count());
    }

    public function test_render_exposes_week_days_and_slots_by_day(): void
    {
        $user = $this->employe();
        $this->actingAs($user);

        Disponibilite::factory()->forEmploye($user)->create([
            'date' => now()->startOfWeek()->toDateString(),
            'heure_debut' => '08:00:00',
            'heure_fin' => '09:00:00',
        ]);

        $component = Livewire::test(DisponibilitesEmploye::class)->assertOk();

        $weekDays = $component->instance()->weekDays;
        $slotsByDay = $component->instance()->slotsByDay;

        $this->assertCount(7, $weekDays);
        $this->assertTrue($slotsByDay->isNotEmpty());
    }
}
