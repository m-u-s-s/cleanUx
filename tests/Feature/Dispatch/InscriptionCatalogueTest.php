<?php

namespace Tests\Feature\Dispatch;

use App\Enums\ProviderType;
use App\Livewire\Provider\TradesAndZones;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\Catalog\ProviderCoverageWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'INSCRIPTION VIENT DU CATALOGUE — et le dispatch ne sert que ce qui a été déclaré (consigne 9).
 *
 * Le lien manquait des deux côtés : l'inscription proposait des métiers sans filtre de zone et
 * AUCUNE zone, si bien qu'un prestataire déclarait « peinture » sans dire où. Le dispatch devait
 * alors deviner son périmètre, et un métier ouvert dans une nouvelle zone n'était déclarable par
 * personne sans déploiement.
 *
 * Les deux tables vérifiées ici — `trade_user` et `employee_zone_assignments` — sont EXACTEMENT
 * celles que lit la requête candidate. C'est ce qui rend la promesse tenable : cocher une case
 * change les offres reçues, tout de suite.
 */
class InscriptionCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $liege;

    private ServiceZone $bruxelles;

    private Trade $peinture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->liege = ServiceZone::create([
            'name' => 'Zone Liège', 'slug' => 'zone-liege-insc', 'code' => 'LIE-I',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->bruxelles = ServiceZone::create([
            'name' => 'Zone Bruxelles', 'slug' => 'zone-bxl-insc', 'code' => 'BXL-I',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $secteur = \App\Models\Sector::create([
            'name' => 'Bâtiment', 'slug' => 'batiment-insc', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->peinture = Trade::create([
            'sector_id' => $secteur->id,
            'slug' => 'peinture-insc', 'code' => 'PNT-I', 'name' => 'Peinture',
            'is_active' => true, 'sort_order' => 1, 'base_price_cents' => 5000,
        ]);

        TradeZonePricing::create([
            'trade_id' => $this->peinture->id,
            'service_zone_id' => $this->liege->id,
            'base_rate_cents' => 5000,
            'surge_multiplier' => '1.00',
            'is_active' => true,
        ]);
    }

    private function prestataire(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYE, 'is_active' => true]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user;
    }

    // ─── L'API partagée ──────────────────────────────────────────────────────────────────────

    #[Test]
    public function les_options_d_inscription_sont_publiques(): void
    {
        // Le formulaire les appelle AVANT que le compte existe, donc avant tout jeton : les mettre
        // derrière `auth:sanctum` viderait l'écran d'inscription.
        $this->getJson('/api/catalog/registration-options')->assertOk();
    }

    #[Test]
    public function un_metier_ajoute_au_catalogue_apparait_sans_deploiement(): void
    {
        $avant = $this->getJson('/api/catalog/registration-options')->json('data.sectors');
        $noms = collect($avant)->flatMap(fn ($s) => collect($s['trades'])->pluck('name'))->all();
        $this->assertContains('Peinture', $noms);

        $toiture = Trade::create([
            'sector_id' => $this->peinture->sector_id,
            'slug' => 'toiture-insc', 'code' => 'TOI-I', 'name' => 'Toiture',
            'is_active' => true, 'sort_order' => 2,
        ]);

        TradeZonePricing::create([
            'trade_id' => $toiture->id,
            'service_zone_id' => $this->liege->id,
            'base_rate_cents' => 9000,
            'surge_multiplier' => '1.00',
            'is_active' => true,
        ]);

        $apres = $this->getJson('/api/catalog/registration-options')->json('data.sectors');
        $nomsApres = collect($apres)->flatMap(fn ($s) => collect($s['trades'])->pluck('name'))->all();

        $this->assertContains('Toiture', $nomsApres);
    }

    #[Test]
    public function un_metier_vendu_nulle_part_n_est_pas_proposable(): void
    {
        Trade::create([
            'sector_id' => $this->peinture->sector_id,
            'slug' => 'elagage-insc', 'code' => 'ELA-I', 'name' => 'Élagage',
            'is_active' => true, 'sort_order' => 3,
        ]);

        $secteurs = $this->getJson('/api/catalog/registration-options')->json('data.sectors');
        $noms = collect($secteurs)->flatMap(fn ($s) => collect($s['trades'])->pluck('name'))->all();

        // Le proposer ferait déclarer une couverture qu'aucune zone ne peut servir : le prestataire
        // attendrait des offres qui ne viendraient jamais.
        $this->assertNotContains('Élagage', $noms);
    }

    // ─── L'écriture ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function la_declaration_ecrit_les_deux_tables_du_dispatch(): void
    {
        $prestataire = $this->prestataire();

        app(ProviderCoverageWriter::class)->sync(
            $prestataire,
            [$this->peinture->id],
            [$this->liege->id],
        );

        $this->assertDatabaseHas('trade_user', [
            'user_id' => $prestataire->id,
            'trade_id' => $this->peinture->id,
        ]);

        $this->assertDatabaseHas('employee_zone_assignments', [
            'user_id' => $prestataire->id,
            'service_zone_id' => $this->liege->id,
            'is_active' => true,
        ]);

        // `users.primary_service_zone_id` est lu par la requête candidate du planifié : le laisser
        // vide rendrait le prestataire invisible aux rendez-vous.
        $this->assertSame($this->liege->id, (int) $prestataire->fresh()->primary_service_zone_id);
    }

    #[Test]
    public function ajouter_une_zone_ouvre_immediatement_ce_perimetre(): void
    {
        $prestataire = $this->prestataire();
        $ecrivain = app(ProviderCoverageWriter::class);

        $ecrivain->sync($prestataire, [$this->peinture->id], [$this->liege->id]);
        $ecrivain->sync($prestataire->fresh(), [$this->peinture->id], [$this->liege->id, $this->bruxelles->id]);

        $zones = $prestataire->fresh()->zoneAssignments()->where('is_active', true)
            ->pluck('service_zone_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($this->bruxelles->id, $zones);
        $this->assertContains($this->liege->id, $zones);
    }

    #[Test]
    public function retirer_une_zone_la_desactive_sans_effacer_son_histoire(): void
    {
        $prestataire = $this->prestataire();
        $ecrivain = app(ProviderCoverageWriter::class);

        $ecrivain->sync($prestataire, [$this->peinture->id], [$this->liege->id, $this->bruxelles->id]);
        $ecrivain->sync($prestataire->fresh(), [$this->peinture->id], [$this->liege->id]);

        // Désactivée, PAS supprimée : son historique explique pourquoi une mission de l'an dernier
        // est partie là-bas.
        $this->assertDatabaseHas('employee_zone_assignments', [
            'user_id' => $prestataire->id,
            'service_zone_id' => $this->bruxelles->id,
            'is_active' => false,
        ]);
    }

    /**
     * UN MÉTIER ARCHIVÉ EST ÉCARTÉ ; un métier simplement pas encore vendu ne l'est PAS.
     *
     * La liste PROPOSÉE à l'inscription se limite aux métiers vendus quelque part — on ne met pas
     * en avant un service qu'aucune zone ne peut servir. Mais REFUSER une déclaration pour cette
     * raison effacerait le métier d'un prestataire dès qu'un administrateur ferme temporairement
     * une zone, et il cesserait de recevoir des missions sans que rien ne le lui dise.
     */
    #[Test]
    public function un_metier_archive_est_ecarte_a_l_ecriture(): void
    {
        $prestataire = $this->prestataire();

        $archive = Trade::create([
            'sector_id' => $this->peinture->sector_id,
            'slug' => 'archive-insc', 'code' => 'ARC-I', 'name' => 'Archivé',
            'is_active' => false, 'sort_order' => 9,
        ]);

        $pasEncoreVendu = Trade::create([
            'sector_id' => $this->peinture->sector_id,
            'slug' => 'a-venir-insc', 'code' => 'AVN-I', 'name' => 'À venir',
            'is_active' => true, 'sort_order' => 10,
        ]);

        // L'identifiant vient du navigateur : rien de ce qui arrive n'est cru sur parole.
        $ecrit = app(ProviderCoverageWriter::class)->sync(
            $prestataire,
            [$this->peinture->id, $archive->id, $pasEncoreVendu->id],
            [$this->liege->id],
        );

        $this->assertContains($this->peinture->id, $ecrit['trades']);
        $this->assertNotContains($archive->id, $ecrit['trades'], 'Un métier archivé n’existe plus.');
        $this->assertContains(
            $pasEncoreVendu->id,
            $ecrit['trades'],
            'Un métier pas encore vendu se déclare : il ne produira simplement aucune offre.',
        );
    }

    /**
     * UNE LISTE VIDE NE VIDE RIEN.
     *
     * `sync([])` effacerait la déclaration existante. Ce chemin est appelé par l'inscription, où
     * les métiers arrivent parfois par un autre champ : effacer en silence ce que l'utilisateur
     * vient de déclarer est le pire des comportements — aucune erreur, et il découvre des semaines
     * plus tard qu'il ne reçoit rien.
     */
    #[Test]
    public function une_liste_vide_n_efface_pas_la_declaration(): void
    {
        $prestataire = $this->prestataire();
        $ecrivain = app(ProviderCoverageWriter::class);

        $ecrivain->sync($prestataire, [$this->peinture->id], [$this->liege->id]);
        $ecrivain->sync($prestataire->fresh(), [], [$this->liege->id]);

        $this->assertTrue(
            $prestataire->fresh()->trades()->where('trades.id', $this->peinture->id)->exists(),
        );
    }

    // ─── L'écran ─────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function l_ecran_prestataire_liste_le_catalogue_et_enregistre(): void
    {
        $prestataire = $this->prestataire();

        Livewire::actingAs($prestataire)
            ->test(TradesAndZones::class)
            ->assertSee('Peinture')
            ->assertSee('Zone Liège')
            ->set('tradeIds', [$this->peinture->id])
            ->set('zoneIds', [$this->liege->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('trade_user', [
            'user_id' => $prestataire->id,
            'trade_id' => $this->peinture->id,
        ]);
    }

    #[Test]
    public function l_ecran_refuse_une_declaration_vide_en_disant_pourquoi(): void
    {
        $prestataire = $this->prestataire();

        Livewire::actingAs($prestataire)
            ->test(TradesAndZones::class)
            ->set('tradeIds', [])
            ->set('zoneIds', [$this->liege->id])
            ->call('save')
            ->assertHasErrors('tradeIds');
    }

    // ─── L'API prestataire ───────────────────────────────────────────────────────────────────

    #[Test]
    public function l_application_prestataire_lit_et_ecrit_sa_couverture(): void
    {
        $prestataire = $this->prestataire();

        $this->actingAs($prestataire, 'sanctum')
            ->putJson('/api/provider/coverage', [
                'trade_ids' => [$this->peinture->id],
                'zone_ids' => [$this->liege->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.trade_ids.0', $this->peinture->id);

        $this->actingAs($prestataire, 'sanctum')
            ->getJson('/api/provider/coverage')
            ->assertOk()
            ->assertJsonPath('data.zone_ids.0', $this->liege->id);
    }
}
