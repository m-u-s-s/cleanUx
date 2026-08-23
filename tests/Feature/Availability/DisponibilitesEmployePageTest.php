<?php

namespace Tests\Feature\Availability;

use App\Livewire\Employe\DisponibilitesEmploye;
use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Models\Disponibilite;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** LA PAGE WEB ÉCRIT LÀ OÙ TOUT LE MONDE LIT. */
class DisponibilitesEmployePageTest extends TestCase
{
    use RefreshDatabase;

    private function prestataire(): User
    {
        return User::factory()->employe()->create();
    }

    public function test_ajouter_un_creneau_ecrit_dans_availability_slots(): void
    {
        $user = $this->prestataire();

        Livewire::actingAs($user)
            ->test(DisponibilitesEmploye::class)
            ->set('weekday', 2)
            ->set('heure_debut', '09:00')
            ->set('heure_fin', '12:00')
            ->call('save')
            ->assertHasNoErrors();

        $slot = AvailabilitySlot::where('provider_user_id', $user->id)->sole();

        $this->assertSame(2, (int) $slot->weekday);
        $this->assertSame('09:00:00', substr((string) $slot->start_time, 0, 8));
        $this->assertSame('12:00:00', substr((string) $slot->end_time, 0, 8));

        // La table orpheline ne doit plus rien recevoir : c'est elle, le défaut.
        $this->assertSame(0, Disponibilite::where('user_id', $user->id)->count());
    }

