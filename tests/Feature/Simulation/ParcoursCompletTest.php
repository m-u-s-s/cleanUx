<?php

namespace Tests\Feature\Simulation;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\EmployeArriveNotification;
use App\Notifications\MissionCompletedNotification;
use App\Notifications\MissionEndCodeNotification;
use App\Notifications\MissionPayoutAnnouncedNotification;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\OnSite\MissionChecklistService;
use App\Services\Rating\RatingService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesDomainFixtures;
use Tests\TestCase;

/**
 * LE PARCOURS COMPLET, POUR CHAQUE COUPLE DE RÔLES.
 *
 * Ce fichier ne teste pas une fonction : il JOUE une intervention du début à la fin — commande,
 * mise en route, arrivée, code de début, tâches obligatoires, code de fin, clôture, avis — et le
 * fait pour chaque combinaison de client et de prestataire.
 *
 * POURQUOI UN PARCOURS ENTIER PLUTÔT QUE DES TESTS UNITAIRES. Chacune de ces étapes était déjà
 * couverte séparément, et pourtant le chemin complet était cassé en cinq endroits : le code de fin
 * n'atteignait pas le client, la clôture ne terminait pas la réservation, un code valide était
 * détruit par un refus étranger, les tâches bloquantes étaient invisibles, et clôturer deux fois
 * rejouait tout. Aucun de ces défauts n'était visible depuis une seule étape — ils vivaient dans
 * les JOINTURES, que seul un parcours traverse.
 *
 * LES QUATRE COUPLES SONT JOUÉS AVEC LE MÊME CORPS. Un défaut qui n'apparaîtrait que pour un
 * client société servi par une société indépendante se verrait immédiatement, puisque la seule
 * chose qui change entre deux cas est le montage des acteurs.
 */
class ParcoursCompletTest extends TestCase
{
    use CreatesDomainFixtures;
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────
    // Les acteurs
    // ──────────────────────────────────────────────────────

    private function clientParticulier(): User
    {
        return User::factory()->client()->create(['phone' => '+3247'.random_int(1000000, 9999999)]);
    }

    /**
     * Un client SOCIÉTÉ : un compte d'organisation, un site, et un utilisateur qui commande pour lui.
     *
     * @return array{0: User, 1: OrganizationAccount}
     */
    private function clientSociete(): array
    {
        $utilisateur = $this->clientParticulier();

        $compte = OrganizationAccount::factory()->create([
            'type' => OrganizationType::CLIENT_COMPANY->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $compte->id,
            'user_id' => $utilisateur->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
        ]);

        $utilisateur->forceFill([
            'current_organization_id' => $compte->id,
            'organization_account_id' => $compte->id,
        ])->save();

        return [$utilisateur->fresh(), $compte];
    }

    /** Un prestataire INDÉPENDANT, encaissable. */
    private function prestataireIndependant(): User
    {
        $prestataire = User::factory()->employe()->create([
            'stripe_connect_account_id' => 'acct_'.uniqid(),
            'stripe_connect_status' => 'active',
        ]);

        ProviderProfile::create([
            'user_id' => $prestataire->id,
            'status' => 'active',
            'stripe_connect_account_id' => $prestataire->stripe_connect_account_id,
            'stripe_connect_status' => 'active',
        ]);

        return $prestataire->fresh();
    }

