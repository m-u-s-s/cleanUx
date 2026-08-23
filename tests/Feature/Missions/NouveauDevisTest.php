<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionFeatureSuspension;
use App\Models\MissionMedia;
use App\Models\MissionQuoteRevision;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\MissionQuoteRevisionService;
use App\Services\Missions\MissionTodoService;
use App\Services\Missions\OnSite\MissionChecklistService as OnSiteChecklistService;
use App\Services\Missions\QuoteRevisionPricing;
use App\Services\Missions\QuoteRevisionTopUp;
use App\Services\Missions\QuoteRevisionWindow;
use App\Services\Payments\CommissionService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Database\Seeders\CancellationPoliciesSeeder;
use Database\Seeders\CancellationQuestionnaireSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** LE NOUVEAU DEVIS — et surtout la fenêtre qui empêche d'en faire une arme. */
class NouveauDevisTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $prestataire;

    private function mission(string $moteur = 'domicile', ?Carbon $demarree = null): Mission
    {
        $this->client = User::factory()->client()->create();
        $this->prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $this->prestataire->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $this->client->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
            'devis_estime' => 50.00,
            'estimated_price' => 50.00,
        ] + match ($moteur) {
            'vehicule' => ['dropoff_lat' => 50.9010, 'dropoff_lng' => 4.4844],
            'horaire' => ['purchased_minutes' => 180],
            default => [],
        });

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $this->prestataire->id,
            'status' => $demarree ? MissionStatus::STARTED : MissionStatus::ARRIVED,
            'actual_start_at' => $demarree,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $this->prestataire->id,
            'arrived_at' => $demarree ?? Carbon::now(),
        ]);

        return $mission->fresh('booking');
    }

    private function fenetre(): QuoteRevisionWindow
    {
        return app(QuoteRevisionWindow::class);
    }

    // ── TÉMOINS : la fenêtre est ouverte quand elle doit l'être ───────────────

    public function test_a_l_arrivee_la_fenetre_est_ouverte(): void
    {
        $etat = $this->fenetre()->etat($this->mission());

        $this->assertTrue($etat['open']);
        $this->assertNull($etat['reason']);
    }

    public function test_une_photo_avant_ne_ferme_rien(): void
    {
        $mission = $this->mission();

        MissionMedia::create([
            'mission_id' => $mission->id,
            'uploaded_by_user_id' => $this->prestataire->id,
            'media_type' => MissionMedia::TYPE_BEFORE_PHOTO,
            'path' => 'missions/avant.jpg',
        ]);

        $this->assertTrue(
            $this->fenetre()->etat($mission->fresh())['open'],
            'la photo « avant » se prend justement pour constater l’écart',
        );
    }

    // ── LES TROIS FERMETURES ──────────────────────────────────────────────────

    public function test_une_tache_cochee_ferme_la_fenetre(): void
    {
        $mission = $this->mission();
        $item = app(MissionTodoService::class)->ajouter($mission, $this->client, 'Nettoyer la hotte');

        // Coché PAR LE VRAI CHEMIN : `basculer()` pose `completed_at`, que la fenêtre lit pour
        // savoir si le prestataire a agi. Écrire `status` à la main laisserait cette date nulle et
        // le test mesurerait un état que l'application ne produit jamais.
        app(OnSiteChecklistService::class)->basculer($item, 'done', null, $this->prestataire);

        $etat = $this->fenetre()->etat($mission->fresh());

        $this->assertFalse($etat['open']);
        $this->assertStringContainsString('commencé', $etat['reason']);
    }

    public function test_une_photo_apres_ferme_la_fenetre(): void
    {
        $mission = $this->mission();

        MissionMedia::create([
            'mission_id' => $mission->id,
            'uploaded_by_user_id' => $this->prestataire->id,
            'media_type' => MissionMedia::TYPE_AFTER_PHOTO,
            'path' => 'missions/apres.jpg',
        ]);

        $this->assertFalse($this->fenetre()->etat($mission->fresh())['open']);
    }

    public function test_l_echeance_ferme_la_fenetre(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $mission = $this->mission(demarree: Carbon::parse('2026-08-18 10:00:00'));

        Carbon::setTestNow('2026-08-18 10:31:00');
        $etat = $this->fenetre()->etat($mission->fresh());

        $this->assertFalse($etat['open']);
        $this->assertStringContainsString('délai', $etat['reason']);
        Carbon::setTestNow();
    }

    /** LA SYMÉTRIE, et c'est la garde la plus importante du module. */
    public function test_une_tache_ajoutee_par_le_client_rouvre_la_fenetre(): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');
        $mission = $this->mission(demarree: Carbon::parse('2026-08-18 10:00:00'));

        Carbon::setTestNow('2026-08-18 10:28:00');
        app(MissionTodoService::class)->ajouter($mission, $this->client, 'Et les vitres aussi');

        // L'échéance de base tombe à 10:30 ; l'ajout rouvre jusqu'à 10:34.
        Carbon::setTestNow('2026-08-18 10:32:00');

        $this->assertTrue(
            $this->fenetre()->etat($mission->fresh())['open'],
            'le client a changé la demande : le prestataire doit pouvoir y répondre',
        );

        // ... et six minutes après l'ajout, elle est refermée.
        Carbon::setTestNow('2026-08-18 10:35:00');
        $this->assertFalse($this->fenetre()->etat($mission->fresh())['open']);

        Carbon::setTestNow();
    }

    public function test_une_course_n_a_pas_de_revision(): void
    {
        $etat = $this->fenetre()->etat($this->mission('vehicule'));

        $this->assertFalse($etat['open']);
        $this->assertStringContainsString('trajet', $etat['reason']);
    }

    public function test_une_mission_horaire_n_a_pas_de_revision(): void
    {
        $etat = $this->fenetre()->etat($this->mission('horaire'));

        $this->assertFalse($etat['open']);
        $this->assertStringContainsString('temps', $etat['reason']);
    }

    // ── LA TARIFICATION RÉVISÉE ───────────────────────────────────────────────

    /** LE TÉMOIN : sans remise, le total révisé est exactement le prix du service. */
    public function test_sans_remise_le_total_est_le_prix_du_service(): void
    {
        $mission = $this->mission();

        $prix = app(QuoteRevisionPricing::class)->recalculer($mission->booking, 30000);

        $this->assertSame(30000, $prix['total_cents']);
        $this->assertNull($prix['breakdown']['promo']);
    }

    /** UNE REMISE AU POURCENTAGE GRANDIT AVEC LE PRIX — c'est le terme même du code, et c'est en faveur du client, qui n'a pas demandé cette augmentation. */
    public function test_une_remise_au_pourcentage_se_recalcule(): void
    {
        $mission = $this->mission();
        $this->remise($mission->booking, 'percent', 20);

        $prix = app(QuoteRevisionPricing::class)->recalculer($mission->booking, 30000);

        $this->assertSame(24000, $prix['total_cents']);
        $this->assertSame(6000, $prix['breakdown']['promo']['discount_cents']);
        $this->assertSame('REMISE20', $prix['breakdown']['promo']['code']);
    }

    /** UN BON DE 10 € RESTE UN BON DE 10 €, quel que soit le montant. */
    public function test_une_remise_en_montant_reste_fixe(): void
    {
        $mission = $this->mission();
        $this->remise($mission->booking, 'fixed_amount', 10);

        $this->assertSame(29000, app(QuoteRevisionPricing::class)->recalculer($mission->booking, 30000)['total_cents']);
    }

    /** Le plafond du code tient : « -50 % jusqu'à 30 € » ne devient pas 150 € de remise. */
    public function test_le_plafond_de_la_remise_tient(): void
    {
        $mission = $this->mission();
        $this->remise($mission->booking, 'percent', 50, plafond: 30);

        $this->assertSame(27000, app(QuoteRevisionPricing::class)->recalculer($mission->booking, 30000)['total_cents']);
    }

    private function remise(Booking $booking, string $type, float $valeur, ?float $plafond = null): void
    {
        $code = PromoCode::create([
            'code' => 'REMISE20',
            'discount_type' => $type,
            'discount_value' => $valeur,
            'max_discount_amount' => $plafond,
        ]);

        PromoCodeRedemption::create([
            'promo_code_id' => $code->id,
            'user_id' => $this->client->id,
            'booking_id' => $booking->id,
            'status' => 'applied',
            'booking_amount_before' => 50,
            'discount_amount' => 10,
            'booking_amount_after' => 40,
            'currency' => 'EUR',
        ]);
    }

    // ── PROPOSER ──────────────────────────────────────────────────────────────

    /** LE TÉMOIN : sur une mission à domicile, à l'arrivée, avec motif et preuve, ça passe. */
    public function test_le_prestataire_propose_un_prix_revise(): void
    {
        $mission = $this->mission();

        $revision = $this->revisions()->proposer(
            $mission, $this->prestataire, 30000, 'Deux cents mètres carrés, pas vingt.', [1],
        );

        $this->assertSame(5000, $revision->original_total_cents);
        $this->assertSame(30000, $revision->revised_total_cents);
        $this->assertSame(25000, $revision->complementCents());
        $this->assertTrue($revision->attendLeClient());
    }

    public function test_sans_preuve_la_revision_est_refusee(): void
    {
        $this->expectExceptionMessage('photo');
        $this->revisions()->proposer($this->mission(), $this->prestataire, 30000, 'Plus grand', []);
    }

    public function test_sans_motif_la_revision_est_refusee(): void
    {
        $this->expectExceptionMessage('justifie');
        $this->revisions()->proposer($this->mission(), $this->prestataire, 30000, '   ', [1]);
    }

    public function test_une_seule_revision_vivante_a_la_fois(): void
    {
        $mission = $this->mission();
        $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        $this->expectExceptionMessage('attend déjà');
        $this->revisions()->proposer($mission, $this->prestataire, 40000, 'Encore plus', [1]);
    }

    public function test_un_prix_identique_n_est_pas_une_revision(): void
    {
        $this->expectExceptionMessage('rien à réviser');
        $this->revisions()->proposer($this->mission(), $this->prestataire, 5000, 'Pareil', [1]);
    }

    public function test_un_prestataire_suspendu_ne_revise_plus(): void
    {
        $mission = $this->mission();

        MissionFeatureSuspension::create([
            'user_id' => $this->prestataire->id,
            'feature' => MissionFeatureSuspension::OPTION_REVISION,
            'level' => 1,
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addDays(13),
            'reason' => 'Trois verdicts',
        ]);

        $this->expectExceptionMessage('suspendue');
        $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);
    }

    /** LE TÉMOIN de la suspension : une fois levée, la proposition repasse. */
    public function test_une_suspension_levee_rouvre_l_option(): void
    {
        $mission = $this->mission();

        MissionFeatureSuspension::create([
            'user_id' => $this->prestataire->id,
            'feature' => MissionFeatureSuspension::OPTION_REVISION,
            'level' => 1,
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addDays(13),
            'reason' => 'Trois verdicts',
            'lifted_at' => Carbon::now(),
            'lifted_by_user_id' => $this->prestataire->id,
            'lift_reason' => 'Rétablie par l’administrateur',
        ]);

        $this->assertNotNull(
            $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]),
        );
    }

    public function test_le_prestataire_retire_sa_proposition(): void
    {
        $mission = $this->mission();
        $revision = $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        $retiree = $this->revisions()->retirer($revision, $this->prestataire);

        $this->assertSame(MissionQuoteRevision::STATUT_RETIREE, $retiree->status);
        $this->assertNull($this->revisions()->vivante($mission->fresh()));
    }

    private function revisions(): MissionQuoteRevisionService
    {
        return app(MissionQuoteRevisionService::class);
    }

    // ── ACCEPTER ET REFUSER ───────────────────────────────────────────────────

    /** L'ÉCHEC DU COMPLÉMENT NE TOUCHE PAS L'EMPREINTE D'ORIGINE. */
    public function test_un_complement_refuse_laisse_le_devis_d_origine_intact(): void
    {
        $mission = $this->mission();
        $revision = $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        try {
            $this->revisions()->accepter($revision, $this->client);
            $this->fail('le complément ne pouvait pas aboutir sans compte de paiement');
        } catch (DomainException $e) {
            $this->assertStringContainsString('complément', $e->getMessage());
        }

        $this->assertSame(
            MissionQuoteRevision::STATUT_PAIEMENT_ECHOUE,
            $revision->fresh()->status,
        );
        $this->assertSame(
            50.0,
            (float) $mission->booking->fresh()->devis_estime,
            'le devis d’origine n’a pas bougé',
        );
    }

    /** LE TÉMOIN : quand le complément aboutit, le devis est réécrit et la commission suit. */
    public function test_un_complement_autorise_reecrit_le_devis(): void
    {
        $mission = $this->mission();
        $revision = $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        // Le seul point du module qui parle au réseau est isolé : on le remplace, tout le reste
        // s'exécute pour de vrai.
        $this->app->instance(QuoteRevisionTopUp::class, new class extends QuoteRevisionTopUp
        {
            public function __construct() {}

            public function autoriser(MissionQuoteRevision $revision, ?string $paymentMethodId = null): array
            {
                return ['ok' => true, 'intent_id' => 'pi_complement_test', 'error' => null];
            }
        });

        $acceptee = $this->revisions()->accepter($revision, $this->client);

        $this->assertSame(MissionQuoteRevision::STATUT_ACCEPTEE, $acceptee->status);
        $this->assertSame('pi_complement_test', $acceptee->top_up_payment_intent_id);

        $reservation = $mission->booking->fresh();
        $this->assertSame(300.0, (float) $reservation->devis_estime);
        $this->assertSame(30000, (int) $reservation->payment_amount_cents);
        $this->assertSame(
            30000,
            (int) app(CommissionService::class)->calculateForBooking($reservation)['total_cents'],
            'la commission se recalcule sur le montant réellement autorisé',
        );
    }

    public function test_le_client_refuse_et_choisit_de_continuer(): void
    {
        $mission = $this->mission();
        $revision = $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        $refusee = $this->revisions()->refuser($revision, $this->client, MissionQuoteRevision::DECISION_POURSUIVRE);

        $this->assertSame(MissionQuoteRevision::STATUT_REFUSEE, $refusee->status);
        $this->assertFalse($refusee->doitEtreAnnulee());
        $this->assertSame(50.0, (float) $mission->booking->fresh()->devis_estime);
    }

    /** L'ARRÊT ANNULE VRAIMENT, ET IL EST GRATUIT. */
    public function test_le_client_refuse_et_choisit_d_arreter(): void
    {
        $this->seed(CancellationPoliciesSeeder::class);
        $this->seed(CancellationQuestionnaireSeeder::class);

        $mission = $this->mission();
        $revision = $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        $refusee = $this->revisions()->refuser($revision, $this->client, MissionQuoteRevision::DECISION_ARRETER);

        $this->assertTrue($refusee->doitEtreAnnulee());
        $this->assertSame(50.0, (float) $mission->booking->fresh()->devis_estime);

        $annulation = BookingCancellationV2::query()->where('booking_id', $mission->booking_id)->firstOrFail();

        $this->assertSame('quote_revision_declined', $annulation->reason_code);
        $this->assertTrue($annulation->exempt_applied, 'le refus d’un devis abusif est gratuit');
        $this->assertSame(0, $annulation->fee_amount_cents);
    }

    /** LE TÉMOIN : « continuez » n'annule rien du tout. */
    public function test_continuer_n_annule_pas(): void
    {
        $this->seed(CancellationPoliciesSeeder::class);
        $this->seed(CancellationQuestionnaireSeeder::class);

        $mission = $this->mission();
        $revision = $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        $this->revisions()->refuser($revision, $this->client, MissionQuoteRevision::DECISION_POURSUIVRE);

        $this->assertSame(0, BookingCancellationV2::query()->where('booking_id', $mission->booking_id)->count());
    }

    public function test_un_tiers_ne_repond_pas_a_la_place_du_client(): void
    {
        $mission = $this->mission();
        $revision = $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        $this->expectExceptionMessage('ne vous concerne pas');
        $this->revisions()->refuser($revision, User::factory()->client()->create(), MissionQuoteRevision::DECISION_POURSUIVRE);
    }

    // ── LES API ───────────────────────────────────────────────────────────────

    public function test_le_prestataire_lit_sa_fenetre_et_simule_un_prix(): void
    {
        $mission = $this->mission();
        $this->remise($mission->booking, 'percent', 20);
        Sanctum::actingAs($this->prestataire);

        $this->getJson('/api/provider/missions/'.$mission->id.'/quote-revision')
            ->assertOk()
            ->assertJsonPath('window.open', true)
            ->assertJsonPath('revision', null);

        $this->postJson('/api/provider/missions/'.$mission->id.'/quote-revision/simulate', [
            'service_cents' => 30000,
        ])->assertOk()->assertJsonPath('quote.total_cents', 24000);
    }

    /** LE TÉMOIN de la garde : un prestataire étranger à la mission ne voit rien. */
    public function test_un_prestataire_etranger_est_refuse(): void
    {
        $mission = $this->mission();
        $autre = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $autre->id, 'status' => 'active']);
        Sanctum::actingAs($autre);

        $this->getJson('/api/provider/missions/'.$mission->id.'/quote-revision')->assertForbidden();
    }

    public function test_le_parcours_complet_par_l_api(): void
    {
        $mission = $this->mission();

        Sanctum::actingAs($this->prestataire);
        $reponse = $this->postJson('/api/provider/missions/'.$mission->id.'/quote-revision', [
            'service_cents' => 30000,
            'reason_text' => 'Deux cents mètres carrés, pas vingt.',
            'media_ids' => [1],
        ])->assertCreated()
            ->assertJsonPath('revision.revised_total_cents', 30000)
            ->assertJsonPath('revision.top_up_cents', 25000);

        $id = $reponse->json('revision.id');

        Sanctum::actingAs($this->client);
        $this->getJson('/api/client/bookings/'.$mission->booking_id.'/onsite/quote-revision')
            ->assertOk()
            ->assertJsonPath('revision.original_total', 50)
            ->assertJsonPath('revision.revised_total', 300)
            ->assertJsonPath('revision.awaiting_client', true);

        $this->postJson(
            '/api/client/bookings/'.$mission->booking_id.'/onsite/quote-revision/'.$id.'/decline',
            ['decision' => 'stop'],
        )->assertOk()->assertJsonPath('must_cancel', true);

        $this->assertSame(50.0, (float) $mission->booking->fresh()->devis_estime);
    }

    public function test_l_api_refuse_une_proposition_sans_preuve(): void
    {
        $mission = $this->mission();
        Sanctum::actingAs($this->prestataire);

        $this->postJson('/api/provider/missions/'.$mission->id.'/quote-revision', [
            'service_cents' => 30000,
            'reason_text' => 'Plus grand',
            'media_ids' => [],
        ])->assertStatus(422);
    }

    /** Un client tiers ne répond pas à la place du propriétaire. */
    public function test_un_client_tiers_ne_repond_pas(): void
    {
        $mission = $this->mission();
        $revision = $this->revisions()->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        Sanctum::actingAs(User::factory()->client()->create());

        $this->postJson(
            '/api/client/bookings/'.$mission->booking_id.'/onsite/quote-revision/'.$revision->id.'/decline',
            ['decision' => 'continue'],
        )->assertForbidden();
    }
}
