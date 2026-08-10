<?php

namespace Tests\Feature\Dispatch;

use App\Enums\ProviderType;
use App\Livewire\Provider\OfferWatcher;
use App\Models\Booking;
use App\Models\MissionAssignment;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/**
 * LES SURFACES PRESTATAIRE — l'offre doit ARRIVER, pas seulement exister (consignes 6, 12).
 *
 * Le mode d'échec de ce dépôt est documenté : `AssignmentOfferScreen` était un écran d'offre
 * complet, avec compte à rebours, monté NULLE PART. Les tests passaient, le code était juste, et
 * aucun prestataire n'a jamais vu la modale.
 *
 * Ces tests portent donc sur la JOIGNABILITÉ : le point d'entrée répond, le composant web s'affiche
 * et ses boutons agissent. La modale native est couverte côté mobile par un test qui PRESSE
 * (`mobile/provider/__tests__/offers/OfferModal.test.tsx`).
 */
class SurfacesPrestataireTest extends TestCase
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
            'name' => 'Zone surfaces', 'slug' => 'zone-surfaces', 'code' => 'ZSF',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->trade = Trade::create([
            'slug' => 'plomberie-surfaces', 'code' => 'PLB-S', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_asap' => true,
        ]);

        $this->ouvrirAuCatalogue($this->trade, $this->zone);
    }

    private function prestataire(): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $this->zone->id,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        ProviderPresence::create([
            'provider_user_id' => $user->id,
            'status' => 'online',
            'current_lat' => self::LAT,
            'current_lng' => self::LNG,
            'heartbeat_at' => now(),
        ]);

        $user->trades()->syncWithoutDetaching([$this->trade->id]);

        return $user;
    }

    private function offrePour(User $prestataire): MissionAssignment
    {
        $booking = Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'employe_id' => null,
            'assigned_employee_id' => null,
            'service_zone_id' => $this->zone->id,
            'trade_id' => $this->trade->id,
            'booking_mode' => 'asap',
            'status' => 'en_attente',
            'destination_lat' => self::LAT,
            'destination_lng' => self::LNG,
            'date' => now()->toDateString(),
            'heure' => now()->format('H:i'),
        ]);

        $search = app(DispatchEngine::class)->openImmediate($booking);

        return MissionAssignment::query()
            ->where('mission_id', $search->mission_id)
            ->where('user_id', $prestataire->id)
            ->firstOrFail();
    }

    // ─── Le point d'entrée de repli ──────────────────────────────────────────────────────────

    #[Test]
    public function l_offre_en_cours_est_lisible_par_son_destinataire(): void
    {
        $prestataire = $this->prestataire();
        $offre = $this->offrePour($prestataire);

        $reponse = $this->actingAs($prestataire, 'sanctum')
            ->getJson('/api/provider/offers/current')
            ->assertOk();

        $charge = $reponse->json('data');

        $this->assertSame($offre->id, $charge['assignment_id']);
        $this->assertSame('Plomberie', $charge['trade_name']);
        // L'HORLOGE DU SERVEUR : c'est `expires_at` qui pilote le compte à rebours, jamais un
        // nombre de secondes figé à l'émission.
        $this->assertNotNull($charge['expires_at']);
        $this->assertNotNull($charge['ttl_seconds']);
    }

    /**
     * L'OFFRE VIVANTE EST AUSSI DANS LA BOÎTE DE RÉCEPTION.
     *
     * La modale est le canal principal, mais elle peut ne jamais s'afficher : application fermée à
     * l'arrivée, notification refusée, temps réel injoignable. Sans cette redondance, l'offre
     * n'existe alors NULLE PART pour le prestataire, et le seul témoin de son existence est le
     * compteur de silences côté exploitation.
     *
     * La liste est la SOURCE DE VÉRITÉ par sondage : elle ne montre que ce qui est encore ouvert,
     * donc une offre expirée en disparaît d'elle-même sans qu'aucun écran n'ait à la retirer.
     */
    /**
     * LA DISTANCE EST LÀ SUR TOUS LES CANAUX, pas seulement en temps réel.
     *
     * Le moteur la connaît — c'est elle qui classe les candidats — et la passait au canal temps
     * réel. Le SONDAGE et la MODALE WEB, eux, construisaient la charge utile sans elle : la même
     * offre affichait « 1,2 km » ou « — » selon le chemin par lequel elle arrivait. Or le sondage
     * est le canal de repli, celui qui marche toujours, et la distance est le premier critère d'un
     * refus : l'afficher vide revient à demander une décision sans son élément principal.
     */
    #[Test]
    public function l_offre_porte_sa_distance_sur_le_canal_de_sondage(): void
    {
        $prestataire = $this->prestataire();
        $this->offrePour($prestataire);

        $charge = $this->actingAs($prestataire, 'sanctum')
            ->getJson('/api/provider/offers/current')
            ->assertOk()
            ->json('data');

        $this->assertNotNull($charge['distance_m'], 'La distance manquait sur le canal de repli.');
        $this->assertNotNull($charge['distance_km']);

        // La fabrique pose le prestataire à quelques centaines de mètres : on vérifie un ordre de
        // grandeur plausible, pas seulement « non nul » — une distance fausse serait pire
        // qu'absente, elle ferait refuser une course qui est à deux rues.
        $this->assertLessThan(2000, (int) $charge['distance_m']);
    }

    #[Test]
    public function le_composant_web_affiche_aussi_la_distance(): void
    {
        $prestataire = $this->prestataire();
        $this->offrePour($prestataire);

        // `offer` est l'assignation ; `payload` est ce que la modale AFFICHE. Lire la première
        // rendait un modèle Eloquent dont `distance_m` n'est pas une colonne — donc null, sans
        // erreur : le test aurait échoué en accusant le correctif.
        $composant = Livewire::actingAs($prestataire)->test(OfferWatcher::class);
        $charge = $composant->instance()->payload;

        $this->assertNotNull($charge, 'Le composant web ne voit aucune offre.');
        $this->assertNotNull($charge['distance_m'], 'La distance manquait sur la modale web.');
    }

    #[Test]
    public function l_offre_immediate_apparait_dans_la_boite_de_reception(): void
    {
        $prestataire = $this->prestataire();
        $offre = $this->offrePour($prestataire);

        $reponse = $this->actingAs($prestataire, 'sanctum')
            ->getJson('/api/provider/assignments/inbox')
            ->assertOk();

        $this->assertSame([$offre->id], array_column($reponse->json('data'), 'id'));
    }

    #[Test]
    public function une_offre_expiree_disparait_de_la_boite_de_reception(): void
    {
        $prestataire = $this->prestataire();
        $offre = $this->offrePour($prestataire);

        // Le serveur a escaladé : la modale s'est fermée seule, la liste doit suivre.
        $offre->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->actingAs($prestataire, 'sanctum')
            ->getJson('/api/provider/assignments/inbox')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function l_adresse_exacte_n_est_pas_livree_avant_l_acceptation(): void
    {
        $prestataire = $this->prestataire();
        $this->offrePour($prestataire);

        $charge = $this->actingAs($prestataire, 'sanctum')
            ->getJson('/api/provider/offers/current')
            ->json('data');

        // Une offre refusée ne doit pas laisser l'adresse complète d'un client chez quelqu'un qui
        // n'ira jamais : ville et code postal seulement.
        $this->assertArrayNotHasKey('address', $charge);
        $this->assertArrayHasKey('approximate_address', $charge);
    }

    #[Test]
    public function un_autre_prestataire_ne_voit_pas_l_offre(): void
    {
        $destinataire = $this->prestataire();
        $this->offrePour($destinataire);

        $curieux = $this->prestataire();

        $this->assertNull(
            $this->actingAs($curieux, 'sanctum')
                ->getJson('/api/provider/offers/current')
                ->json('data'),
        );
    }

    // ─── La modale web ───────────────────────────────────────────────────────────────────────

    #[Test]
    public function le_composant_web_affiche_l_offre(): void
    {
        $prestataire = $this->prestataire();
        $this->offrePour($prestataire);

        Livewire::actingAs($prestataire)
            ->test(OfferWatcher::class)
            ->assertSee('Nouvelle mission')
            ->assertSee('Plomberie')
            ->assertSee('Accepter')
            ->assertSee('Refuser');
    }

    #[Test]
    public function accepter_depuis_le_web_assigne_la_mission(): void
    {
        $prestataire = $this->prestataire();
        $offre = $this->offrePour($prestataire);

        Livewire::actingAs($prestataire)
            ->test(OfferWatcher::class)
            ->call('accept', $offre->id);

        $this->assertSame('accepted', $offre->fresh()->assignment_status);
    }

    #[Test]
    public function refuser_depuis_le_web_libere_la_mission(): void
    {
        $prestataire = $this->prestataire();
        $offre = $this->offrePour($prestataire);

        Livewire::actingAs($prestataire)
            ->test(OfferWatcher::class)
            ->call('decline', $offre->id);

        $this->assertSame('declined', $offre->fresh()->assignment_status);
    }

    #[Test]
    public function un_prestataire_ne_peut_pas_accepter_l_offre_d_un_collegue(): void
    {
        $destinataire = $this->prestataire();
        $offre = $this->offrePour($destinataire);

        $curieux = $this->prestataire();

        /*
         * LIVEWIRE NE REJOUE PAS `mount()` : l'identifiant qui arrive avec l'action vient du
         * navigateur. Sans relecture filtrée sur l'utilisateur connecté, accepter la mission d'un
         * collègue tiendrait à changer un nombre dans les outils de développement.
         */
        Livewire::actingAs($curieux)
            ->test(OfferWatcher::class)
            ->call('accept', $offre->id);

        $this->assertSame('assigned', $offre->fresh()->assignment_status);
        $this->assertSame($destinataire->id, (int) $offre->fresh()->user_id);
    }

    #[Test]
    public function sans_offre_le_composant_ne_montre_rien(): void
    {
        $prestataire = $this->prestataire();

        Livewire::actingAs($prestataire)
            ->test(OfferWatcher::class)
            ->assertDontSee('Nouvelle mission');
    }
}