    /**
     * Une SOCIÉTÉ prestataire : un chef d'équipe, des renforts, une équipe de terrain.
     *
     * @return array{0: OrganizationAccount, 1: User, 2: list<User>}
     */
    private function prestataireSociete(int $renforts = 2): array
    {
        $societe = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $membre = function (OrganizationRole $role) use ($societe): User {
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

        $chef = $membre(OrganizationRole::TEAM_LEAD);
        $equipiers = [];
        for ($i = 0; $i < $renforts; $i++) {
            $equipiers[] = $membre(OrganizationRole::WORKER);
        }

        $equipe = FieldTeam::factory()->create([
            'organization_account_id' => $societe->id,
            'team_lead_user_id' => $chef->id,
            'status' => 'active',
        ]);

        foreach (array_merge([$chef], $equipiers) as $personne) {
            FieldTeamMember::create([
                'field_team_id' => $equipe->id,
                'user_id' => $personne->id,
                'status' => 'active',
            ]);
        }

        return [$societe, $chef, $equipiers];
    }

    // ──────────────────────────────────────────────────────
    // La commande et la mission
    // ──────────────────────────────────────────────────────

    private function reservation(User $client, string $mode = 'scheduled'): Booking
    {
        return Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'booking_mode' => $mode,
            'status' => 'confirme',
            'devis_estime' => 188.50,
            'payment_status' => 'pending',
            'organization_account_id' => $client->current_organization_id,
        ]);
    }

    /**
     * @param  list<User>  $renforts
     */
    private function mission(
        Booking $reservation,
        ?User $independant = null,
        ?OrganizationAccount $societe = null,
        ?User $chef = null,
        array $renforts = [],
    ): Mission {
        $mission = Mission::factory()->create([
            'booking_id' => $reservation->id,
            'lead_provider_user_id' => $independant?->id ?? $chef?->id,
            'lead_employee_id' => $independant?->id ?? $chef?->id,
            'provider_organization_id' => $societe?->id,
            'status' => MissionStatus::ASSIGNED,
            'planned_start_at' => now()->subHour(),
        ]);

        /*
         * L'ASSIGNATION D'UN SALARIÉ NAÎT `assigned`, PAS `accepted`.
         *
         * `accepted` est l'état du parcours MARKETPLACE — un indépendant à qui l'on propose et qui
         * répond oui. Un salarié n'accepte rien : son employeur décide. Confondre les deux fait
         * disparaître les missions d'équipe des listes du renfort.
         */
        foreach (array_merge($independant ? [$independant] : [], $chef ? [$chef] : [], $renforts) as $acteur) {
            MissionAssignment::factory()->create([
                'mission_id' => $mission->id,
                'user_id' => $acteur->id,
                'assignment_status' => $independant ? 'accepted' : 'assigned',
            ]);
        }

        // Les tâches obligatoires : sans elles, la clôture est refusée — et c'est le cas réel.
        $checklist = MissionChecklist::create([
            'mission_id' => $mission->id,
            'template_name' => 'Checklist standard',
            'status' => 'draft',
        ]);

        foreach (['Vérifier accès client', 'Nettoyer les pièces', 'Contrôle qualité'] as $intitule) {
            MissionChecklistItem::create([
                'mission_checklist_id' => $checklist->id,
                'label' => $intitule,
                'is_required' => true,
                'status' => 'pending',
            ]);
        }

        return $mission->fresh();
    }

    // ──────────────────────────────────────────────────────
    // Le parcours
    // ──────────────────────────────────────────────────────

    /**
     * Joue l'intervention du départ à l'avis, en passant par tous les gestes réels.
     *
     * @return array<string, mixed> Le compte rendu de ce qui a été vérifié à chaque étape.
     */
    private function jouerLeParcours(Mission $mission, User $intervenant, User $client): array
    {
        $cycle = app(MissionLifecycleService::class);

        // ── Départ et arrivée ────────────────────────────────────────────────
        $mission = $cycle->setEnRoute($mission, $intervenant);
        $this->assertSame(MissionStatus::EN_ROUTE, $mission->fresh()->status);

        $mission = $cycle->setArrived($mission->fresh(), $intervenant);
        $this->assertSame(MissionStatus::ARRIVED, $mission->fresh()->status);

        // ── Le code de DÉBUT doit avoir atteint le client ─────────────────────
        $codeDebut = $this->codeDepuisLaNotification($client, EmployeArriveNotification::class, 'startCode');
        $this->assertNotNull($codeDebut, 'Le client n’a reçu aucun code de début.');

        $mission = $cycle->validateStartCode($mission->fresh(), $intervenant, $codeDebut);
        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);

        // ── Les tâches obligatoires, cochées par le vrai service ──────────────
        $checklist = app(MissionChecklistService::class);
        $etat = $checklist->pour($mission->fresh());
        $this->assertTrue($etat['blocks_completion'], 'Les tâches obligatoires devraient bloquer.');

        foreach ($etat['checklists'] as $liste) {
            foreach ($liste['items'] as $item) {
                $checklist->basculer(MissionChecklistItem::findOrFail($item['id']), 'done', null, $intervenant);
            }
        }

        $this->assertFalse(
            $checklist->pour($mission->fresh())['blocks_completion'],
            'Toutes les tâches cochées, la clôture devrait être possible.',
        );

        // ── Le code de FIN doit avoir atteint le client ───────────────────────
        $codeFin = $this->codeDepuisLaNotification($client, MissionEndCodeNotification::class, 'endCode');
        $this->assertNotNull($codeFin, 'Le client n’a reçu aucun code de fin.');

        $mission = $cycle->validateEndCode($mission->fresh(), $intervenant, $codeFin);
        $this->assertSame(MissionStatus::COMPLETED, $mission->fresh()->status);

        // ── La réservation suit, sans quoi aucun avis n'est possible ──────────
        $reservation = $mission->fresh()->booking;
        $this->assertSame('termine', $reservation->status, 'La clôture doit terminer la réservation.');

        // ── Les deux parties sont prévenues ───────────────────────────────────
        Notification::assertSentTo($client, MissionCompletedNotification::class);

        /*
         * L'ANNONCE DE GAIN NE VAUT QUE POUR UN INDÉPENDANT.
         *
         * Dans une société, l'argent va à l'entreprise : chef d'équipe et renforts sont salariés.
         * Leur promettre un virement personnel serait faux — c'est ce que ce parcours a révélé.
         */
        if ($mission->fresh()->provider_organization_id === null) {
            Notification::assertSentTo($intervenant, MissionPayoutAnnouncedNotification::class);
        } else {
            Notification::assertNotSentTo($intervenant, MissionPayoutAnnouncedNotification::class);
        }

        // ── L'avis du client ──────────────────────────────────────────────────
        app(RatingService::class)->submit(
            $reservation->fresh(),
            $client,
            Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            ['rating' => 5, 'comment' => 'Travail impeccable.'],
        );

        $avis = Feedback::where('booking_id', $reservation->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->first();

        $this->assertNotNull($avis, 'L’avis du client n’a pas été enregistré.');
        $this->assertSame(5, (int) ($avis->rating ?? $avis->note));

        return [
            'mission' => $mission->fresh()->status,
            'reservation' => $reservation->fresh()->status,
            'avis' => (int) ($avis->rating ?? $avis->note),
        ];
    }

    /**
     * Le code en clair ne vit QUE dans la notification du client — il n'est jamais stocké.
     *
     * `MissionVerificationCodeService` n'enregistre qu'une empreinte : lire la base ne rendrait
     * jamais les six chiffres. Le parcours doit donc les récupérer là où le client les lit, ce qui
     * vérifie du même coup que le porteur fonctionne vraiment.
     *
     * @param  class-string  $classe
     */
    private function codeDepuisLaNotification(User $client, string $classe, string $propriete): ?string
    {
        $envoyees = Notification::sent($client, $classe);

        if ($envoyees->isEmpty()) {
            return null;
        }

        $derniere = $envoyees->last();

        return isset($derniere->{$propriete}) ? (string) $derniere->{$propriete} : null;
    }

    // ──────────────────────────────────────────────────────
    // La matrice
    // ──────────────────────────────────────────────────────

    public function test_client_particulier_avec_prestataire_independant(): void
    {
        Notification::fake();

        $client = $this->clientParticulier();
        $prestataire = $this->prestataireIndependant();
        $reservation = $this->reservation($client);
        $mission = $this->mission($reservation, independant: $prestataire);

        $rapport = $this->jouerLeParcours($mission, $prestataire, $client);

        $this->assertSame(MissionStatus::COMPLETED, $rapport['mission']);
    }

    public function test_client_particulier_avec_societe_prestataire(): void
    {
        Notification::fake();

        $client = $this->clientParticulier();
        [$societe, $chef, $renforts] = $this->prestataireSociete();
        $reservation = $this->reservation($client);
        $mission = $this->mission($reservation, societe: $societe, chef: $chef, renforts: $renforts);

        $rapport = $this->jouerLeParcours($mission, $chef, $client);

        $this->assertSame(MissionStatus::COMPLETED, $rapport['mission']);
    }

    public function test_client_societe_avec_prestataire_independant(): void
    {
        Notification::fake();

        [$client] = $this->clientSociete();
        $prestataire = $this->prestataireIndependant();
        $reservation = $this->reservation($client);
        $mission = $this->mission($reservation, independant: $prestataire);

        $rapport = $this->jouerLeParcours($mission, $prestataire, $client);

        $this->assertSame(MissionStatus::COMPLETED, $rapport['mission']);
    }

    public function test_client_societe_avec_societe_prestataire(): void
    {
        Notification::fake();

        [$client] = $this->clientSociete();
        [$societe, $chef, $renforts] = $this->prestataireSociete();
        $reservation = $this->reservation($client);
        $mission = $this->mission($reservation, societe: $societe, chef: $chef, renforts: $renforts);

        $rapport = $this->jouerLeParcours($mission, $chef, $client);

        $this->assertSame(MissionStatus::COMPLETED, $rapport['mission']);
    }

    /**
     * UNE MISSION IMMÉDIATE suit le même chemin — seul le mode de commande change.
     */
    public function test_mission_immediate_de_bout_en_bout(): void
    {
        Notification::fake();

        $client = $this->clientParticulier();
        $prestataire = $this->prestataireIndependant();
        $reservation = $this->reservation($client, mode: 'asap');
        $mission = $this->mission($reservation, independant: $prestataire);

        $rapport = $this->jouerLeParcours($mission, $prestataire, $client);

        $this->assertSame(MissionStatus::COMPLETED, $rapport['mission']);
    }

    /**
     * UN RENFORT DOIT POUVOIR MENER LA MISSION DE SON ÉQUIPE.
     *
     * Il n'a rien « accepté » — son employeur a décidé pour lui — et son assignation naît donc
     * `assigned`. Une garde écrite sur `accepted` seul le laisserait dehors.
     */
    public function test_un_renfort_de_societe_peut_mener_la_mission(): void
    {
        Notification::fake();

        $client = $this->clientParticulier();
        [$societe, $chef, $renforts] = $this->prestataireSociete();
        $reservation = $this->reservation($client);
        $mission = $this->mission($reservation, societe: $societe, chef: $chef, renforts: $renforts);

        $rapport = $this->jouerLeParcours($mission, $renforts[0], $client);

        $this->assertSame(MissionStatus::COMPLETED, $rapport['mission']);
    }
}
