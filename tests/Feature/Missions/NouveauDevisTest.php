<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklistItem;
use App\Models\MissionMedia;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\MissionFeatureSuspension;
use App\Models\MissionQuoteRevision;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\MissionQuoteRevisionService;
use App\Services\Missions\MissionTodoService;
use App\Services\Missions\OnSite\MissionChecklistService as OnSiteChecklistService;
use App\Services\Missions\QuoteRevisionPricing;
use App\Services\Missions\QuoteRevisionWindow;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LE NOUVEAU DEVIS — et surtout la fenêtre qui empêche d'en faire une arme.
 *
 * La règle du porteur : la révision se fait AU DÉBUT, avant que le prestataire ne touche à quoi que
 * ce soit. Un imprévu découvert en travaillant passe par le supplément, pas par ici.
 *
 * Trois faits mesurables ferment la fenêtre, et aucun n'est déclaratif : une tâche cochée, une
 * photo « après », l'échéance. La photo « avant » ne ferme rien — elle se prend justement pour
 * constater l'écart.
 */
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

    /**
     * LA SYMÉTRIE, et c'est la garde la plus importante du module.
     *
     * Sans elle, un client ajoute trois tâches lourdes à la minute 25 — quand plus rien n'est
     * révisable — et la règle anti-abus prestataire devient une arme entre ses mains.
     */
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

    /**
     * UNE REMISE AU POURCENTAGE GRANDIT AVEC LE PRIX — c'est le terme même du code, et c'est en
     * faveur du client, qui n'a pas demandé cette augmentation.
     */
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
}
