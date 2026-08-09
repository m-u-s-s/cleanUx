<?php

namespace Tests\Feature\Dispatch;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\CandidateFinder;
use App\Services\Dispatch\DispatchCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/**
 * L'ANNUAIRE DES CANDIDATS — les filtres qu'on ne peut pas franchir (consignes 1, 2, 5).
 *
 * Chaque test ci-dessous couvre une façon dont le dispatch pouvait se tromper de personne, et
 * chacune de ces façons a réellement existé dans ce dépôt :
 *
 *  - le filtre métier avait un REPLI qui rendait la liste NON filtrée quand elle se vidait ;
 *  - « en ligne » se lisait sur `provider_profiles.is_online`, un drapeau qu'on pose et qui reste
 *    vrai quand l'application est morte depuis vingt minutes ;
 *  - rien n'empêchait d'envoyer une deuxième offre à quelqu'un qui en avait déjà une à l'écran.
 *
 * Les tests portent sur le SQL rendu, pas sur une intention : c'est la requête elle-même qui doit
 * refuser, pas un `if` qu'on peut contourner par un autre appelant.
 */
class CandidateFinderTest extends TestCase
{
    use OuvreLeCatalogue;
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    private ServiceZone $zone;

    private Trade $peinture;

    private Trade $babysitting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::create([
            'name' => 'Zone test', 'slug' => 'zone-cf-test', 'code' => 'CF-T',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->peinture = Trade::create([
            'slug' => 'peinture-cf', 'code' => 'PAINT-CF', 'name' => 'Peinture',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->babysitting = Trade::create([
            'slug' => 'babysitting-cf', 'code' => 'BABY-CF', 'name' => 'Babysitting',
            'is_active' => true, 'sort_order' => 2,
        ]);

        $this->ouvrirAuCatalogue($this->peinture, $this->zone);
        $this->ouvrirAuCatalogue($this->babysitting, $this->zone);
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function provider(
        Trade $trade,
        float $lat,
        float $lng,
        string $status = 'online',
        ?int $heartbeatMinutesAgo = 0,
        ?int $organizationId = null,
    ): User {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $this->zone->id,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $organizationId,
            'provider_type' => $organizationId
                ? ProviderType::COMPANY_WORKER->value
                : ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        ProviderPresence::create([
            'provider_user_id' => $user->id,
            'status' => $status,
            'current_lat' => $lat,
            'current_lng' => $lng,
            'heartbeat_at' => $heartbeatMinutesAgo === null ? null : now()->subMinutes($heartbeatMinutesAgo),
        ]);

        $user->trades()->syncWithoutDetaching([$trade->id]);

        return $user;
    }

    private function booking(Trade $trade, ?int $organizationId = null): Booking
    {
        return Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'service_zone_id' => $this->zone->id,
            'trade_id' => $trade->id,
            'booking_mode' => 'asap',
            'status' => 'en_attente',
            'destination_lat' => self::LAT,
            'destination_lng' => self::LNG,
            'assigned_provider_organization_id' => $organizationId,
        ]);
    }

    /** @return list<int> */
    private function ids(Booking $booking, int $radiusM = 10000, array $exclude = []): array
    {
        return app(CandidateFinder::class)
            ->immediate($booking, $radiusM, $exclude)
            ->map(fn (DispatchCandidate $c) => $c->id())
            ->all();
    }

    // ─── L'invariant métier ──────────────────────────────────────────────────────────────────

    #[Test]
    public function un_peintre_a_500_metres_ne_recoit_jamais_une_mission_babysitting(): void
    {
        $peintre = $this->provider($this->peinture, 50.8500, 4.3560);

        $candidats = $this->ids($this->booking($this->babysitting));

        $this->assertNotContains(
            $peintre->id,
            $candidats,
            'Le filtre métier vit dans le SQL : aucun repli ne doit le rouvrir.',
        );
    }

    #[Test]
    public function une_reservation_sans_metier_ne_rend_personne(): void
    {
        $this->provider($this->peinture, 50.8500, 4.3560);

        $booking = $this->booking($this->peinture);
        $booking->update(['trade_id' => null, 'service_catalog_id' => null]);

        // Pas « tout le monde », pas « au hasard » : PERSONNE. Une mission non pourvue est un
        // incident qu'on voit ; une mission pourvue par le mauvais métier est un client perdu.
        $this->assertSame([], $this->ids($booking->fresh()));
    }

    // ─── En ligne et position fraîche ────────────────────────────────────────────────────────

    #[Test]
    public function un_babysitter_hors_ligne_ne_recoit_rien_en_immediat(): void
    {
        $horsLigne = $this->provider($this->babysitting, 50.8470, 4.3530, 'offline');

        $this->assertNotContains($horsLigne->id, $this->ids($this->booking($this->babysitting)));
    }

    #[Test]
    public function un_prestataire_occupe_ou_en_pause_ne_recoit_rien(): void
    {
        $occupe = $this->provider($this->babysitting, 50.8470, 4.3530, 'busy');
        $pause = $this->provider($this->babysitting, 50.8471, 4.3531, 'on_break');

        $candidats = $this->ids($this->booking($this->babysitting));

        $this->assertNotContains($occupe->id, $candidats);
        $this->assertNotContains($pause->id, $candidats);
    }

    #[Test]
    public function une_position_perimee_ne_compte_pas(): void
    {
        // Le miroir binaire dirait « en ligne ». Le battement dit qu'il n'a rien envoyé depuis
        // vingt minutes : son téléphone est éteint, dans une poche, ou hors réseau.
        $perime = $this->provider($this->babysitting, 50.8470, 4.3530, 'online', 20);

        $this->assertNotContains($perime->id, $this->ids($this->booking($this->babysitting)));
    }

    // ─── L'ordre : la proximité d'abord ──────────────────────────────────────────────────────

    #[Test]
    public function le_plus_proche_passe_avant_le_plus_lointain(): void
    {
        $loin = $this->provider($this->babysitting, 50.9500, 4.4500);   // ~12 km
        $proche = $this->provider($this->babysitting, 50.8490, 4.3560); // ~400 m

        $candidats = $this->ids($this->booking($this->babysitting), 20000);

        $this->assertSame($proche->id, $candidats[0] ?? null);
        $this->assertContains($loin->id, $candidats);
    }

    #[Test]
    public function hors_rayon_personne(): void
    {
        $this->provider($this->babysitting, 50.9500, 4.4500); // ~12 km

        $this->assertSame([], $this->ids($this->booking($this->babysitting), 5000));
    }

    // ─── Une seule offre à la fois ───────────────────────────────────────────────────────────

    #[Test]
    public function un_prestataire_qui_a_deja_une_offre_n_en_recoit_pas_une_deuxieme(): void
    {
        $prestataire = $this->provider($this->babysitting, 50.8470, 4.3530);

        $autreBooking = $this->booking($this->babysitting);
        $mission = Mission::create([
            'booking_id' => $autreBooking->id,
            'status' => 'planned',
            'service_zone_id' => $this->zone->id,
        ]);

        MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'expires_at' => now()->addSeconds(20),
        ]);

        // Deux modales concurrentes font accepter la mauvaise course, et la première expire
        // pendant qu'on lit la seconde.
        $this->assertNotContains($prestataire->id, $this->ids($this->booking($this->babysitting)));
    }

