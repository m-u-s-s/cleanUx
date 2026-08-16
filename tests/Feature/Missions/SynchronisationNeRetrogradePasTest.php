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

/**
 * SAUVEGARDER UNE RÉSERVATION NE DOIT PAS EFFACER LA PROGRESSION DE SA MISSION.
 *
 * Le défaut était muet et global. `RendezVousObserver::saved()` resynchronise à CHAQUE sauvegarde
 * d'une réservation `confirme`, et la synchronisation réécrivait le statut de la mission avec sa
 * valeur initiale. Écrire n'importe quelle colonne d'une réservation confirmée — une note interne,
 * une durée mesurée, un champ de facturation — faisait donc retomber à `assigned` une mission en
 * cours d'exécution.
 *
 * Ce qui rendait la chose introuvable : la cause était une écriture sur un AUTRE objet. Le
 * prestataire perdait son écran de terrain, le client lisait « en attente » pendant que quelqu'un
 * travaillait chez lui, et rien ne reliait les deux.
 *
 * CE FICHIER PORTE SON TÉMOIN. Sans le dernier test, les précédents passeraient au vert en
 * mesurant une synchronisation qui ne fait plus rien du tout.
 */
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

    /**
     * LE CHEMIN RÉEL DU DÉFAUT — par l'observateur, pas par un appel direct au service.
     *
     * C'est ainsi qu'il se produisait en exploitation : personne n'appelait la synchronisation, on
     * écrivait simplement une colonne sans rapport sur la réservation.
     */
    public function test_ecrire_une_colonne_sans_rapport_ne_touche_pas_la_mission(): void
    {
        $scenario = SpineScenario::make()->build();
        $scenario->booking->forceFill(['status' => BookingStatus::CONFIRME])->save();

        $scenario->mission->forceFill(['status' => MissionStatus::STARTED])->save();

        // Une note interne. Rien de plus anodin, et c'est tout ce qu'il fallait.
        $scenario->booking->forceFill(['commentaire_client' => 'Le portail est ouvert.'])->save();

        $this->assertSame(MissionStatus::STARTED, $scenario->mission->refresh()->status);
    }

    /**
     * LE TÉMOIN POSITIF — la synchronisation fait toujours son travail.
     *
     * Tant que la mission n'a pas commencé, la valeur initiale reste juste : nommer un salarié doit
     * la faire passer de `planned` à `assigned`. C'est la raison pour laquelle cette écriture de
     * statut existait, et elle est préservée. Sans ce test, « rien ne bouge » serait un succès.
     */
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

    /**
     * L'AUTRE MOITIÉ DU VA-ET-VIENT — et la raison pour laquelle elle se retire sur la MISSION.
     *
     * LE PIÈGE DE MONTAGE QUI A FAIT ÉCHOUER LA PREMIÈRE VERSION, et qui vaut pour tout ce dépôt :
     * le scénario garde en mémoire une réservation dont `employe_id` vaut encore `null`, alors que
     * la base porte l'identifiant posé par un autre chemin. Un `forceFill(['employe_id' => null])`
     * n'est alors PAS sale, Eloquent ne l'inclut pas dans son UPDATE, et la colonne reste garnie.
     * Le test mesurait un état qu'il croyait avoir posé. On écrit donc par requête directe.
     */
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