    public function test_le_chevauchement_est_refuse_a_la_creation(): void
    {
        $user = $this->prestataire();

        AvailabilitySlot::create([
            'provider_user_id' => $user->id,
            'weekday' => 2,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(DisponibilitesEmploye::class)
            ->set('weekday', 2)
            ->set('heure_debut', '11:00')
            ->set('heure_fin', '13:00')
            ->call('save')
            ->assertHasErrors('heure_debut');

        $this->assertSame(1, AvailabilitySlot::where('provider_user_id', $user->id)->count());
    }

    /** L'ancienne version ne vérifiait le chevauchement QU'À LA CRÉATION : éditer un créneau pour le faire recouvrir un autre passait sans un mot. */
    public function test_le_chevauchement_est_refuse_aussi_a_la_modification(): void
    {
        $user = $this->prestataire();

        $matin = AvailabilitySlot::create([
            'provider_user_id' => $user->id, 'weekday' => 2,
            'start_time' => '08:00:00', 'end_time' => '10:00:00',
            'timezone' => 'Europe/Brussels', 'is_active' => true,
        ]);

        AvailabilitySlot::create([
            'provider_user_id' => $user->id, 'weekday' => 2,
            'start_time' => '14:00:00', 'end_time' => '17:00:00',
            'timezone' => 'Europe/Brussels', 'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(DisponibilitesEmploye::class)
            ->call('edit', $matin->id)
            ->set('heure_fin', '15:00')
            ->call('save')
            ->assertHasErrors('heure_debut');

        $this->assertSame('10:00:00', substr((string) $matin->fresh()->end_time, 0, 8));
    }

    /** Un créneau ne se chevauche pas lui-même : le modifier sans le déplacer doit passer. */
    public function test_modifier_un_creneau_sans_conflit_passe(): void
    {
        $user = $this->prestataire();

        $slot = AvailabilitySlot::create([
            'provider_user_id' => $user->id, 'weekday' => 2,
            'start_time' => '08:00:00', 'end_time' => '10:00:00',
            'timezone' => 'Europe/Brussels', 'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(DisponibilitesEmploye::class)
            ->call('edit', $slot->id)
            ->set('heure_fin', '11:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('11:00:00', substr((string) $slot->fresh()->end_time, 0, 8));
    }

    /** LE CŒUR DU DÉFAUT CORRIGÉ. L'ancien « Bloquer » supprimait les créneaux de la date. */
    public function test_fermer_un_jour_pose_une_exception_et_laisse_la_semaine_intacte(): void
    {
        $user = $this->prestataire();

        AvailabilitySlot::create([
            'provider_user_id' => $user->id, 'weekday' => 2,
            'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'timezone' => 'Europe/Brussels', 'is_active' => true,
        ]);

        $mardi = now()->startOfWeek()->addDay()->toDateString();

        Livewire::actingAs($user)
            ->test(DisponibilitesEmploye::class)
            ->call('closeDay', $mardi);

        $this->assertSame(
            1,
            AvailabilitySlot::where('provider_user_id', $user->id)->count(),
            'La semaine type ne doit PAS être touchée par la fermeture d’une date.',
        );

        $exception = AvailabilityException::where('provider_user_id', $user->id)->sole();
        $this->assertSame(AvailabilityException::TYPE_CLOSED, $exception->exception_type);
        $this->assertSame($mardi, $exception->date->format('Y-m-d'));
    }

    public function test_fermer_deux_fois_le_meme_jour_ne_cree_qu_une_exception(): void
    {
        $user = $this->prestataire();
        $jour = now()->startOfWeek()->addDay()->toDateString();

        $page = Livewire::actingAs($user)->test(DisponibilitesEmploye::class);
        $page->call('closeDay', $jour);
        $page->call('closeDay', $jour);

        $this->assertSame(1, AvailabilityException::where('provider_user_id', $user->id)->count());
    }

    public function test_rouvrir_un_jour_retire_l_exception(): void
    {
        $user = $this->prestataire();
        $jour = now()->startOfWeek()->addDay()->toDateString();

        $page = Livewire::actingAs($user)->test(DisponibilitesEmploye::class);
        $page->call('closeDay', $jour);

        $exception = AvailabilityException::where('provider_user_id', $user->id)->sole();
        $page->call('reopenDay', $exception->id);

        $this->assertSame(0, AvailabilityException::where('provider_user_id', $user->id)->count());
    }

    /** L'appartenance vit dans la requête, pas dans un contrôle à côté : le créneau d'autrui n'existe pas pour cette page. */
    public function test_on_ne_touche_pas_au_creneau_d_autrui(): void
    {
        $user = $this->prestataire();
        $autre = $this->prestataire();

        $sien = AvailabilitySlot::create([
            'provider_user_id' => $user->id, 'weekday' => 1,
            'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'timezone' => 'Europe/Brussels', 'is_active' => true,
        ]);

        $celuiDeLAutre = AvailabilitySlot::create([
            'provider_user_id' => $autre->id, 'weekday' => 1,
            'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'timezone' => 'Europe/Brussels', 'is_active' => true,
        ]);

        $page = Livewire::actingAs($user)->test(DisponibilitesEmploye::class);

        // Témoin : sans lui, une page cassée qui refuserait tout ferait passer le test.
        $page->call('edit', $sien->id)->assertSet('editingId', $sien->id);

        // Livewire relance l'exception plutot que de rendre une reponse : c'est le `findOrFail`
        // scope sur `provider_user_id` qui refuse, et c'est exactement ce qu'on veut prouver.
        try {
            $page->call('delete', $celuiDeLAutre->id);
            $this->fail('Le creneau d autrui ne devait pas etre supprimable.');
        } catch (ModelNotFoundException) {
            // attendu
        }

        $this->assertNotNull($celuiDeLAutre->fresh());
    }

    public function test_la_page_affiche_les_creneaux_sous_le_bon_jour(): void
    {
        $user = $this->prestataire();

        // weekday 0 = DIMANCHE côté serveur.
        AvailabilitySlot::create([
            'provider_user_id' => $user->id, 'weekday' => 0,
            'start_time' => '10:00:00', 'end_time' => '14:00:00',
            'timezone' => 'Europe/Brussels', 'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(DisponibilitesEmploye::class)
            ->assertSee('Dimanche')
            ->assertSee('10:00')
            ->assertSee('14:00');
    }
}
