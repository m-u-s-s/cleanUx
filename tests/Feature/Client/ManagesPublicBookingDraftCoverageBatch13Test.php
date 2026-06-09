<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Exercises the ManagesPublicBookingDraft trait through its real host Livewire
 * component (PrendreRendezVous): restoring a persisted draft on mount,
 * persisting the current draft when redirecting to authentication, and the
 * early-return guards.
 */
class ManagesPublicBookingDraftCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    private const DRAFT_KEY = 'booking.public_draft';

    private function client(): User
    {
        return User::factory()->client()->create();
    }

    #[Test]
    public function restore_rehydrates_fields_and_clamps_step_when_resuming(): void
    {
        $this->actingAs($this->client());

        session()->put(self::DRAFT_KEY, [
            'surface' => '88',
            'commentaire_client' => 'note resume',
            'priorite' => 'urgente',
            'step' => 3,
        ]);

        $component = Livewire::withQueryParams(['resume' => 1])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('surface', '88')
            ->assertSet('commentaire_client', 'note resume')
            ->assertSet('priorite', 'urgente')
            ->assertSet('step', 3);

        // Draft is consumed (pull) and a confirmation flash is set.
        $this->assertNull(session(self::DRAFT_KEY));
        $this->assertNotNull($component->get('commentaire_client'));
        $this->assertSame(
            'Votre demande a été restaurée. Vous pouvez maintenant la confirmer.',
            session('info')
        );
    }

    #[Test]
    public function restore_clamps_an_out_of_range_step_to_the_upper_bound(): void
    {
        $this->actingAs($this->client());

        session()->put(self::DRAFT_KEY, [
            'surface' => '10',
            'step' => 99,
        ]);

        Livewire::withQueryParams(['resume' => 1])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('step', 5);
    }

    #[Test]
    public function restore_is_skipped_when_resume_flag_is_absent(): void
    {
        $this->actingAs($this->client());

        session()->put(self::DRAFT_KEY, [
            'surface' => '88',
            'step' => 4,
        ]);

        Livewire::test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('surface', null)
            ->assertSet('step', 1);

        // No resume → the draft remains untouched in the session.
        $this->assertIsArray(session(self::DRAFT_KEY));
    }

    #[Test]
    public function restore_returns_early_for_a_guest(): void
    {
        session()->put(self::DRAFT_KEY, [
            'surface' => '88',
            'step' => 4,
        ]);

        Livewire::withQueryParams(['resume' => 1])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('surface', null)
            ->assertSet('step', 1);

        $this->assertIsArray(session(self::DRAFT_KEY));
    }

    #[Test]
    public function restore_ignores_an_empty_draft_payload(): void
    {
        $this->actingAs($this->client());

        session()->put(self::DRAFT_KEY, []);

        Livewire::withQueryParams(['resume' => 1])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('step', 1);
    }

    #[Test]
    public function redirecting_to_authentication_persists_the_current_draft(): void
    {
        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->set('surface', '150')
            ->set('commentaire_client', 'persist me')
            ->call('redirectToAuthentication', 'register')
            ->assertRedirect();

        $draft = session(self::DRAFT_KEY);

        $this->assertIsArray($draft);
        $this->assertSame('150', $draft['surface']);
        $this->assertSame('persist me', $draft['commentaire_client']);
        // Persisting always forces the step to the final review step.
        $this->assertSame(5, $draft['step']);
        $this->assertArrayHasKey('selected_service_identifier', $draft);
    }

    #[Test]
    public function clearing_the_draft_forgets_the_session_entry(): void
    {
        $this->actingAs($this->client());

        $instance = Livewire::test(PrendreRendezVous::class)->instance();

        session()->put(self::DRAFT_KEY, ['surface' => '42', 'step' => 5]);

        $clear = new ReflectionMethod($instance, 'clearPublicBookingDraft');
        $clear->invoke($instance);

        $this->assertNull(session(self::DRAFT_KEY));
    }
}
