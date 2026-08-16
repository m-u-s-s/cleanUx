<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\DisponibilitesEmploye;
use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MISE À JOUR : CETTE SUITE DÉCRIVAIT UN COMPORTEMENT QU'ON A DÉLIBÉRÉMENT REMPLACÉ.
 *
 * Elle affirmait que la page écrit dans `disponibilites` — table à zéro ligne que rien ne lisait
 * hors d'elle-même — et que « Bloquer » SUPPRIME les créneaux du jour. Les deux étaient le défaut,
 * pas le contrat : un prestataire saisissait son horaire et restait introuvable à la
 * planification, et fermer « ce mardi » fermait tous les mardis à venir puisque les créneaux sont
 * récurrents.
 *
 * Les assertions suivent le nouveau contrat. Le comportement fin — chevauchement à la
 * modification, idempotence de la fermeture, appartenance — vit dans
 * `Tests\Feature\Availability\DisponibilitesEmployePageTest`.
 */
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

    private function creneau(User $user, int $weekday, string $debut, string $fin): AvailabilitySlot
    {
        return AvailabilitySlot::create([
            'provider_user_id' => $user->id,
            'weekday' => $weekday,
            'start_time' => $debut,
            'end_time' => $fin,
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);
    }

    public function test_mount_initialises_week_and_defaults(): void
    {
        $this->actingAs($this->employe());

        Livewire::test(DisponibilitesEmploye::class)
            ->assertOk()
            ->assertSet('weekStart', now()->startOfWeek()->toDateString())
            // Le formulaire porte un JOUR DE LA SEMAINE, plus une date : les créneaux sont
            // récurrents, c'est ce qui les rend réutilisables sans ressaisie.
            ->assertSet('weekday', 1)
            ->assertSet('heure_debut', '08:00')
            // 17:00 et non 12:00 : c'est le défaut posé à l'inscription, la page dit la même chose.
            ->assertSet('heure_fin', '17:00');
    }

    public function test_previous_and_next_week_navigation(): void
    {
        $this->actingAs($this->employe());

        Livewire::test(DisponibilitesEmploye::class)
            ->call('previousWeek')
            ->assertSet('weekStart', now()->startOfWeek()->subWeek()->toDateString())
            ->call('nextWeek')
            ->call('nextWeek')
            ->assertSet('weekStart', now()->startOfWeek()->addWeek()->toDateString());
    }

    public function test_save_creates_a_recurring_slot(): void
    {
        $user = $this->employe();
        $this->actingAs($user);

        Livewire::test(DisponibilitesEmploye::class)
            ->set('weekday', 3)
            ->set('heure_debut', '09:00')
            ->set('heure_fin', '12:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editingId', null);

        $slot = AvailabilitySlot::where('provider_user_id', $user->id)->sole();
        $this->assertSame(3, (int) $slot->weekday);
        $this->assertSame('09:00:00', substr((string) $slot->start_time, 0, 8));
    }

    public function test_save_validation_rejects_end_before_start(): void
    {
        $this->actingAs($this->employe());

        Livewire::test(DisponibilitesEmploye::class)
            ->set('weekday', 1)
            ->set('heure_debut', '14:00')
            ->set('heure_fin', '09:00')
            ->call('save')
            ->assertHasErrors('heure_fin');
    }

    public function test_save_detects_overlapping_slot(): void
    {
        $user = $this->employe();
        $this->creneau($user, 1, '09:00:00', '12:00:00');
        $this->actingAs($user);

        Livewire::test(DisponibilitesEmploye::class)
            ->set('weekday', 1)
            ->set('heure_debut', '11:00')
            ->set('heure_fin', '13:00')
            ->call('save')
            ->assertHasErrors('heure_debut');

        $this->assertSame(1, AvailabilitySlot::where('provider_user_id', $user->id)->count());
    }

    public function test_edit_loads_slot_then_save_updates(): void
    {
        $user = $this->employe();
        $slot = $this->creneau($user, 4, '08:00:00', '10:00:00');
        $this->actingAs($user);

        Livewire::test(DisponibilitesEmploye::class)
            ->call('edit', $slot->id)
            ->assertSet('editingId', $slot->id)
            ->assertSet('weekday', 4)
            ->assertSet('heure_debut', '08:00')
            ->assertSet('heure_fin', '10:00')
            ->set('heure_fin', '11:30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('11:30:00', substr((string) $slot->fresh()->end_time, 0, 8));
        $this->assertSame(1, AvailabilitySlot::where('provider_user_id', $user->id)->count());
    }

    public function test_delete_removes_slot_and_resets_when_editing(): void
    {
        $user = $this->employe();
        $slot = $this->creneau($user, 5, '08:00:00', '12:00:00');
        $this->actingAs($user);

        Livewire::test(DisponibilitesEmploye::class)
            ->call('edit', $slot->id)
            ->assertSet('editingId', $slot->id)
            ->call('delete', $slot->id)
            ->assertSet('editingId', null);

        $this->assertNull($slot->fresh());
    }

    /**
     * L'ANCIENNE ASSERTION DISAIT L'INVERSE, et c'était le défaut : « Bloquer » supprimait les
     * créneaux du jour. Comme ils sont récurrents, fermer un mardi les fermait TOUS.
     */
    public function test_close_day_creates_an_exception_and_keeps_the_week(): void
    {
        $user = $this->employe();
        $this->creneau($user, 2, '08:00:00', '17:00:00');
        $this->actingAs($user);

        $mardi = now()->startOfWeek()->addDay()->toDateString();

        Livewire::test(DisponibilitesEmploye::class)->call('closeDay', $mardi);

        $this->assertSame(
            1,
            AvailabilitySlot::where('provider_user_id', $user->id)->count(),
            'Fermer une date ne doit pas toucher à la semaine type.',
        );

        $this->assertSame(
            1,
            AvailabilityException::where('provider_user_id', $user->id)
                ->where('exception_type', AvailabilityException::TYPE_CLOSED)
                ->count(),
        );
    }

    public function test_render_exposes_week_days_and_slots(): void
    {
        $user = $this->employe();
        $this->creneau($user, 1, '08:00:00', '17:00:00');
        $this->actingAs($user);

        Livewire::test(DisponibilitesEmploye::class)
            ->assertOk()
            ->assertViewHas('weekDays', fn ($days) => $days->count() === 7)
            ->assertViewHas('slotsByWeekday', fn ($groupes) => $groupes->has(1))
            ->assertViewHas('closedDays');
    }
}
