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
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA FICHE TERRAIN MONTRE TOUT D'UN COUP, EN CASES.
 *
 * Le panneau du créneau était une liste verticale de paires libellé/valeur : sur un
 * téléphone tenu d'une main sur un chantier, il fallait la parcourir ligne à ligne pour
 * savoir à quelle heure commencer.
 *
 * Trois contraintes n'y figuraient MÊME PAS — matériel fourni, animaux, parking — alors
 * que `CreateBookingAction` les enregistre à chaque réservation. Le prestataire arrivait
 * sans savoir s'il devait apporter son matériel.
 */
class CasesDeLaFicheTerrainTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mission} */
    private function intervention(array $surcharges = [], array $surchargesMission = []): array
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

        $mission = Mission::create(array_merge([
            'booking_id' => $reservation->id,
            'lead_provider_user_id' => $prestataire->id,
            'status' => MissionStatus::ASSIGNED,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ], $surchargesMission));

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
        ]);

        return [$prestataire, $mission];
    }

    public function test_l_heure_de_debut_et_la_duree_sont_en_cases(): void
    {
        [$prestataire, $mission] = $this->intervention([], [
            'planned_start_at' => Carbon::parse('2026-09-10 08:30'),
            'planned_end_at' => Carbon::parse('2026-09-10 11:00'),
        ]);

        $this->actingAs($prestataire);

        Livewire::test(MissionFieldPage::class, ['mission' => $mission])
            ->assertSee('Début')
            ->assertSee('08:30')
            ->assertSee('Fin prévue')
            ->assertSee('11:00')
            // La durée se déduit des deux bornes : le prestataire n'a pas à la calculer.
            ->assertSee('150 min');
    }

    /**
     * LES TROIS CONTRAINTES D'ACCÈS — elles n'étaient visibles NULLE PART sur cette fiche.
     */
    public function test_les_contraintes_d_acces_sont_montrees(): void
    {
        [$prestataire, $mission] = $this->intervention([
            'materiel_fournit' => false,
            'presence_animaux' => true,
            'acces_parking' => true,
        ]);

        $this->actingAs($prestataire);

        Livewire::test(MissionFieldPage::class, ['mission' => $mission])
            ->assertSee('Matériel')
            ->assertSee('À apporter')
            ->assertSee('Animaux')
            ->assertSee('Parking');
    }

    /** TÉMOIN — le matériel FOURNI ne se lit pas comme le matériel à apporter. */
    public function test_le_materiel_fourni_se_distingue(): void
    {
        [$prestataire, $mission] = $this->intervention(['materiel_fournit' => true]);

        $this->actingAs($prestataire);

        Livewire::test(MissionFieldPage::class, ['mission' => $mission])
            ->assertSee('Fourni')
            ->assertDontSee('À apporter');
    }

    /** TÉMOIN NÉGATIF — sans créneau planifié, aucune heure n'est inventée. */
    public function test_sans_creneau_aucune_heure_n_est_inventee(): void
    {
        [$prestataire, $mission] = $this->intervention([], [
            'planned_start_at' => null,
            'planned_end_at' => null,
        ]);

        $this->actingAs($prestataire);

        $rendu = Livewire::test(MissionFieldPage::class, ['mission' => $mission])
            ->assertSee('Non planifiée')
            ->html();

        /*
         * `assertDontSee('min')` aurait passé pour une mauvaise raison — puis échoué pour une
         * autre : « min » vit dans `minmax()`, dans « administration », dans mille endroits.
         * Un témoin négatif doit viser la FORME exacte qu'il interdit, sinon il mesure le bruit.
         */
        $this->assertDoesNotMatchRegularExpression('/\d+\s*min<\/span>/u', $rendu);
    }
}