    #[Test]
    public function une_offre_expiree_libere_le_prestataire(): void
    {
        $prestataire = $this->provider($this->babysitting, 50.8470, 4.3530);

        $autreBooking = $this->booking($this->babysitting);
        $mission = Mission::create([
            'booking_id' => $autreBooking->id,
            'status' => 'planned',
            'service_zone_id' => $this->zone->id,
        ]);

        MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now()->subMinute(),
            'expires_at' => now()->subSeconds(30),
        ]);

        $this->assertContains($prestataire->id, $this->ids($this->booking($this->babysitting)));
    }

    #[Test]
    public function les_deja_tentes_sont_exclus(): void
    {
        $prestataire = $this->provider($this->babysitting, 50.8470, 4.3530);

        $this->assertSame([], $this->ids($this->booking($this->babysitting), 10000, [$prestataire->id]));
    }

    // ─── Société ─────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function le_client_qui_choisit_une_societe_ne_voit_que_ses_salaries(): void
    {
        $societe = OrganizationAccount::create([
            'name' => 'ProServices', 'legal_name' => 'ProServices', 'slug' => 'proservices-cf',
            'type' => 'provider_company', 'status' => 'active',
        ]);
        $autre = OrganizationAccount::create([
            'name' => 'Concurrent', 'legal_name' => 'Concurrent', 'slug' => 'concurrent-cf',
            'type' => 'provider_company', 'status' => 'active',
        ]);

        $salarieChoisi = $this->provider($this->babysitting, 50.8470, 4.3530, 'online', 0, $societe->id);
        $salarieAutre = $this->provider($this->babysitting, 50.8471, 4.3531, 'online', 0, $autre->id);
        $independant = $this->provider($this->babysitting, 50.8472, 4.3532);

        $candidats = $this->ids($this->booking($this->babysitting, $societe->id));

        $this->assertContains($salarieChoisi->id, $candidats);
        $this->assertNotContains($salarieAutre->id, $candidats);
        $this->assertNotContains(
            $independant->id,
            $candidats,
            'Le client a choisi une société : un indépendant n’est pas ce qu’il a demandé.',
        );
    }

    #[Test]
    public function sans_societe_choisie_independants_et_salaries_concourent(): void
    {
        $societe = OrganizationAccount::create([
            'name' => 'ProServices2', 'legal_name' => 'ProServices2', 'slug' => 'proservices-cf2',
            'type' => 'provider_company', 'status' => 'active',
        ]);

        $salarie = $this->provider($this->babysitting, 50.8470, 4.3530, 'online', 0, $societe->id);
        $independant = $this->provider($this->babysitting, 50.8472, 4.3532);

        $candidats = $this->ids($this->booking($this->babysitting));

        $this->assertContains($salarie->id, $candidats);
        $this->assertContains($independant->id, $candidats);
    }

    // ─── Éligibilité ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function un_profil_non_verifie_ne_recoit_rien(): void
    {
        $prestataire = $this->provider($this->babysitting, 50.8470, 4.3530);
        ProviderProfile::where('user_id', $prestataire->id)->update(['verification_status' => 'pending']);

        $this->assertNotContains($prestataire->id, $this->ids($this->booking($this->babysitting)));
    }

    #[Test]
    public function un_compte_desactive_ne_recoit_rien(): void
    {
        $prestataire = $this->provider($this->babysitting, 50.8470, 4.3530);
        $prestataire->update(['is_active' => false]);

        $this->assertNotContains($prestataire->id, $this->ids($this->booking($this->babysitting)));
    }

    // ─── Planifié ────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function le_planifie_travaille_sur_la_zone_declaree_pas_sur_la_presence(): void
    {
        $horsLigne = $this->provider($this->babysitting, 50.8470, 4.3530, 'offline');

        $booking = $this->booking($this->babysitting);
        $booking->update(['booking_mode' => 'scheduled']);

        $candidats = app(CandidateFinder::class)
            ->scheduled($booking->fresh())
            ->map(fn (DispatchCandidate $c) => $c->id())
            ->all();

        // Personne n'attend derrière la porte : un prestataire hors ligne aujourd'hui sera
        // peut-être le meilleur pour jeudi 14 h.
        $this->assertContains($horsLigne->id, $candidats);
    }

    #[Test]
    public function le_planifie_garde_l_invariant_metier(): void
    {
        $peintre = $this->provider($this->peinture, 50.8470, 4.3530, 'offline');

        $booking = $this->booking($this->babysitting);
        $booking->update(['booking_mode' => 'scheduled']);

        $candidats = app(CandidateFinder::class)
            ->scheduled($booking->fresh())
            ->map(fn (DispatchCandidate $c) => $c->id())
            ->all();

        $this->assertNotContains($peintre->id, $candidats);
    }
}
