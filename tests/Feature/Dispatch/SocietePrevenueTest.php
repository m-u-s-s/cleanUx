<?php

namespace Tests\Feature\Dispatch;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\PushNotification;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/**
 * LE CLIENT A CHOISI UNE SOCIÉTÉ : ses répartiteurs l'apprennent au départ, pas à l'arrivée.
 *
 * Restreindre les candidats aux salariés de la société choisie garantissait que la course ne parte
 * pas ailleurs, et s'arrêtait là. Côté société, la demande était INVISIBLE : le répartiteur la
 * découvrait par le salarié qui l'avait acceptée — ou jamais, si personne ne répondait et que la
 * recherche expirait. Celui qui pilote l'équipe ne pouvait donc pas faire la seule chose qu'on
 * attend de lui : réagir avant l'échéance.
 *
 * Trois invariants tiennent ce comportement, et chacun est une façon dont il pouvait se retourner :
 *
 *  1. SEULS LES PORTEURS DE `missions.dispatch` sont prévenus. Faire sonner le téléphone d'un
 *     nettoyeur pour une décision qu'il ne peut pas prendre est le meilleur moyen qu'on cesse de
 *     lire ces messages — y compris ceux qui comptent.
 *  2. UNE SEULE DEMANDE, UN SEUL MESSAGE. La clé d'idempotence porte l'identifiant de réservation :
 *     rouvrir la recherche, relancer après une vague épuisée, rejouer un job — rien ne doit
 *     renotifier.
 *  3. SANS SOCIÉTÉ CHOISIE, personne n'est prévenu. Une course de marketplace ne concerne aucune
 *     société en particulier, et le contraire ferait fuiter des demandes à des tiers.
 */
class SocietePrevenueTest extends TestCase
{
    use OuvreLeCatalogue;
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    private ServiceZone $zone;

    private Trade $trade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::create([
            'name' => 'Zone société', 'slug' => 'zone-societe-dispatch', 'code' => 'ZSD',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->trade = Trade::create([
            'slug' => 'plomberie-societe', 'code' => 'PLB-SO', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_asap' => true,
        ]);

        $this->ouvrirAuCatalogue($this->trade, $this->zone);
    }

    private function societe(): OrganizationAccount
    {
        return OrganizationAccount::create([
            'name' => 'ProServices', 'legal_name' => 'ProServices SRL', 'slug' => 'proservices-dispatch',
            'type' => 'provider_company', 'status' => 'active',
        ]);
    }

    private function membre(OrganizationAccount $societe, OrganizationRole $role): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'current_organization_id' => $societe->id,
            'organization_account_id' => $societe->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $societe->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        /*
         * SANS APPAREIL ENREGISTRÉ, RIEN NE PART — et c'est le comportement réel du notifieur
         * société : il ne connaît que le canal push. Le poser explicitement ici évite un test vert
         * pour la mauvaise raison (zéro message attendu, zéro message trouvé) et rappelle la limite
         * du dispositif : un répartiteur qui n'a jamais ouvert l'application n'est pas joignable.
         */
        DeviceToken::factory()->create(['user_id' => $user->id, 'invalidated_at' => null]);

        return $user;
    }

    /** Un salarié effectivement joignable par le moteur : en ligne, du bon métier, sur place. */
    private function salarieDisponible(OrganizationAccount $societe): User
    {
        $user = $this->membre($societe, OrganizationRole::WORKER);

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $societe->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
            'verification_status' => 'verified',
            'current_lat' => 50.8470,
            'current_lng' => 4.3530,
        ]);

        ProviderPresence::create([
            'provider_user_id' => $user->id,
            'status' => 'online',
            'current_lat' => 50.8470,
            'current_lng' => 4.3530,
            'heartbeat_at' => now(),
        ]);

        $user->trades()->syncWithoutDetaching([$this->trade->id]);

        return $user;
    }

    private function reservation(?OrganizationAccount $societe): Booking
    {
        return Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'employe_id' => null,
            'assigned_employee_id' => null,
            'assigned_provider_organization_id' => $societe?->id,
            'service_zone_id' => $this->zone->id,
            'trade_id' => $this->trade->id,
            'booking_mode' => 'asap',
            'status' => 'en_attente',
            'destination_lat' => self::LAT,
            'destination_lng' => self::LNG,
            'postal_code' => '1000',
            'city' => 'Bruxelles',
            'date' => now()->toDateString(),
            'heure' => now()->format('H:i'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function le_repartiteur_de_la_societe_choisie_est_prevenu(): void
    {
        $societe = $this->societe();
        $repartiteur = $this->membre($societe, OrganizationRole::OWNER);
        $this->salarieDisponible($societe);

        app(DispatchEngine::class)->openImmediate($this->reservation($societe));

        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $repartiteur->id,
            'title' => 'Nouvelle demande immédiate',
        ]);
    }

    #[Test]
    public function le_nettoyeur_de_la_societe_n_est_pas_prevenu(): void
    {
        $societe = $this->societe();
        $this->membre($societe, OrganizationRole::OWNER);
        $salarie = $this->salarieDisponible($societe);

        app(DispatchEngine::class)->openImmediate($this->reservation($societe));

        // Il recevra l'OFFRE, qui est son affaire ; pas l'alerte de pilotage, qui ne l'est pas.
        $this->assertDatabaseMissing('push_notifications', [
            'user_id' => $salarie->id,
            'title' => 'Nouvelle demande immédiate',
        ]);
    }

    #[Test]
    public function une_course_sans_societe_choisie_ne_previent_personne(): void
    {
        $societe = $this->societe();
        $repartiteur = $this->membre($societe, OrganizationRole::OWNER);
        $this->salarieDisponible($societe);

        app(DispatchEngine::class)->openImmediate($this->reservation(null));

        $this->assertDatabaseMissing('push_notifications', [
            'user_id' => $repartiteur->id,
            'title' => 'Nouvelle demande immédiate',
        ]);
    }

    #[Test]
    public function rouvrir_la_recherche_ne_renotifie_pas(): void
    {
        $societe = $this->societe();
        $repartiteur = $this->membre($societe, OrganizationRole::OWNER);
        $this->salarieDisponible($societe);

        $booking = $this->reservation($societe);
        $moteur = app(DispatchEngine::class);

        $moteur->openImmediate($booking);
        $moteur->openImmediate($booking->fresh());

        /*
         * UN SEUL MESSAGE pour une seule demande. La relance et la réouverture repassent par la
         * même porte ; sans clé d'idempotence, un répartiteur voyait son téléphone sonner à chaque
         * vague pour la course qu'il regardait déjà.
         */
        $this->assertSame(
            1,
            PushNotification::query()
                ->where('user_id', $repartiteur->id)
                ->where('title', 'Nouvelle demande immédiate')
                ->count(),
        );
    }
}
