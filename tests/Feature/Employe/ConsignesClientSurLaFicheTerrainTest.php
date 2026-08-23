<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\MissionFieldPage;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE PRESTATAIRE SUR PLACE NE VOYAIT AUCUNE CONSIGNE DU CLIENT.
 *
 * La carte « Client & accès » de la fiche terrain porte un panneau ambre intitulé « Notes
 * client ». Il était gardé par `@if($rdv?->notes)` — et `bookings.notes` n'est écrite par AUCUN
 * code. Le panneau ne s'est donc jamais affiché, depuis qu'il existe.
 *
 * Le commentaire du client vit ailleurs, dans `customer_comment` / `commentaire_client` : c'est
 * là que le parcours de commande le range, là que `CreateBookingFromApiAction` le reçoit, et là
 * que le tableau de bord prestataire et la page d'offre le lisent déjà — le tableau de bord avec
 * exactement le même habillage ambre, ce qui montre bien ce que la fiche voulait montrer.
 *
 * La confusion a une origine nommable : dans `BookingHub`, le champ de formulaire s'appelle
 * `notes` et s'enregistre dans `commentaire_client`. Qui a écrit la fiche a suivi le nom du
 * champ, pas celui de la colonne.
 *
 * Aucun test ne pouvait le voir : un panneau conditionnel qui ne s'affiche jamais ne casse rien,
 * il se contente de manquer. C'est la famille de défauts dominante de ce dépôt.
 */
class ConsignesClientSurLaFicheTerrainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Mission, 2: Booking}
     */
    private function intervention(array $surcharges = []): array
    {
        $client = User::factory()->client()->create();
        $prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $prestataire->id, 'status' => 'active']);

        $reservation = Booking::create(array_merge([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'employe_id' => $prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'devis_estime' => 80,
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ], $surcharges));

        $mission = Mission::create([
            'booking_id' => $reservation->id,
            'lead_provider_user_id' => $prestataire->id,
            'status' => MissionStatus::ASSIGNED,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
        ]);

        return [$prestataire, $mission, $reservation];
    }

    public function test_la_fiche_terrain_montre_la_consigne_laissee_par_le_client(): void
    {
        [$prestataire, $mission] = $this->intervention([
            'commentaire_client' => 'Portail au fond de la cour, sonner deux fois.',
        ]);

        $this->actingAs($prestataire);

        Livewire::test(MissionFieldPage::class, ['mission' => $mission])
            ->assertSee('Notes client')
            ->assertSee('Portail au fond de la cour, sonner deux fois.');
    }

    /**
     * LA CONSIGNE PASSE AUSSI PAR LE CÔTÉ ANGLAIS DE LA PAIRE.
     *
     * `commentaire_client` et `customer_comment` sont deux noms d'une même valeur, tenus d'accord
     * par `HasLegacyBookingAliases`. La fiche lit le côté moderne ; ce test vérifie que l'écriture
     * par le côté français y arrive quand même — sinon le formulaire société, qui écrit le
     * français, laisserait la fiche vide sans que rien ne le dise.
     */
    public function test_la_consigne_ecrite_en_anglais_arrive_aussi(): void
    {
        [$prestataire, $mission] = $this->intervention([
            'customer_comment' => 'Key under the mat.',
        ]);

        $this->actingAs($prestataire);

        Livewire::test(MissionFieldPage::class, ['mission' => $mission])
            ->assertSee('Key under the mat.');
    }

    /**
     * TÉMOIN NÉGATIF — sans consigne, le panneau reste absent.
     *
     * Sans ce contrôle, un panneau affiché systématiquement passerait les deux tests ci-dessus
     * tout en encombrant l'écran d'un cadre vide à chaque intervention.
     */
    public function test_sans_consigne_le_panneau_ne_s_affiche_pas(): void
    {
        [$prestataire, $mission] = $this->intervention();

        $this->actingAs($prestataire);

        Livewire::test(MissionFieldPage::class, ['mission' => $mission])
            ->assertDontSee('Notes client');
    }
}
