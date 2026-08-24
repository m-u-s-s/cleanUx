<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Services\Missions\MissionFromRendezVousSyncService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** SAUVEGARDER UNE RÉSERVATION NE DOIT PAS EFFACER LA PROGRESSION DE SA MISSION. */
class SynchronisationNeRetrogradePasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{string}>
     */
    public static function statutsEngages(): array
    {
        return [
            [MissionStatus::EN_ROUTE],
            [MissionStatus::ARRIVED],
            [MissionStatus::STARTED],
            [MissionStatus::PAUSED],
            [MissionStatus::COMPLETED],
        ];
    }

    #[DataProvider('statutsEngages')]
    public function test_une_mission_engagee_ne_retombe_pas(string $statut): void
    {
        $scenario = SpineScenario::make()->build();
        $scenario->booking->forceFill(['status' => BookingStatus::CONFIRME])->save();

        $mission = $scenario->mission;
        $mission->forceFill(['status' => $statut])->save();

        app(MissionFromRendezVousSyncService::class)->syncFromRendezVous($scenario->booking->fresh());

        $this->assertSame($statut, $mission->refresh()->status);
    }

    /** LE CHEMIN RÉEL DU DÉFAUT — par l'observateur, pas par un appel direct au service. */
    public function test_ecrire_une_colonne_sans_rapport_ne_touche_pas_la_mission(): void
    {
        $scenario = SpineScenario::make()->build();
        $scenario->booking->forceFill(['status' => BookingStatus::CONFIRME])->save();

        $scenario->mission->forceFill(['status' => MissionStatus::STARTED])->save();

        // Une note interne. Rien de plus anodin, et c'est tout ce qu'il fallait.
        $scenario->booking->forceFill(['customer_comment' => 'Le portail est ouvert.'])->save();

        $this->assertSame(MissionStatus::STARTED, $scenario->mission->refresh()->status);
    }

    /** LE TÉMOIN POSITIF — la synchronisation fait toujours son travail. */
    public function test_avant_le_demarrage_la_synchronisation_suit_toujours_la_reservation(): void
    {
        $scenario = SpineScenario::make()->build();

        $scenario->booking->forceFill([
            'status' => BookingStatus::CONFIRME,
            'employe_id' => $scenario->provider->id,
        ])->save();

        $mission = $scenario->mission;
        $mission->forceFill(['status' => MissionStatus::PLANNED])->save();

        app(MissionFromRendezVousSyncService::class)->syncFromRendezVous($scenario->booking->fresh());

        $this->assertSame(
            MissionStatus::ASSIGNED,
            $mission->refresh()->status,
            'Un salarié nommé doit toujours faire passer une mission NON DÉMARRÉE à assigned.',
        );
    }

    /** L'AUTRE MOITIÉ DU VA-ET-VIENT — et la raison pour laquelle elle se retire sur la MISSION. */
    public function test_sans_intervenant_une_mission_non_demarree_revient_a_planned(): void
    {
        $scenario = SpineScenario::make()->build();

        Mission::query()->whereKey($scenario->mission->getKey())->update([
            'status' => MissionStatus::ASSIGNED,
            'lead_employee_id' => null,
            'lead_provider_user_id' => null,
        ]);

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'status' => BookingStatus::CONFIRME,
            'employe_id' => null,
        ]);

        app(MissionFromRendezVousSyncService::class)
            ->syncFromRendezVous(Booking::findOrFail($scenario->booking->getKey()));

        $this->assertSame(MissionStatus::PLANNED, $scenario->mission->refresh()->status);
    }

    /** Une mission qui n'existe pas encore naît bien avec sa valeur initiale. */
    public function test_une_mission_naissante_prend_sa_valeur_initiale(): void
    {
        $scenario = SpineScenario::make()->build();
        $scenario->mission->forceDelete();

        $scenario->booking->forceFill([
            'status' => BookingStatus::CONFIRME,
            'employe_id' => $scenario->provider->id,
        ])->save();

        $mission = app(MissionFromRendezVousSyncService::class)
            ->syncFromRendezVous($scenario->booking->fresh());

        $this->assertSame(MissionStatus::ASSIGNED, $mission->status);
        $this->assertSame(1, Mission::query()->where('booking_id', $scenario->booking->id)->count());
    }
}
