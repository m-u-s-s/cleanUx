<?php

namespace Tests\Feature\Simulation;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Missions\MissionAssignmentService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * LE VOLUME, LES SOUS-RÔLES, ET L'ÉTANCHÉITÉ ENTRE SOCIÉTÉS.
 *
 * Une société prestataire n'a pas un prestataire : elle a des chefs d'équipe et des renforts, et
 * chacun ne doit voir QUE ses missions. Deux erreurs opposées guettent, et toutes deux se lisent
 * dans la même liste : un renfort qui ne voit pas sa mission ne peut pas travailler, un renfort
 * qui voit celles des autres a accès à l'adresse et au téléphone de clients chez qui il n'ira
 * jamais.
 *
 * CES DÉFAUTS NE SE VOIENT QU'EN NOMBRE. Avec une société, un chef et une mission, toutes les
 * requêtes semblent justes — il n'y a rien à confondre. C'est à quinze personnes et cinquante
 * missions qu'un filtre manquant devient visible.
 *
 * La liste interrogée est celle que l'application prestataire appelle vraiment,
 * `GET /api/provider/missions/active`, et non une requête réécrite pour le test : une garde
 * vérifiée ailleurs que là où elle s'applique ne prouve rien.
 */
class VolumeEtReassignationTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * @var array<string, mixed> Le contexte géographique, construit UNE fois.
     *
     * LES FIXTURES NE MONTENT PAS EN VOLUME TOUTES SEULES : `BookingFactory` fabrique sinon une
     * hiérarchie géographique complète par réservation, et `RegionFactory` s'appuie sur le
     * générateur `unique()` de Faker — qui abandonne au bout de dix mille tentatives. Cinquante
     * réservations suffisaient à faire tomber le test sur une limite d'outillage, pas sur un
     * défaut du produit. Un seul contexte partagé lève la limite et rapproche du réel : cinquante
     * missions d'une même société se déroulent dans la même zone.
     */
    private array $contexte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contexte = $this->createCoverageContext();
    }

    /**
     * @return array{0: OrganizationAccount, 1: list<User>, 2: list<User>}
     */
    private function societe(int $chefs = 1, int $renforts = 2): array
    {
        $societe = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $creer = function (OrganizationRole $role) use ($societe): User {
            $user = User::factory()->employe()->create([
                'current_organization_id' => $societe->id,
                'is_active' => true,
                'status' => 'active',
            ]);

            OrganizationMember::create([
                'organization_account_id' => $societe->id,
                'user_id' => $user->id,
                'role' => $role->value,
                'status' => 'active',
            ]);

            return $user->fresh();
        };

        $lesChefs = [];
        for ($i = 0; $i < $chefs; $i++) {
            $lesChefs[] = $creer(OrganizationRole::TEAM_LEAD);
        }

        $lesRenforts = [];
        for ($i = 0; $i < $renforts; $i++) {
            $lesRenforts[] = $creer(OrganizationRole::WORKER);
        }

        return [$societe, $lesChefs, $lesRenforts];
    }

    private function missionPour(OrganizationAccount $societe): Mission
    {
        $client = User::factory()->client()->create();

        $reservation = Booking::factory()
            ->forStructuredContext(
                $this->contexte['service'],
                $this->contexte['zone'],
                $this->contexte['postalCode'],
            )
            ->create([
                'client_id' => $client->id,
                'customer_user_id' => $client->id,
                'status' => 'confirme',
                'devis_estime' => 120,
            ]);

        /*
         * LA MISSION EXISTE DÉJÀ : `RendezVousObserver` la crée avec la réservation, en
         * `updateOrCreate` sur `booking_id`. En fabriquer une seconde donnait deux missions pour
         * une réservation — un état hors de portée de la production, et qui faussait ce que ce
         * test croyait mesurer.
         */
        $mission = $reservation->missions()->latest('id')->firstOrFail();
        $mission->forceFill([
            'provider_organization_id' => $societe->id,
            'status' => MissionStatus::ASSIGNED,
            'planned_start_at' => now()->addHour(),
        ])->save();

        return $mission;
    }

    private function membre(OrganizationAccount $societe, User $user): OrganizationMember
    {
        return OrganizationMember::where('organization_account_id', $societe->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    /** Les identifiants de mission que CET utilisateur voit dans son application. */
    private function saListe(User $user): array
    {
        $reponse = $this->actingAs($user, 'sanctum')->getJson('/api/provider/missions/active');
        $reponse->assertOk();

        return collect($reponse->json('data'))->pluck('id')->sort()->values()->all();
    }

    /**
     * QUINZE PERSONNES, CINQUANTE MISSIONS : chacun voit les siennes et rien d'autre.
     */
    public function test_a_volume_chaque_renfort_ne_voit_que_ses_missions(): void
    {
        [$societe, $chefs, $renforts] = $this->societe(chefs: 3, renforts: 12);
        $assignation = app(MissionAssignmentService::class);

        $attendu = [];

        for ($i = 0; $i < 50; $i++) {
            $mission = $this->missionPour($societe);
            $destinataire = $renforts[$i % count($renforts)];

            $assignation->assigner($mission, $this->membre($societe, $destinataire));

            $attendu[$destinataire->id][] = $mission->id;
        }

        // Cinquante missions réparties : si la répartition dérape, elle dérape pour PLUSIEURS
        // renforts. Une assertion par tour n'en nommerait qu'un.
        $ecarts = [];

        foreach ($renforts as $renfort) {
            sort($attendu[$renfort->id]);
            $obtenu = $this->saListe($renfort);

            if ($obtenu !== $attendu[$renfort->id]) {
                $ecarts[] = sprintf(
                    'renfort #%d : attendu [%s], obtenu [%s]',
                    $renfort->id,
                    implode(',', $attendu[$renfort->id]),
                    implode(',', $obtenu),
                );
            }
        }

        $this->assertSame([], $ecarts, 'Ces renforts ne voient pas exactement leurs missions.');

        // Un chef d'équipe sans assignation personnelle ne récupère pas celles de la société :
        // diriger n'est pas exécuter, et la liste sert à savoir où l'on doit se rendre.
        $voyants = [];

        foreach ($chefs as $chef) {
            $liste = $this->saListe($chef);

            if ($liste !== []) {
                $voyants[] = sprintf('chef #%d voit [%s]', $chef->id, implode(',', $liste));
            }
        }

        $this->assertSame([], $voyants, 'Un chef d’équipe ne doit voir aucune mission de renfort dans cette liste.');
    }

    /**
     * L'ÉTANCHÉITÉ ENTRE SOCIÉTÉS — la garde qui protège l'adresse des clients.
     */
    public function test_une_societe_ne_voit_jamais_les_missions_d_une_autre(): void
    {
        [$societeA, , $renfortsA] = $this->societe(renforts: 2);
        [$societeB, , $renfortsB] = $this->societe(renforts: 2);
        $assignation = app(MissionAssignmentService::class);

        $missionA = $this->missionPour($societeA);
        $assignation->assigner($missionA, $this->membre($societeA, $renfortsA[0]));

        $missionB = $this->missionPour($societeB);
        $assignation->assigner($missionB, $this->membre($societeB, $renfortsB[0]));

        $this->assertSame([$missionA->id], $this->saListe($renfortsA[0]));
        $this->assertSame([$missionB->id], $this->saListe($renfortsB[0]));

        // Et le détail est refusé, pas seulement absent de la liste : une adresse ne se protège
        // pas en la cachant d'un écran si l'URL la rend quand même.
        $this->actingAs($renfortsB[0], 'sanctum')
            ->getJson("/api/provider/missions/{$missionA->id}")
            ->assertForbidden();
    }

    /**
     * RÉASSIGNER DOIT RETIRER À L'ANCIEN, pas seulement donner au nouveau.
     *
     * Une réassignation qui ajoute sans retirer laisse deux personnes convaincues d'y aller — et
     * l'une des deux découvre sur place qu'elle n'était pas attendue.
     */
    public function test_reassigner_retire_la_mission_a_l_ancien(): void
    {
        [$societe, , $renforts] = $this->societe(renforts: 3);
        $assignation = app(MissionAssignmentService::class);

        $mission = $this->missionPour($societe);
        $assignation->assigner($mission, $this->membre($societe, $renforts[0]));

        $this->assertSame([$mission->id], $this->saListe($renforts[0]));

        $assignation->assigner($mission->fresh(), $this->membre($societe, $renforts[1]));

        $this->assertSame([], $this->saListe($renforts[0]), 'L’ancien voit encore une mission qui ne lui revient plus.');
        $this->assertSame([$mission->id], $this->saListe($renforts[1]));
    }

    /**
     * UN RENFORT AJOUTÉ VOIT LA MISSION, sans en déposséder le responsable.
     */
    public function test_un_renfort_ajoute_voit_la_mission_sans_en_priver_le_responsable(): void
    {
        [$societe, , $renforts] = $this->societe(renforts: 3);
        $assignation = app(MissionAssignmentService::class);

        $mission = $this->missionPour($societe);
        $assignation->assigner($mission, $this->membre($societe, $renforts[0]));
        $assignation->ajouterRenfort($mission->fresh(), $this->membre($societe, $renforts[1]));

        $this->assertSame([$mission->id], $this->saListe($renforts[0]));
        $this->assertSame([$mission->id], $this->saListe($renforts[1]));
        $this->assertSame([], $this->saListe($renforts[2]));
    }

    /** Retirer un renfort le retire aussi de sa liste. */
    public function test_retirer_un_renfort_le_retire_de_sa_liste(): void
    {
        [$societe, , $renforts] = $this->societe(renforts: 2);
        $assignation = app(MissionAssignmentService::class);

        $mission = $this->missionPour($societe);
        $assignation->assigner($mission, $this->membre($societe, $renforts[0]));
        $assignation->ajouterRenfort($mission->fresh(), $this->membre($societe, $renforts[1]));

        $assignation->retirerRenfort($mission->fresh(), $renforts[1]->id);

        $this->assertSame([$mission->id], $this->saListe($renforts[0]));
        $this->assertSame([], $this->saListe($renforts[1]));
    }
}
