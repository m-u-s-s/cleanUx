<?php

namespace Tests\Feature\Availability;

use App\Actions\Fortify\CreateNewUser;
use App\Models\AvailabilitySlot;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\Availability\DefaultAvailabilityProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA SEMAINE PAR DÉFAUT, ET CE QU'ELLE NE DOIT JAMAIS ÉCRASER.
 *
 * Un prestataire sans le moindre créneau est invisible à la planification : `AvailabilityService`
 * ne lui calcule aucune fenêtre, et rien à l'écran ne le lui dit. Il sortait de l'inscription
 * fermé, donc cassé.
 *
 * Le point délicat n'est pas de créer les créneaux, c'est de NE PAS les recréer : un prestataire
 * qui ferme délibérément son dimanche ne doit pas le voir revenir au prochain passage de la
 * commande de rattrapage.
 */
class DefaultAvailabilityOnRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function inscrire(array $extra = []): User
    {
        return app(CreateNewUser::class)->create(array_merge([
            'name' => 'Prestataire Test',
            'email' => 'presta-'.uniqid().'@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'provider_independent',
        ], $extra));
    }

    public function test_l_inscription_independant_pose_sept_creneaux_de_8h_a_17h(): void
    {
        $user = $this->inscrire();

        $slots = AvailabilitySlot::where('provider_user_id', $user->id)->orderBy('weekday')->get();

        $this->assertCount(7, $slots, 'Les sept jours doivent être ouverts par défaut.');
        $this->assertSame(
            [0, 1, 2, 3, 4, 5, 6],
            $slots->pluck('weekday')->all(),
            'Dimanche (0) compris : le défaut d’une place de marché est « ouvert ».',
        );

        foreach ($slots as $slot) {
            $this->assertSame('08:00:00', substr((string) $slot->start_time, 0, 8));
            $this->assertSame('17:00:00', substr((string) $slot->end_time, 0, 8));
            $this->assertTrue($slot->is_active);
        }
    }

    public function test_l_inscription_societe_pose_les_memes_creneaux(): void
    {
        $user = $this->inscrire([
            'account_type' => 'provider_company',
            'provider_company_name' => 'Brio Services SPRL',
        ]);

        $this->assertSame(7, AvailabilitySlot::where('provider_user_id', $user->id)->count());
    }

    /**
     * Un client n'est pas un prestataire : lui poser des créneaux créerait des lignes qu'aucune
     * lecture n'attend, et fausserait tout comptage de prestataires « configurés ».
     */
    public function test_un_client_ne_recoit_aucun_creneau(): void
    {
        $client = app(CreateNewUser::class)->create([
            'name' => 'Client Test',
            'email' => 'client-'.uniqid().'@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertSame(0, AvailabilitySlot::where('provider_user_id', $client->id)->count());
    }

    /**
     * LE CŒUR DU SUJET : un horaire déjà choisi ne se fait jamais réécrire.
     */
    public function test_un_prestataire_qui_a_deja_choisi_n_est_pas_touche(): void
    {
        $user = User::factory()->employe()->create();

        AvailabilitySlot::create([
            'provider_user_id' => $user->id,
            'weekday' => AvailabilitySlot::WEEKDAY_TUESDAY,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);

        $crees = app(DefaultAvailabilityProvisioner::class)->provision($user);

        $this->assertSame(0, $crees);
        $this->assertSame(1, AvailabilitySlot::where('provider_user_id', $user->id)->count());
        $this->assertSame('10:00:00', substr(
            (string) AvailabilitySlot::where('provider_user_id', $user->id)->value('start_time'), 0, 8
        ));
    }

    /**
     * Un jour DÉLIBÉRÉMENT FERMÉ est un créneau inactif, pas un créneau absent. Si le test
     * d'idempotence ne portait que sur les créneaux actifs, le dimanche fermé rouvrirait tout seul.
     */
    public function test_un_jour_desactive_ne_rouvre_pas(): void
    {
        $user = User::factory()->employe()->create();

        AvailabilitySlot::create([
            'provider_user_id' => $user->id,
            'weekday' => AvailabilitySlot::WEEKDAY_SUNDAY,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'timezone' => 'Europe/Brussels',
            'is_active' => false,
        ]);

        $this->assertSame(0, app(DefaultAvailabilityProvisioner::class)->provision($user));
        $this->assertSame(1, AvailabilitySlot::where('provider_user_id', $user->id)->count());
        $this->assertFalse((bool) AvailabilitySlot::where('provider_user_id', $user->id)->value('is_active'));
    }

    public function test_rejouer_le_provisionnement_ne_double_pas_les_creneaux(): void
    {
        $user = User::factory()->employe()->create();
        $provisioner = app(DefaultAvailabilityProvisioner::class);

        $this->assertSame(7, $provisioner->provision($user));
        $this->assertSame(0, $provisioner->provision($user));
        $this->assertSame(7, AvailabilitySlot::where('provider_user_id', $user->id)->count());
    }

    /**
     * Le but de tout ceci : le moteur de disponibilité voit enfin quelque chose. Sans cette
     * assertion, on vérifierait des lignes en base sans savoir si elles servent.
     */
    public function test_le_moteur_calcule_des_fenetres_pour_un_compte_neuf(): void
    {
        $user = $this->inscrire();
        $service = app(AvailabilityService::class);

        $demain = now()->addDay();

        $this->assertTrue(
            $service->isAvailable($user, $demain->copy()->setTime(10, 0), $demain->copy()->setTime(11, 0)),
            'Dix heures tombe dans 08:00–17:00.',
        );

        $this->assertFalse(
            $service->isAvailable($user, $demain->copy()->setTime(19, 0), $demain->copy()->setTime(20, 0)),
            'Dix-neuf heures est hors de la fenêtre — sinon le défaut ne contraindrait rien.',
        );
    }

    public function test_la_commande_de_rattrapage_ne_touche_que_les_comptes_a_zero(): void
    {
        $vide = User::factory()->employe()->create();

        $garni = User::factory()->employe()->create();
        AvailabilitySlot::create([
            'provider_user_id' => $garni->id,
            'weekday' => AvailabilitySlot::WEEKDAY_MONDAY,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);

        $this->artisan('availability:provision-defaults')->assertSuccessful();

        $this->assertSame(7, AvailabilitySlot::where('provider_user_id', $vide->id)->count());
        $this->assertSame(1, AvailabilitySlot::where('provider_user_id', $garni->id)->count());
    }

    public function test_l_essai_a_blanc_n_ecrit_rien(): void
    {
        $vide = User::factory()->employe()->create();

        $this->artisan('availability:provision-defaults', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, AvailabilitySlot::where('provider_user_id', $vide->id)->count());
    }
}
