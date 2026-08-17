<?php

namespace Tests\Feature\Provider;

use App\Livewire\Provider\SafetyPanel;
use App\Models\AcademyCourse;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderQuest;
use App\Models\ProviderWalletTransaction;
use App\Models\SafetyAlert;
use App\Models\User;
use App\Services\Payments\ExpressPayoutService;
use App\Services\Provider\AcademyService;
use App\Services\Provider\DailyRouteService;
use App\Services\Provider\OfferStatsService;
use App\Services\Provider\QuestService;
use App\Services\Provider\TaxSummaryService;
use App\Services\Safety\SafetyAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PHASE 5 — SÉCURITÉ (E33), HEATMAP (E12), OBJECTIFS (E13), CASH-OUT (E14), OFFRES (E15),
 * ACADÉMIE (E16), TOURNÉE (E17/E34) ET FISCAL (E18).
 *
 * CE QUE CE FICHIER PROTÈGE EN PRIORITÉ :
 *
 *   1. L'ALERTE S'ÉCRIT AVANT toute notification, et rien ne peut l'empêcher — c'est la seule
 *      fonctionnalité de ce programme dont l'échec se compte en intégrité physique ;
 *   2. ON N'OUVRE PAS DEUX ALERTES : trois appuis sur un bouton d'urgence ne font pas trois
 *      personnes en difficulté ;
 *   3. LES FRAIS EXPRESS S'AFFICHENT EN EUROS avant le bouton, et le plancher protège le
 *      prestataire du ratio, pas la plateforme de la dépense ;
 *   4. LA TOURNÉE NE RÉORDONNE RIEN : un client attend à 14 h.
 */
class SecuriteEtProgressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function prestataire(): User
    {
        $user = User::factory()->employe()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->firstOrCreate([], [
            'provider_type' => 'independent',
            'status' => 'active',
        ]);

        return $user->fresh();
    }

    // ─── E33 : la sécurité ───────────────────────────────────────────────────

    #[Test]
    public function l_ecran_de_securite_repond(): void
    {
        $this->actingAs($this->prestataire())
            ->get(route('employe.safety'))
            ->assertOk();
    }

    #[Test]
    public function l_alerte_s_ecrit_meme_sans_position(): void
    {
        $prestataire = $this->prestataire();

        $alerte = app(SafetyAlertService::class)->declencher($prestataire);

        /*
         * UNE ALERTE SANS POSITION VAUT INFINIMENT MIEUX qu'un refus renvoyé à quelqu'un qui a peur.
         * L'écriture ne dépend d'aucune donnée facultative.
         */
        $this->assertSame(SafetyAlert::STATUS_OPEN, $alerte->status);
        $this->assertSame(SafetyAlert::LEVEL_EMERGENCY, $alerte->level);
    }

    #[Test]
    public function on_n_ouvre_pas_deux_alertes(): void
    {
        $prestataire = $this->prestataire();
        $service = app(SafetyAlertService::class);

        $premiere = $service->declencher($prestataire);
        $seconde = $service->declencher($prestataire);

        // Quelqu'un qui appuie trois fois appuie trois fois sur le même bouton ; trois lignes
        // feraient croire à trois personnes en difficulté.
        $this->assertSame($premiere->id, $seconde->id);
        $this->assertSame(1, SafetyAlert::query()->count());
    }

    #[Test]
    public function une_veille_qui_devient_une_urgence_monte_de_niveau(): void
    {
        $prestataire = $this->prestataire();
        $service = app(SafetyAlertService::class);

        $veille = $service->declencher($prestataire, SafetyAlert::LEVEL_CHECK_IN);
        $urgence = $service->declencher($prestataire, SafetyAlert::LEVEL_EMERGENCY);

        // L'escalade reste possible sur la MÊME alerte : la situation a changé, pas la personne.
        $this->assertSame($veille->id, $urgence->id);
        $this->assertSame(SafetyAlert::LEVEL_EMERGENCY, $urgence->level);
    }

    #[Test]
    public function l_accuse_de_reception_est_trace(): void
    {
        $prestataire = $this->prestataire();
        $admin = User::factory()->create(['platform_role' => 'admin']);

        $alerte = app(SafetyAlertService::class)->declencher($prestataire);
        $alerte = app(SafetyAlertService::class)->accuserReception($alerte, $admin);

        /*
         * SAVOIR QUE QUELQU'UN A VU L'ALERTE est ce que la personne sur place attend en premier —
         * plus que la résolution. Savoir qu'on est seul est ce qui rend une situation effrayante.
         */
        $this->assertSame(SafetyAlert::STATUS_ACKNOWLEDGED, $alerte->status);
        $this->assertNotNull($alerte->acknowledged_at);
    }

    #[Test]
    public function une_fausse_alerte_se_conserve(): void
    {
        $prestataire = $this->prestataire();

        $alerte = app(SafetyAlertService::class)->declencher($prestataire);
        $alerte = app(SafetyAlertService::class)->cloturer($alerte, $prestataire, true);

        // L'effacer empêcherait de voir qu'un bouton se déclenche tout seul dans une poche.
        $this->assertSame(SafetyAlert::STATUS_FALSE_ALARM, $alerte->status);
        $this->assertDatabaseHas('safety_alerts', ['id' => $alerte->id]);
    }

    #[Test]
    public function on_ne_referme_pas_l_alerte_d_un_autre(): void
    {
        $prestataire = $this->prestataire();
        $curieux = $this->prestataire();

        $alerte = app(SafetyAlertService::class)->declencher($prestataire);

        Sanctum::actingAs($curieux, ['*']);

        $this->postJson("/api/provider/safety/alerts/{$alerte->id}/close")->assertNotFound();
        $this->assertSame(SafetyAlert::STATUS_OPEN, $alerte->fresh()->status);
    }

    #[Test]
    public function le_bouton_d_urgence_n_est_pas_garde_par_provider_approved(): void
    {
        // Un compte non approuvé est PRÉCISÉMENT celui qui n'a encore aucun réflexe : un 403 au
        // pire moment serait le défaut le plus coûteux possible.
        $nouveau = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        Sanctum::actingAs($nouveau, ['*']);

        $this->postJson('/api/provider/safety/alerts', ['level' => 'emergency'])
            ->assertCreated();
    }

    #[Test]
    public function le_panneau_web_declenche_et_referme(): void
    {
        $prestataire = $this->prestataire();

        Livewire::actingAs($prestataire)
            ->test(SafetyPanel::class)
            ->call('declencher', SafetyAlert::LEVEL_EMERGENCY)
            ->assertOk();

        $alerte = SafetyAlert::query()->where('user_id', $prestataire->id)->firstOrFail();

        Livewire::actingAs($prestataire)
            ->test(SafetyPanel::class)
            ->call('fermer', $alerte->id);

        $this->assertSame(SafetyAlert::STATUS_RESOLVED, $alerte->fresh()->status);
    }

    // ─── E14 : le cash-out express ───────────────────────────────────────────

    #[Test]
    public function les_frais_express_s_affichent_en_euros(): void
    {
        $devis = app(ExpressPayoutService::class)->devis(10000);

        // « 1,5 % » se lit et ne se comprend pas ; « 1,50 € » se comprend. Le NET est le seul
        // chiffre qui compte pour celui qui reçoit.
        $this->assertSame(150, $devis['fee_cents']);
        $this->assertSame(9850, $devis['net_cents']);
        $this->assertTrue($devis['eligible']);
    }

    #[Test]
    public function sous_le_plancher_on_refuse(): void
    {
        $devis = app(ExpressPayoutService::class)->devis(1000);

        /*
         * LE PLANCHER PROTÈGE DU RATIO, PAS DE LA DÉPENSE. Sur dix euros, un euro de frais fait
         * 10 % : on ne propose pas une mauvaise affaire à quelqu'un qui n'a pas le choix.
         */
        $this->assertFalse($devis['eligible']);

        $this->expectException(ValidationException::class);

        app(ExpressPayoutService::class)->demander($this->prestataire(), 1000);
    }

    // ─── E15 : les statistiques d'offres ─────────────────────────────────────

    #[Test]
    public function une_offre_expiree_n_est_pas_un_refus(): void
    {
        $prestataire = $this->prestataire();
        $mission = Mission::factory()->create();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            // Ni acceptée, ni refusée, échéance passée — et c'est bien ainsi que le service
            // reconnaît une expiration, jamais par un statut : `assignment_status` reste sur son
            // défaut ici, exprès.
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $stats = app(OfferStatsService::class)->pour($prestataire);

        /*
         * UNE EXPIRATION SE CORRIGE EN RÉPONDANT PLUS VITE, un refus en changeant ce qu'on accepte :
         * les mélanger donnerait un conseil faux.
         */
        $this->assertSame(1, $stats['expired_count']);
        $this->assertSame(0, $stats['declined_count']);
    }

    #[Test]
    public function le_temps_de_reponse_est_une_mediane(): void
    {
        $prestataire = $this->prestataire();

        foreach ([10, 20, 3000] as $secondes) {
            MissionAssignment::query()->create([
                'mission_id' => Mission::factory()->create()->id,
                'user_id' => $prestataire->id,
                'accepted_at' => now(),
                'response_seconds' => $secondes,
            ]);
        }

        // Une offre répondue depuis un tunnel décalerait une moyenne au point de la rendre absurde :
        // la médiane décrit le comportement ordinaire, qui est ce qu'on cherche à améliorer.
        $this->assertSame(20, app(OfferStatsService::class)->pour($prestataire)['median_response_seconds']);
    }

    // ─── E13 : les objectifs ─────────────────────────────────────────────────

    #[Test]
    public function la_quete_dit_ce_qu_il_reste(): void
    {
        $prestataire = $this->prestataire();

        ProviderQuest::factory()->create(['target' => 5, 'metric' => ProviderQuest::METRIC_MISSIONS]);

        Mission::factory()->count(2)->create([
            'lead_provider_user_id' => $prestataire->id,
            'status' => 'completed',
        ]);

        $lignes = app(QuestService::class)->pour($prestataire);

        /*
         * CE QU'IL RESTE est le seul chiffre qui fait faire la course de trop. Une quête sans
         * compteur visible n'est pas une quête, c'est une surprise.
         */
        $this->assertSame(2, $lignes[0]['progress']);
        $this->assertSame(3, $lignes[0]['remaining']);
        $this->assertFalse($lignes[0]['is_completed']);
    }

    #[Test]
    public function la_quete_ne_se_recompense_pas_deux_fois(): void
    {
        $prestataire = $this->prestataire();

        ProviderQuest::factory()->create(['target' => 1, 'metric' => ProviderQuest::METRIC_MISSIONS]);

        Mission::factory()->create([
            'lead_provider_user_id' => $prestataire->id,
            'status' => 'completed',
        ]);

        $service = app(QuestService::class);
        $service->pour($prestataire);
        $service->pour($prestataire);

        // Ce service RECALCULE à chaque lecture : sans clé d'idempotence stable, la récompense
        // partirait autant de fois que l'écran est ouvert.
        $this->assertSame(1, \DB::table('provider_quest_progress')->count());
        $this->assertSame(1, \DB::table('provider_quest_progress')->whereNotNull('completed_at')->count());
    }

    // ─── E16 : l'académie ────────────────────────────────────────────────────

    #[Test]
    public function terminer_deux_fois_ne_double_rien(): void
    {
        $prestataire = $this->prestataire();
        $cours = AcademyCourse::factory()->create();

        $service = app(AcademyService::class);
        $premiere = $service->terminer($prestataire, $cours);
        $seconde = $service->terminer($prestataire, $cours);

        // Un double clic sur « j'ai terminé » n'est pas une erreur de l'utilisateur.
        $this->assertSame($premiere->id, $seconde->id);
    }

    #[Test]
    public function reussir_pese_dans_le_profil(): void
    {
        $prestataire = $this->prestataire();
        $cours = AcademyCourse::factory()->create(['specialty_bonus' => 7]);

        app(AcademyService::class)->terminer($prestataire, $cours);

        /*
         * RÉUSSIR DOIT CHANGER QUELQUE CHOSE, sinon personne ne suit. Un catalogue de cours sans
         * effet est un catalogue que personne n'ouvre deux fois.
         */
        $bonus = data_get($prestataire->fresh()->providerProfile?->metadata, 'academy.specialty_bonus');

        $this->assertSame(7, $bonus[$cours->code] ?? null);
    }

    // ─── E17 + E34 : la tournée ──────────────────────────────────────────────

    #[Test]
    public function la_tournee_suit_l_heure_prevue(): void
    {
        $prestataire = $this->prestataire();
        $jour = Carbon::now()->startOfDay();

        foreach ([14, 9, 11] as $heure) {
            $booking = Booking::factory()->create([
                'destination_lat' => 50.85,
                'destination_lng' => 4.35,
            ]);

            Mission::query()->where('booking_id', $booking->id)->delete();

            Mission::factory()->create([
                'booking_id' => $booking->id,
                'lead_provider_user_id' => $prestataire->id,
                'planned_start_at' => $jour->copy()->setTime($heure, 0),
                'planned_end_at' => $jour->copy()->setTime($heure + 1, 0),
            ]);
        }

        $tournee = app(DailyRouteService::class)->pourLaJournee($prestataire, $jour);

        /*
         * ON NE RÉORDONNE RIEN. Un client attend à 14 h : un outil qui propose de décaler des
         * rendez-vous pris ne sert à personne.
         */
        $heures = array_map(
            fn (array $etape) => (int) Carbon::parse($etape['planned_start_at'])->hour,
            $tournee['steps'],
        );

        $this->assertSame([9, 11, 14], $heures);
        $this->assertTrue($tournee['is_estimate']);
    }

    #[Test]
    public function sans_coordonnees_on_ne_devine_pas_de_trajet(): void
    {
        $prestataire = $this->prestataire();
        $jour = Carbon::now()->startOfDay();

        foreach ([9, 11] as $heure) {
            $booking = Booking::factory()->create([
                'destination_lat' => null,
                'destination_lng' => null,
            ]);

            Mission::query()->where('booking_id', $booking->id)->delete();

            Mission::factory()->create([
                'booking_id' => $booking->id,
                'lead_provider_user_id' => $prestataire->id,
                'planned_start_at' => $jour->copy()->setTime($heure, 0),
            ]);
        }

        $tournee = app(DailyRouteService::class)->pourLaJournee($prestataire, $jour);

        /*
         * INVENTER UNE DISTANCE ferait planifier une journée sur un chiffre faux. La garde
         * comparait ces colonnes à `null` alors qu'elles rendent une CHAÎNE : elle ne gardait rien,
         * et on aurait calculé une distance depuis le point zéro de l'Atlantique.
         */
        $this->assertNull($tournee['steps'][1]['travel_km']);
        $this->assertNull($tournee['steps'][1]['travel_minutes']);
    }

    // ─── E18 : le fiscal ─────────────────────────────────────────────────────

    #[Test]
    public function les_reprises_se_deduisent_du_revenu(): void
    {
        $prestataire = $this->prestataire();

        ProviderWalletTransaction::query()->create([
            'provider_user_id' => $prestataire->id,
            'type' => ProviderWalletTransaction::TYPE_EARNING,
            'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
            'amount' => 500.00,
            'currency' => 'EUR',
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'occurred_at' => Carbon::now(),
        ]);

        ProviderWalletTransaction::query()->create([
            'provider_user_id' => $prestataire->id,
            'type' => ProviderWalletTransaction::TYPE_REFUND_CLAWBACK,
            'direction' => ProviderWalletTransaction::DIRECTION_DEBIT,
            'amount' => 100.00,
            'currency' => 'EUR',
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'occurred_at' => Carbon::now(),
        ]);

        $resume = app(TaxSummaryService::class)->pourLAnnee($prestataire, (int) Carbon::now()->year);

        // Un remboursement au client reprend une part au prestataire : l'ignorer gonflerait le
        // revenu déclaré d'un argent qu'il n'a jamais gardé.
        $this->assertSame(50000, $resume['gross_cents']);
        $this->assertSame(40000, $resume['net_cents']);
        // L'estimation se dit : annoncer un montant sans le mot ferait provisionner faux.
        $this->assertTrue($resume['is_estimate']);
    }

    #[Test]
    public function l_export_fiscal_porte_son_avertissement(): void
    {
        $export = app(TaxSummaryService::class)->csv($this->prestataire(), (int) Carbon::now()->year);

        // Un CSV se transmet et se lit sans l'écran qui l'accompagnait : l'avertissement voyage
        // DANS le fichier.
        $this->assertStringContainsString('CHARGES ESTIMEES', $export['content']);
        $this->assertStringContainsString('a verifier avec votre comptable', $export['content']);
    }

    // ─── L'API ───────────────────────────────────────────────────────────────

    #[Test]
    public function l_api_de_croissance_sert_les_sept_modules(): void
    {
        $prestataire = $this->prestataire();
        Sanctum::actingAs($prestataire, ['*']);

        $this->getJson('/api/provider/growth/heatmap')->assertOk()->assertJsonPath('meta.is_observation', true);
        $this->getJson('/api/provider/growth/quests')->assertOk();
        $this->getJson('/api/provider/growth/offer-stats')->assertOk();
        $this->getJson('/api/provider/growth/courses')->assertOk();
        $this->getJson('/api/provider/growth/daily-route')->assertOk();
        $this->getJson('/api/provider/growth/tax-summary')->assertOk();
        $this->postJson('/api/provider/growth/express-quote', ['amount_cents' => 5000])
            ->assertOk()
            ->assertJsonPath('data.eligible', true);
    }

    #[Test]
    public function un_client_n_atteint_pas_l_api_de_croissance(): void
    {
        Sanctum::actingAs(User::factory()->client()->create(), ['*']);

        // La croissance d'un prestataire dit ce qu'il gagne et comment il travaille.
        $this->getJson('/api/provider/growth/tax-summary')->assertForbidden();
    }

    #[Test]
    public function les_deux_modules_figurent_au_repertoire(): void
    {
        $entrees = collect(config('modules.catalogue'))
            ->where('context', 'employe')
            ->pluck('route')
            ->all();

        $this->assertContains('employe.safety', $entrees);
        $this->assertContains('employe.heatmap', $entrees);
    }
}
