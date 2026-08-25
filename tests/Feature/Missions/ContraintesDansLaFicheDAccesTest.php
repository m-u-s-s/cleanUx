<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\OnSite\MissionAccessSheetService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LES TROIS CONTRAINTES DE LA RÉSERVATION ARRIVENT JUSQU'AU PRESTATAIRE.
 *
 * `CreateBookingAction` enregistre à chaque commande si le matériel est fourni, s'il y a un
 * animal, s'il y a un parking. Ces réponses n'arrivaient nulle part côté prestataire : il se
 * présentait sans savoir s'il devait charger son matériel dans la camionnette.
 *
 * Le même défaut existait sur la fiche web, corrigé plus tôt. Celui-ci est plus lourd, parce
 * que ce prestataire-là est déjà sur place quand il ouvre l'écran.
 *
 * À NE PAS CONFONDRE AVEC `preferences`, qui porte le carnet du LIEU — ce qui vaut pour un
 * site visité chaque semaine. Celles-ci sont ce que le client a répondu POUR CETTE FOIS.
 */
class ContraintesDansLaFicheDAccesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mission} */
    private function interventionArrivee(array $surcharges = []): array
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
        ], $surcharges));

        $mission = Mission::create([
            'booking_id' => $reservation->id,
            'lead_provider_user_id' => $prestataire->id,
            // La fiche est verrouillée tant que l'arrivée n'est pas confirmée.
            'status' => MissionStatus::ARRIVED,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
        ]);

        return [$prestataire, $mission];
    }

    public function test_la_fiche_porte_les_trois_contraintes(): void
    {
        [$prestataire, $mission] = $this->interventionArrivee([
            'materiel_fournit' => false,
            'presence_animaux' => true,
            'acces_parking' => true,
        ]);

        $fiche = app(MissionAccessSheetService::class)->pour($mission, $prestataire);

        $this->assertIsArray($fiche['constraints'] ?? null, 'Les contraintes de la réservation manquent.');
        $this->assertFalse($fiche['constraints']['equipment_provided']);
        $this->assertTrue($fiche['constraints']['pets_on_site']);
        $this->assertTrue($fiche['constraints']['parking_available']);
    }

    /** TÉMOIN — le matériel FOURNI ne se lit pas comme le matériel à apporter. */
    public function test_le_materiel_fourni_se_distingue(): void
    {
        [$prestataire, $mission] = $this->interventionArrivee(['materiel_fournit' => true]);

        $fiche = app(MissionAccessSheetService::class)->pour($mission, $prestataire);

        $this->assertTrue($fiche['constraints']['equipment_provided']);
    }

    /**
     * TÉMOIN — les contraintes ne remplacent pas les préférences du LIEU.
     *
     * Les deux répondent à des questions différentes : `preferences.pets` dit ce que le carnet
     * du lieu sait d'habitude, `constraints.pets_on_site` ce que le client a répondu cette fois.
     * Confondre les deux ferait disparaître l'une des deux sources.
     */
    public function test_les_preferences_du_lieu_restent_a_cote(): void
    {
        [$prestataire, $mission] = $this->interventionArrivee(['presence_animaux' => true]);

        $fiche = app(MissionAccessSheetService::class)->pour($mission, $prestataire);

        $this->assertArrayHasKey('preferences', $fiche);
        $this->assertArrayHasKey('constraints', $fiche);
    }
}
