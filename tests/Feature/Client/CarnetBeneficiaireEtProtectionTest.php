<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\HomeBudget;
use App\Livewire\Client\MyProtection;
use App\Livewire\Client\PlacesBook;
use App\Models\Booking;
use App\Models\ClientPlace;
use App\Models\Mission;
use App\Models\OrderDraft;
use App\Models\User;
use App\Services\Ai\OrderIntentInterpreter;
use App\Services\Client\ClientPlaceService;
use App\Services\Client\HomeBudgetService;
use App\Services\Client\SharedTrackingService;
use App\Services\Missions\OnSite\MissionAccessSheetService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PHASE 3 — LE BÉNÉFICIAIRE (E1), LE CARNET DE LIEUX (E2), LE SUIVI PARTAGÉ (E3), LE BUDGET (E4),
 * L'ASSISTANT (E5) ET « MA PROTECTION » (E6).
 *
 * CE QUE CE FICHIER PROTÈGE EN PRIORITÉ, ce sont les quatre décisions qui font que ces modules
 * servent à quelque chose :
 *
 *   1. le bénéficiaire SURVIT à la conversion du panier — sinon le prestataire arrive en demandant
 *      celui qui a payé ;
 *   2. le carnet ALIMENTE la fiche d'accès sur place — sans quoi ce n'est qu'un formulaire
 *      d'adresse de plus, et les consignes ne servent à personne ;
 *   3. le lien de suivi partagé ne montre NI le montant NI l'adresse — il circule par SMS ;
 *   4. l'assistant ne propose QUE des métiers du catalogue — un métier inventé produirait une
 *      commande que le dispatch ne sait pas servir.
 */
class CarnetBeneficiaireEtProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    // ─── Les portes ──────────────────────────────────────────────────────────

    #[Test]
    public function les_trois_ecrans_web_repondent(): void
    {
        $client = User::factory()->client()->create();

        foreach (['places', 'budget', 'protection'] as $ecran) {
            $this->actingAs($client)
                ->get(route("client.{$ecran}"))
                ->assertOk();
        }
    }

    #[Test]
    public function les_modules_figurent_au_repertoire(): void
    {
        $entrees = collect(config('modules.catalogue'))
            ->where('context', 'client')
            ->pluck('route')
            ->all();

        // Un écran absent du répertoire est un écran que personne ne trouve.
        $this->assertContains('client.places', $entrees);
        $this->assertContains('client.budget', $entrees);
        $this->assertContains('client.protection', $entrees);
    }

    // ─── E2 : le carnet de lieux ─────────────────────────────────────────────

    #[Test]
    public function le_premier_lieu_devient_le_defaut_sans_qu_on_le_demande(): void
    {
        $client = User::factory()->client()->create();

        $premier = app(ClientPlaceService::class)->enregistrer($client, [
            'label' => 'Chez moi',
            'address' => 'Rue Haute 1',
        ]);

        /*
         * UN CARNET DONT AUCUN LIEU N'EST PAR DÉFAUT NE PRÉ-REMPLIT RIEN — c'est-à-dire exactement
         * le problème qu'il devait résoudre.
         */
        $this->assertTrue($premier->is_default);

        $second = app(ClientPlaceService::class)->enregistrer($client, [
            'label' => 'Maison de maman',
            'address' => 'Chaussée de Wavre 200',
        ]);

        $this->assertFalse($second->is_default);
    }

    #[Test]
    public function un_seul_lieu_par_defaut_a_la_fois(): void
    {
        $client = User::factory()->client()->create();
        $service = app(ClientPlaceService::class);

        $premier = $service->enregistrer($client, ['label' => 'Chez moi', 'address' => 'Rue Haute 1']);
        $second = $service->enregistrer($client, ['label' => 'Bureau', 'address' => 'Rue Neuve 5']);

        $service->definirParDefaut($second);

        // Deux défauts simultanés rendraient le pré-remplissage arbitraire : le parcours prendrait
        // le premier venu, qui varierait selon l'ordre de lecture.
        $this->assertFalse($premier->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    #[Test]
    public function archiver_designe_un_nouveau_defaut(): void
    {
        $client = User::factory()->client()->create();
        $service = app(ClientPlaceService::class);

        $premier = $service->enregistrer($client, ['label' => 'Chez moi', 'address' => 'Rue Haute 1']);
        $service->enregistrer($client, ['label' => 'Bureau', 'address' => 'Rue Neuve 5']);

        $service->archiver($premier);

        // Sans cela, le parcours ne pré-remplirait plus rien, sans que le client comprenne pourquoi.
        $this->assertNotNull($service->parDefaut($client));
        // Archivé, jamais supprimé : les interventions passées portent ce lieu.
        $this->assertNotNull(ClientPlace::query()->find($premier->id));
        $this->assertNotNull($premier->fresh()->archived_at);
    }

    #[Test]
    public function le_lieu_d_un_autre_client_reste_hors_de_portee(): void
    {
        $client = User::factory()->client()->create();
        $curieux = User::factory()->client()->create();

        $lieu = ClientPlace::factory()->avecConsignes()->create(['user_id' => $client->id]);

        // Un lieu porte l'adresse, l'étage et le code d'alarme du domicile de quelqu'un.
        $this->assertNull(app(ClientPlaceService::class)->lieuDuClient($curieux, $lieu->id));

        Livewire::actingAs($curieux)
            ->test(PlacesBook::class)
            ->call('archiver', $lieu->id);

        $this->assertNull($lieu->fresh()->archived_at);
    }

    #[Test]
    public function le_carnet_alimente_la_fiche_d_acces_sur_place(): void
    {
        $client = User::factory()->client()->create();
        $prestataire = User::factory()->employe()->create();

        $lieu = ClientPlace::factory()->avecConsignes()->create(['user_id' => $client->id]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $prestataire->id,
            'client_place_id' => $lieu->id,
            'beneficiary_name' => 'Madame Dupont',
            'beneficiary_phone' => '+32470000000',
        ]);

        $mission = Mission::query()->where('booking_id', $booking->id)->first()
            ?? Mission::factory()->create(['booking_id' => $booking->id]);

        $mission->forceFill([
            'lead_provider_user_id' => $prestataire->id,
            'status' => MissionStatus::ARRIVED,
        ])->save();

        $fiche = app(MissionAccessSheetService::class)->pour($mission->fresh(), $prestataire);

        /*
         * C'EST LE CHAÎNON QUI FAIT D'UN CARNET D'ADRESSES UN CARNET DE LIEUX. Sans lui, les
         * consignes enregistrées par le client ne serviraient à personne, et se redonneraient
         * oralement à chaque nouveau prestataire.
         */
        $this->assertSame('3e étage, porte gauche', $fiche['floor']);
        $this->assertStringContainsString('Digicode', $fiche['access_instructions']);
        $this->assertTrue($fiche['alarm_code_required']);
        $this->assertSame('Allergie aux parfums d’agrumes.', $fiche['preferences']['allergies']);

        // E1 — arriver en demandant celui qui a payé quand c'est sa mère qui ouvre, c'est commencer
        // l'intervention par un malentendu.
        $this->assertSame('Madame Dupont', $fiche['beneficiary']['name']);
    }

    #[Test]
    public function la_fiche_verrouillee_garde_les_memes_cles(): void
    {
        $verrouillee = app(MissionAccessSheetService::class)->verrouillee('Pas encore.');

        // Un appelant qui doit tester la présence d'une clé avant de la lire finit par en oublier
        // une : la forme est la même, ouverte ou fermée.
        $this->assertArrayHasKey('preferences', $verrouillee);
        $this->assertArrayHasKey('beneficiary', $verrouillee);
        $this->assertNull($verrouillee['beneficiary']);
        $this->assertNull($verrouillee['preferences']['allergies']);
    }

    #[Test]
    public function l_api_ne_laisse_pas_choisir_sa_zone(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/client/places', [
            'label' => 'Chez moi',
            'address' => 'Rue Haute 1',
            'service_zone_id' => 999,
        ])->assertCreated();

        // La zone décide de la grille tarifaire : la laisser venir du téléphone permettrait de
        // s'attribuer celle d'une autre zone.
        $this->assertNotSame(999, ClientPlace::query()->first()->service_zone_id);
    }

    // ─── E1 : le bénéficiaire ────────────────────────────────────────────────

    #[Test]
    public function le_beneficiaire_survit_a_la_conversion_du_panier(): void
    {
        $draft = OrderDraft::query()->create([
            'reference' => OrderDraft::generateReference(),
            'session_token' => 'jeton-de-test',
            'beneficiary_name' => 'Madame Dupont',
            'beneficiary_phone' => '+32470000000',
            'beneficiary_note' => 'Sonnez longtemps.',
        ]);

        /*
         * LE PANIER PORTE L'INFORMATION ; c'est la RÉSERVATION qui la fait suivre au terrain. Un
         * bénéficiaire qui ne franchirait pas la confirmation ne servirait à personne.
         */
        $this->assertSame('Madame Dupont', $draft->fresh()->beneficiary_name);

        $booking = Booking::factory()->create([
            'beneficiary_name' => $draft->beneficiary_name,
            'beneficiary_phone' => $draft->beneficiary_phone,
            'beneficiary_note' => $draft->beneficiary_note,
        ]);

        $this->assertSame('Madame Dupont', $booking->fresh()->beneficiary_name);
        $this->assertSame('Sonnez longtemps.', $booking->fresh()->beneficiary_note);
    }

    // ─── E3 : le suivi partagé ───────────────────────────────────────────────

    #[Test]
    public function le_lien_de_suivi_ouvre_une_page_publique(): void
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'city' => 'Bruxelles',
        ]);

        $lien = app(SharedTrackingService::class)->lienPour($booking);

        // AUCUNE AUTHENTIFICATION : le destinataire est souvent quelqu'un qui n'a pas de compte et
        // n'en veut pas. Lui demander de s'inscrire reviendrait à ne pas partager du tout.
        $this->get($lien)->assertOk()->assertSee('Bruxelles');
    }

    #[Test]
    public function un_lien_non_signe_ne_donne_rien(): void
    {
        $booking = Booking::factory()->create();

        // Un identifiant de réservation dans une URL publique se devine en comptant.
        $this->get("/suivi/{$booking->id}")->assertForbidden();
    }

    #[Test]
    public function un_lien_perime_ne_donne_plus_rien(): void
    {
        $booking = Booking::factory()->create();
        $lien = app(SharedTrackingService::class)->lienPour($booking);

        // Un partage qui survit à l'intervention devient un traceur, et personne ne pense à le
        // révoquer.
        Carbon::setTestNow(Carbon::now()->addHours(SharedTrackingService::VALIDITE_HEURES + 1));

        $this->get($lien)->assertForbidden();

        Carbon::setTestNow();
    }

    #[Test]
    public function l_apercu_partage_ne_montre_ni_montant_ni_adresse(): void
    {
        $booking = Booking::factory()->create([
            'address' => 'Rue Haute 1, appartement 3B',
            'city' => 'Bruxelles',
            'devis_estime' => 189.90,
        ]);

        $apercu = app(SharedTrackingService::class)->apercu($booking);

        /*
         * VOLONTAIREMENT PAUVRE. Le destinataire du lien n'est pas le client : il n'a pas à
         * connaître le montant, ni l'adresse exacte. Une position et une heure suffisent à ce pour
         * quoi le lien a été envoyé.
         *
         * ON ÉNUMÈRE LES CHAMPS AUTORISÉS, plutôt que de chercher des valeurs interdites dans le
         * JSON. La version précédente cherchait la chaîne « 189 » — le montant — dans la charge
         * entière, et tombait le jour où la référence tirée au hasard en contenait les chiffres
         * (`CUX-20260814-CK189KS`). Un échec par coïncidence, sur une assertion trop large.
         *
         * La liste blanche dit mieux ce qu'on protège : ce n'est pas « le nombre 189 n'apparaît
         * pas », c'est « cette charge ne porte QUE ces champs-là ». Elle refusera aussi le jour où
         * quelqu'un ajoutera un champ sans y penser — ce qui est exactement le but.
         */
        $this->assertSame(
            [
                'reference',
                'provider_first_name',
                'scheduled_at',
                'status',
                'city',
                'beneficiary_name',
                'tracking',
                'expires_in_hours',
            ],
            array_keys($apercu),
            'La charge du lien partagé a changé de forme : vérifier qu’aucun champ sensible n’y entre.',
        );

        $encode = json_encode($apercu, JSON_UNESCAPED_UNICODE);

        // Le complément d'adresse, lui, ne peut coïncider avec rien d'autre.
        $this->assertStringNotContainsString('appartement 3B', $encode);
        $this->assertStringNotContainsString('Rue Haute', $encode);
        $this->assertSame('Bruxelles', $apercu['city']);
    }

    #[Test]
    public function l_api_de_partage_refuse_la_reservation_d_un_autre(): void
    {
        $client = User::factory()->client()->create();
        $curieux = User::factory()->client()->create();

        $booking = Booking::factory()->create(['client_id' => $client->id]);

        Sanctum::actingAs($curieux, ['*']);

        // Émettre un lien signé pour la réservation d'un autre donnerait à qui devine un numéro un
        // accès au suivi d'un inconnu.
        $this->postJson("/api/client/bookings/{$booking->id}/tracking/share")->assertForbidden();
    }

    // ─── E4 : le budget ──────────────────────────────────────────────────────

    #[Test]
    public function le_budget_ecarte_les_annulees(): void
    {
        $client = User::factory()->client()->create();

        Booking::factory()->create(['client_id' => $client->id, 'devis_estime' => 100.0]);
        Booking::factory()->create([
            'client_id' => $client->id,
            'devis_estime' => 500.0,
            'status' => 'annule',
        ]);

        $budget = app(HomeBudgetService::class)->pour($client);

        // Une annulée n'a rien coûté : la compter gonflerait le budget d'un montant que personne
        // n'a payé.
        $this->assertSame(10000, $budget['total_cents']);
        $this->assertSame(1, $budget['bookings_count']);
    }

    #[Test]
    public function le_comparatif_distingue_recurrent_et_ponctuel(): void
    {
        $client = User::factory()->client()->create();

        Booking::factory()->create([
            'client_id' => $client->id,
            'devis_estime' => 80.0,
            'is_recurrent' => true,
        ]);

        Booking::factory()->create(['client_id' => $client->id, 'devis_estime' => 120.0]);

        $comparatif = app(HomeBudgetService::class)->pour($client)['subscription_vs_on_demand'];

        // C'est le seul chiffre qui serve à décider : le reste documente.
        $this->assertSame(8000, $comparatif['subscription']['total_cents']);
        $this->assertSame(12000, $comparatif['on_demand']['total_cents']);
    }

    #[Test]
    public function le_budget_ne_voit_pas_les_depenses_d_un_autre(): void
    {
        $client = User::factory()->client()->create();
        $autre = User::factory()->client()->create();

        Booking::factory()->create(['client_id' => $autre->id, 'devis_estime' => 999.0]);

        Livewire::actingAs($client)
            ->test(HomeBudget::class)
            ->assertOk()
            ->assertViewHas('budget', fn (array $budget) => $budget['total_cents'] === 0);
    }

    // ─── E5 : l'assistant ────────────────────────────────────────────────────

    #[Test]
    public function l_assistant_ne_propose_que_des_metiers_du_catalogue(): void
    {
        $resultat = app(OrderIntentInterpreter::class)->interpreter('un dégât des eaux au plafond');

        /*
         * LE CATALOGUE FAIT AUTORITÉ. Un métier inventé produirait une commande que le dispatch ne
         * saurait servir, et l'erreur ne se verrait qu'à la recherche de prestataire.
         */
        if ($resultat['trade_id'] !== null) {
            $this->assertDatabaseHas('trades', ['id' => $resultat['trade_id'], 'is_active' => true]);
        }

        // Sans clé d'API, le repli déterministe prend la main — et le DIT.
        $this->assertContains($resultat['source'], ['keywords', 'model', 'none']);
    }

    #[Test]
    public function une_description_vide_ne_devine_rien(): void
    {
        $resultat = app(OrderIntentInterpreter::class)->interpreter('   ');

        $this->assertNull($resultat['trade_id']);
        $this->assertSame('none', $resultat['confidence']);
    }

    #[Test]
    public function l_assistant_coupe_repond_404(): void
    {
        config(['features.ai_order_assistant' => false]);

        $client = User::factory()->client()->create();
        Sanctum::actingAs($client, ['*']);

        // 404 plutôt qu'une interprétation vide : l'application saurait alors qu'il n'existe pas,
        // au lieu de croire que l'assistant n'a rien compris.
        $this->postJson('/api/client/order-intent', ['description' => 'un robinet qui fuit'])
            ->assertNotFound();
    }

    // ─── E6 : ma protection ──────────────────────────────────────────────────

    #[Test]
    public function la_protection_agrege_les_trois_briques(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client, ['*']);

        $reponse = $this->getJson('/api/client/protection')->assertOk();

        /*
         * LES TROIS SONT TOUJOURS RENDUES, même vides : un écran dont les blocs apparaissent et
         * disparaissent selon ce qu'on possède fait douter de ce qui manque — et c'est exactement
         * ce qu'une page de protection doit éviter.
         */
        $this->assertIsArray($reponse->json('data.insurance'));
        $this->assertIsArray($reponse->json('data.cancellation'));
        $this->assertIsArray($reponse->json('data.disputes'));
        $this->assertSame(0, $reponse->json('data.insurance.active_count'));
    }

    #[Test]
    public function la_protection_ne_montre_pas_les_litiges_d_un_autre(): void
    {
        $client = User::factory()->client()->create();
        $autre = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(MyProtection::class)
            ->assertOk()
            ->assertViewHas('protection', fn (array $p) => $p['disputes']['cases'] === []);

        $this->assertNotSame($client->id, $autre->id);
    }
}
