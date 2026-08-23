<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Livewire\Client\ReceivedQuotes;
use App\Livewire\ProviderCompany\QualityAndFleet;
use App\Livewire\ProviderCompany\QuoteBuilder;
use App\Livewire\ProviderCompany\RecruitmentCenter;
use App\Models\Booking;
use App\Models\FleetCertification;
use App\Models\FleetVehicle;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\OrganizationAccount;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\ProviderQuote;
use App\Models\Trade;
use App\Models\User;
use App\Services\FleetV2\ProviderFleetService;
use App\Services\Quotes\ProviderQuoteService;
use App\Services\Recruitment\RecruitmentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** PHASE 2, SECONDE MOITIÉ — LE DEVIS SOCIÉTÉ (E24), LE RECRUTEMENT (E25), LE SCORE QUALITÉ (E26) ET LA FLOTTE (E27). */
class DevisRecrutementQualiteFlotteTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $org;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();

        $this->org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function membre(OrganizationRole $role, ?OrganizationAccount $org = null): User
    {
        $org ??= $this->org;

        $user = User::factory()->employe()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    // ─── Les portes ──────────────────────────────────────────────────────────

    #[Test]
    public function les_trois_ecrans_web_repondent(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        foreach (['quotes', 'recruitment', 'quality-fleet'] as $ecran) {
            $this->actingAs($patron)
                ->get(route("provider-company.{$ecran}"))
                ->assertOk();
        }
    }

    #[Test]
    public function les_modules_figurent_au_repertoire(): void
    {
        $entrees = collect(config('modules.catalogue'))
            ->where('context', 'provider-company')
            ->pluck('route')
            ->all();

        // Un écran absent du répertoire est un écran que personne ne trouve.
        $this->assertContains('provider-company.quotes', $entrees);
        $this->assertContains('provider-company.recruitment', $entrees);
        $this->assertContains('provider-company.quality-fleet', $entrees);
    }

    // ─── E24 : le devis ──────────────────────────────────────────────────────

    #[Test]
    public function accepter_un_devis_cree_les_missions(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $client = User::factory()->create();
        $metier = Trade::factory()->create();

        $service = app(ProviderQuoteService::class);

        $devis = $service->ouvrirUnBrouillon($this->org->id, $patron, 'Remise en état', $client->id);
        $service->ajouterUneLigne($devis, $metier->id, 'Nettoyage des communs', 1, 25000);
        $service->ajouterUneLigne($devis, $metier->id, 'Vitrerie', 2, 8000);
        $service->envoyer($devis);

        $this->assertSame(41000, $devis->fresh()->total_cents);

        $service->accepter($devis->fresh(), $client);

        // C'EST TOUT L'INTÉRÊT.
        $this->assertSame(2, Booking::query()->where('client_id', $client->id)->count());

        $this->assertDatabaseHas('bookings', [
            'client_id' => $client->id,
            'trade_id' => $metier->id,
            'assigned_provider_organization_id' => $this->org->id,
        ]);
    }

    #[Test]
    public function un_devis_envoye_ne_se_modifie_plus(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $client = User::factory()->create();
        $metier = Trade::factory()->create();

        $service = app(ProviderQuoteService::class);
        $devis = $service->ouvrirUnBrouillon($this->org->id, $patron, 'Chantier', $client->id);
        $service->ajouterUneLigne($devis, $metier->id, 'Peinture', 1, 50000);
        $service->envoyer($devis);

        // Le corriger après coup ferait diverger ce que le client a reçu de ce qu'il accepte — et
        // c'est le montant REÇU qui fait foi dans une conversation commerciale.
        $this->expectException(DomainException::class);

        $service->ajouterUneLigne($devis->fresh(), $metier->id, 'Ligne ajoutée après coup', 1, 10000);
    }

    #[Test]
    public function un_devis_perime_ne_s_accepte_pas(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $client = User::factory()->create();
        $metier = Trade::factory()->create();

        $service = app(ProviderQuoteService::class);
        $devis = $service->ouvrirUnBrouillon($this->org->id, $patron, 'Chantier', $client->id);
        $service->ajouterUneLigne($devis, $metier->id, 'Peinture', 1, 50000);
        $service->envoyer($devis);

        $devis->forceFill(['valid_until' => Carbon::now()->subDay()->toDateString()])->save();

        // L'ÉCHÉANCE COMPTE MÊME SI LE BALAYAGE N'EST PAS PASSÉ.
        $this->expectException(DomainException::class);

        $service->accepter($devis->fresh(), $client);
    }

    #[Test]
    public function un_devis_ne_s_accepte_que_par_son_destinataire(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $client = User::factory()->create();
        $curieux = User::factory()->create();
        $metier = Trade::factory()->create();

        $service = app(ProviderQuoteService::class);
        $devis = $service->ouvrirUnBrouillon($this->org->id, $patron, 'Chantier', $client->id);
        $service->ajouterUneLigne($devis, $metier->id, 'Peinture', 1, 50000);
        $service->envoyer($devis);

        // Un devis porte le nom du client, son adresse et ce qu'il paye.
        Livewire::actingAs($curieux)
            ->test(ReceivedQuotes::class)
            ->call('accepter', $devis->id);

        $this->assertSame(ProviderQuote::STATUS_SENT, $devis->fresh()->status);
    }

    #[Test]
    public function le_client_voit_et_accepte_son_devis_depuis_le_web(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $client = User::factory()->create();
        $metier = Trade::factory()->create();

        $service = app(ProviderQuoteService::class);
        $devis = $service->ouvrirUnBrouillon($this->org->id, $patron, 'Remise en état', $client->id);
        $service->ajouterUneLigne($devis, $metier->id, 'Nettoyage', 1, 25000);
        $service->envoyer($devis);

        Livewire::actingAs($client)
            ->test(ReceivedQuotes::class)
            ->call('accepter', $devis->id);

        $this->assertSame(ProviderQuote::STATUS_ACCEPTED, $devis->fresh()->status);
    }

    #[Test]
    public function un_executant_ne_chiffre_pas(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);

        // `quotes.view` n'est accordée qu'aux rôles qui décident : un devis engage un prix, et un
        // prix engage la marge de toute l'équipe qui l'exécutera.
        $this->actingAs($ouvrier)
            ->get(route('provider-company.quotes'))
            ->assertForbidden();
    }

    #[Test]
    public function l_api_societe_chiffre_et_envoie(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $client = User::factory()->create();
        $metier = Trade::factory()->create();

        Sanctum::actingAs($patron, ['*']);

        $devisId = $this->postJson('/api/provider/company/quotes', [
            'title' => 'Visite du 12',
            'client_user_id' => $client->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/provider/company/quotes/{$devisId}/lines", [
            'trade_id' => $metier->id,
            'label' => 'Nettoyage de fin de chantier',
            'quantity' => 1,
            'unit_price_cents' => 30000,
        ])->assertCreated()->assertJsonPath('data.quote_total_cents', 30000);

        $this->postJson("/api/provider/company/quotes/{$devisId}/send")
            ->assertOk()
            ->assertJsonPath('data.status', ProviderQuote::STATUS_SENT)
            ->assertJsonPath('data.is_open', true);
    }

    #[Test]
    public function l_api_client_accepte_et_dit_combien_de_rendez_vous(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $client = User::factory()->create();
        $metier = Trade::factory()->create();

        $service = app(ProviderQuoteService::class);
        $devis = $service->ouvrirUnBrouillon($this->org->id, $patron, 'Chantier', $client->id);
        $service->ajouterUneLigne($devis, $metier->id, 'Peinture', 1, 50000);
        $service->envoyer($devis);

        Sanctum::actingAs($client, ['*']);

        // Accepter CRÉE le travail : le dire évite que le client croie n'avoir signé qu'un papier.
        $this->postJson("/api/client/quotes/{$devis->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', ProviderQuote::STATUS_ACCEPTED)
            ->assertJsonPath('data.bookings_created', 1);
    }

    #[Test]
    public function l_api_client_ne_montre_pas_le_devis_d_un_autre(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        $client = User::factory()->create();
        $curieux = User::factory()->create();
        $metier = Trade::factory()->create();

        $service = app(ProviderQuoteService::class);
        $devis = $service->ouvrirUnBrouillon($this->org->id, $patron, 'Chantier', $client->id);
        $service->ajouterUneLigne($devis, $metier->id, 'Peinture', 1, 50000);
        $service->envoyer($devis);

        Sanctum::actingAs($curieux, ['*']);

        $this->getJson('/api/client/quotes')->assertOk()->assertJsonCount(0, 'data');
        // 404 et non 403 : la différence dirait à un appelant que le devis existe.
        $this->getJson("/api/client/quotes/{$devis->id}")->assertNotFound();
    }

    // ─── E25 : le recrutement ────────────────────────────────────────────────

    #[Test]
    public function embaucher_emet_l_invitation(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $service = app(RecruitmentService::class);
        $offre = $service->ouvrirUneOffre($this->org->id, $patron, 'Agent d’entretien');
        $service->publier($offre);

        $candidature = $service->postuler($offre->fresh(), 'Nadia B.', 'nadia@exemple.test');

        $candidature = $service->embaucher($candidature, $patron);

        // UN MÊME GESTE, PAS DEUX ÉCRANS.
        $this->assertSame(JobApplication::STATUS_HIRED, $candidature->status);
        $this->assertNotNull($candidature->organization_invitation_id);

        $this->assertDatabaseHas('organization_invitations', [
            'organization_account_id' => $this->org->id,
            'email' => 'nadia@exemple.test',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function embaucher_deux_fois_n_emet_pas_deux_jetons(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $service = app(RecruitmentService::class);
        $offre = $service->publier($service->ouvrirUneOffre($this->org->id, $patron, 'Agent'));
        $candidature = $service->postuler($offre, 'Nadia B.', 'nadia@exemple.test');

        $service->embaucher($candidature, $patron);
        $service->embaucher($candidature->fresh(), $patron);

        // Un double clic ne doit pas envoyer deux invitations à la même personne.
        $this->assertSame(1, OrganizationInvitation::query()
            ->where('email', 'nadia@exemple.test')->count());
    }

    #[Test]
    public function on_ne_postule_pas_a_un_brouillon(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $offre = app(RecruitmentService::class)->ouvrirUneOffre($this->org->id, $patron, 'Agent');

        // Un brouillon ferait répondre à une annonce que la société n'a pas fini d'écrire.
        $this->expectException(DomainException::class);

        app(RecruitmentService::class)->postuler($offre, 'Nadia', 'nadia@exemple.test');
    }

    #[Test]
    public function une_seconde_candidature_ne_produit_pas_de_doublon(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $service = app(RecruitmentService::class);
        $offre = $service->publier($service->ouvrirUneOffre($this->org->id, $patron, 'Agent'));

        $premiere = $service->postuler($offre, 'Nadia', 'Nadia@Exemple.test');
        $seconde = $service->postuler($offre, 'Nadia', 'nadia@exemple.test');

        // Un double clic produirait sinon deux candidatures que le tri devrait départager à la main.
        $this->assertSame($premiere->id, $seconde->id);
        $this->assertSame(1, JobApplication::query()->count());
    }

    #[Test]
    public function la_candidature_d_une_autre_societe_reste_hors_de_portee(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $autreSociete = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
        ]);
        $etranger = $this->membre(OrganizationRole::OWNER, $autreSociete);

        $service = app(RecruitmentService::class);
        $offre = $service->publier($service->ouvrirUneOffre($autreSociete->id, $etranger, 'Agent'));
        $candidature = $service->postuler($offre, 'Nadia', 'nadia@exemple.test');

        Livewire::actingAs($patron)
            ->test(RecruitmentCenter::class)
            ->call('statuerSurLaCandidature', $candidature->id, 'hire');

        // Une candidature ne porte pas d'organisation : charger par son seul identifiant exposerait des données personnelles de gens qui n'ont postulé nulle part ici.
        $this->assertSame(JobApplication::STATUS_RECEIVED, $candidature->fresh()->status);
    }

    #[Test]
    public function l_api_trie_les_candidatures(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $service = app(RecruitmentService::class);
        $offre = $service->publier($service->ouvrirUneOffre($this->org->id, $patron, 'Agent'));
        $candidature = $service->postuler($offre, 'Nadia', 'nadia@exemple.test');

        Sanctum::actingAs($patron, ['*']);

        $this->getJson("/api/provider/company/job-postings/{$offre->id}/applications")
            ->assertOk()
            ->assertJsonPath('data.0.invited', false);

        $this->postJson("/api/provider/company/job-applications/{$candidature->id}/decision", [
            'decision' => 'hire',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', JobApplication::STATUS_HIRED)
            ->assertJsonPath('data.invited', true);
    }

    // ─── E26 : le score qualité ──────────────────────────────────────────────

    #[Test]
    public function un_score_sans_matiere_ne_se_fabrique_pas(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);
        Sanctum::actingAs($patron, ['*']);

        $reponse = $this->getJson('/api/provider/company/quality-scores')->assertOk();

        // Trois missions ne disent rien de personne.
        $this->assertFalse($reponse->json('data.0.has_enough_data'));
        $this->assertNull($reponse->json('data.0.score'));
        $this->assertSame(3, $reponse->json('meta.missions_minimum'));
    }

    #[Test]
    public function le_score_ne_sort_pas_de_la_societe(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);
        Sanctum::actingAs($ouvrier, ['*']);

        // Il sert à repérer qui a besoin d'aide, pas à classer : `missions.quality` le dit.
        $this->getJson('/api/provider/company/quality-scores')->assertForbidden();
    }

    // ─── E27 : la flotte ─────────────────────────────────────────────────────

    #[Test]
    public function une_societe_ne_voit_que_sa_flotte(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $autreSociete = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
        ]);

        $service = app(ProviderFleetService::class);
        $service->declarerUnVehicule($this->org->id, ['plate' => '1-ABC-123']);
        $service->declarerUnVehicule($autreSociete->id, ['plate' => '2-XYZ-999']);

        // Un véhicule de la PLATEFORME : la colonne est nullable, et `null` veut dire « à la
        // plateforme » — l'état de chaque ligne existante avant cette migration.
        FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '3-PLA-000',
            'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);

        Sanctum::actingAs($patron, ['*']);

        $reponse = $this->getJson('/api/provider/company/fleet')->assertOk();

        $plaques = collect($reponse->json('vehicles'))->pluck('plate')->all();

        $this->assertSame(['1-ABC-123'], $plaques);
    }

    #[Test]
    public function les_echeances_se_disent_avant_l_expiration(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $vehicule = app(ProviderFleetService::class)
            ->declarerUnVehicule($this->org->id, ['plate' => '1-ABC-123']);

        FleetCertification::query()->create([
            'subject_type' => FleetCertification::SUBJECT_VEHICLE,
            'subject_id' => $vehicule->id,
            'certification_type' => 'controle_technique',
            'expires_at' => Carbon::now()->addDays(10)->toDateString(),
            'status' => FleetCertification::STATUS_ACTIVE,
        ]);

        // Une certification qui expire dans dix mois n'a rien à annoncer : la signaler noierait
        // celle qui expire dans dix jours.
        FleetCertification::query()->create([
            'subject_type' => FleetCertification::SUBJECT_VEHICLE,
            'subject_id' => $vehicule->id,
            'certification_type' => 'assurance',
            'expires_at' => Carbon::now()->addMonths(10)->toDateString(),
            'status' => FleetCertification::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($patron, ['*']);

        $reponse = $this->getJson('/api/provider/company/fleet')->assertOk();

        // DÉCOUVRIR L'EXPIRATION QUAND LE MOTEUR REFUSE L'ASSIGNATION, c'est la découvrir trop tard : le blocage sur certification expirée fonctionne déjà, et la société ne comprenait pas pourquoi son camion n'était plus assignable.
        $this->assertCount(1, $reponse->json('expiring'));
        $this->assertSame('controle_technique', $reponse->json('expiring.0.certification_type'));
    }

    #[Test]
    public function un_executant_ne_declare_pas_de_vehicule(): void
    {
        $ouvrier = $this->membre(OrganizationRole::WORKER);
        Sanctum::actingAs($ouvrier, ['*']);

        $this->postJson('/api/provider/company/fleet/vehicles', ['plate' => '1-ABC-123'])
            ->assertForbidden();

        $this->assertSame(0, FleetVehicle::query()->count());
    }

    #[Test]
    public function l_ecran_qualite_materiel_s_ouvre_par_deux_portes(): void
    {
        // Le responsable qualité entre par `missions.quality` sans avoir `fleet.manage` ; le
        // répartiteur par `fleet.view` sans avoir `missions.quality`. Exiger les deux fermerait
        // l'écran à chacun.
        $qualite = $this->membre(OrganizationRole::QUALITY_MANAGER);
        $repartiteur = $this->membre(OrganizationRole::DISPATCHER);

        Livewire::actingAs($qualite)
            ->test(QualityAndFleet::class)
            ->assertOk()
            ->assertViewHas('peutVoirLaQualite', true);

        Livewire::actingAs($repartiteur)
            ->test(QualityAndFleet::class)
            ->assertOk()
            ->assertViewHas('peutVoirLaFlotte', true);
    }

    #[Test]
    public function le_constructeur_de_devis_ne_liste_que_ses_propres_clients(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $sien = User::factory()->create();
        $etranger = User::factory()->create();

        Booking::factory()->create([
            'client_id' => $sien->id,
            'assigned_provider_organization_id' => $this->org->id,
        ]);

        Booking::factory()->create(['client_id' => $etranger->id]);

        // OFFRIR LA LISTE COMPLÈTE DES CLIENTS ferait de cet écran un annuaire : n'importe quelle société pourrait énumérer la clientèle de ses concurrentes, et adresser un devis à quelqu'un qui n'a jamais entendu parler d'elle.
        Livewire::actingAs($patron)
            ->test(QuoteBuilder::class)
            ->assertViewHas('clients', fn ($clients) => $clients->pluck('id')->all() === [$sien->id]);
    }

    #[Test]
    public function une_offre_fermee_n_accepte_plus_de_candidature(): void
    {
        $patron = $this->membre(OrganizationRole::OWNER);

        $service = app(RecruitmentService::class);
        $offre = $service->publier($service->ouvrirUneOffre($this->org->id, $patron, 'Agent'));
        $offre = $service->fermer($offre);

        // Une offre fermée qui accepte encore laisse quelqu'un attendre une réponse qui ne viendra
        // jamais. Et elle CONSERVE ses candidatures : c'est souvent dans ce vivier qu'on repêche.
        $this->assertSame(JobPosting::STATUS_CLOSED, $offre->status);

        $this->expectException(DomainException::class);

        $service->postuler($offre, 'Nadia', 'nadia@exemple.test');
    }
}
