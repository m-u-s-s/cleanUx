<?php

namespace Tests\Feature\Cerveau;

use App\Livewire\Admin\LeCerveau;
use App\Models\Booking;
use App\Models\MarketingCampaign;
use App\Models\Referral;
use App\Models\RiskHold;
use App\Models\User;
use App\Services\Cerveau\Cerveau;
use App\Services\Cerveau\RegistreDesGestes;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE CERVEAU CONSEILLE, ET N'AGIT QUE SUR AUTORISATION.
 *
 * Deux choses se prouvent ici, et la seconde compte autant que la première :
 *   — il VOIT les motifs (sinon il ne sert à rien) ;
 *   — il n'agit JAMAIS seul, et jamais sur l'argent.
 *
 * Chaque refus porte son témoin : un garde qui passerait au vert parce que l'analyse est cassée
 * ne mesurerait rien.
 */
class LeCerveauConseilleEtAgitTest extends TestCase
{
    use RefreshDatabase;

    // ── La porte ───────────────────────────────────────────────────────────

    public function test_l_ecran_est_reserve_au_titulaire_du_siege(): void
    {
        $admin = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => array_keys(User::allowedAdminPermissions()),
        ]);

        Livewire::actingAs($admin)->test(LeCerveau::class)->assertForbidden();
    }

    /** TÉMOIN — le titulaire, lui, entre. */
    public function test_temoin_le_titulaire_entre(): void
    {
        $this->actingAs($this->titulaire())
            ->get(route('admin.cerveau'))
            ->assertOk()
            ->assertSee('Le cerveau');
    }

    /** UN GESTE NE S'APPLIQUE PAS SANS LE SIÈGE — la garde est dans le service, pas l'écran. */
    public function test_un_administrateur_ordinaire_n_applique_aucun_geste(): void
    {
        $admin = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => array_keys(User::allowedAdminPermissions()),
        ]);

        $this->expectException(DomainException::class);

        app(RegistreDesGestes::class)->appliquer($admin, RegistreDesGestes::SUSPENDRE_CAMPAGNE, ['id' => 1]);
    }

    /** UN GESTE INCONNU NE S'INVENTE PAS : le registre est fermé. */
    public function test_un_geste_inconnu_est_refuse(): void
    {
        $this->expectException(DomainException::class);

        app(RegistreDesGestes::class)->appliquer($this->titulaire(), 'tout.effacer', []);
    }

    // ── Ce que le cerveau ne fera jamais ───────────────────────────────────

    /**
     * AUCUN GESTE NE SORT D'ARGENT.
     *
     * C'est la règle qui protège le plus : une automatisation qui rembourse finit par rembourser
     * une fois de trop, et un remboursement rendu à tort ne se reprend pas.
     */
    public function test_aucun_geste_ne_touche_a_l_argent(): void
    {
        $interdits = ['rembours', 'refund', 'payout', 'virement', 'capture', 'transfer'];

        foreach (app(RegistreDesGestes::class)->tous() as $cle => $geste) {
            foreach ($interdits as $mot) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $mot,
                    $cle,
                    "Le geste {$cle} touche à l’argent : le registre ne doit en contenir aucun.",
                );
            }
        }
    }

    /** CHAQUE GESTE DIT CE QU'IL FAIT, CE QU'IL IMPLIQUE, ET S'IL EST RÉVERSIBLE. */
    public function test_chaque_geste_s_explique_avant_d_etre_applique(): void
    {
        foreach (app(RegistreDesGestes::class)->tous() as $cle => $geste) {
            $this->assertNotEmpty($geste->fait, "Le geste {$cle} ne dit pas ce qu’il fait.");
            $this->assertNotEmpty($geste->implique, "Le geste {$cle} ne dit pas ce qu’il implique.");
            $this->assertNotEmpty($geste->libelle, "Le geste {$cle} n’a pas de libellé.");
        }
    }

    // ── Marketing ──────────────────────────────────────────────────────────

    public function test_il_signale_une_campagne_qui_tourne_depuis_trop_longtemps(): void
    {
        $this->campagne('Relance éternelle', 120);

        $titres = array_map(
            fn ($r) => $r->titre,
            app(Cerveau::class)->recommandations('marketing'),
        );

        $this->assertNotEmpty(array_filter($titres, fn ($t) => str_contains($t, 'Relance éternelle')));
    }

    /** TÉMOIN — une campagne récente ne déclenche rien. */
    public function test_temoin_une_campagne_recente_ne_declenche_rien(): void
    {
        $this->campagne('Campagne du mois', 10);

        $titres = array_map(fn ($r) => $r->titre, app(Cerveau::class)->recommandations('marketing'));

        $this->assertEmpty(array_filter($titres, fn ($t) => str_contains($t, 'Campagne du mois')));
    }

    /** LE GESTE MARCHE VRAIMENT : proposer sans pouvoir appliquer ne servirait à rien. */
    public function test_le_geste_met_bien_la_campagne_en_pause(): void
    {
        $campagne = $this->campagne('Vieille campagne', 120);

        app(Cerveau::class)->appliquer(
            $this->titulaire(),
            RegistreDesGestes::SUSPENDRE_CAMPAGNE,
            ['id' => $campagne->id],
        );

        $this->assertSame(MarketingCampaign::STATUS_PAUSED, $campagne->fresh()->status);
    }

    // ── Fraude ─────────────────────────────────────────────────────────────

    /** LE CLIENT QUI ANNULE APRÈS AFFECTATION — le motif qui coûte au prestataire. */
    public function test_il_signale_un_client_qui_annule_toujours_apres_affectation(): void
    {
        $client = User::factory()->client()->create(['name' => 'Camille Annule']);
        $prestataire = User::factory()->employe()->create();

        foreach (range(1, 8) as $i) {
            Booking::factory()->create([
                'client_id' => $client->id,
                'assigned_provider_user_id' => $prestataire->id,
                'cancelled_at' => now()->subDays($i),
            ]);
        }

        $titres = array_map(fn ($r) => $r->titre, app(Cerveau::class)->recommandations('fraude'));

        $this->assertNotEmpty(array_filter($titres, fn ($t) => str_contains($t, 'Camille Annule')));
    }

    /** TÉMOIN — le même client sans annulation ne déclenche rien. */
    public function test_temoin_un_client_qui_n_annule_pas_ne_declenche_rien(): void
    {
        $client = User::factory()->client()->create(['name' => 'Camille Fidele']);
        $prestataire = User::factory()->employe()->create();

        foreach (range(1, 8) as $i) {
            Booking::factory()->create([
                'client_id' => $client->id,
                'assigned_provider_user_id' => $prestataire->id,
                'cancelled_at' => null,
            ]);
        }

        $titres = array_map(fn ($r) => $r->titre, app(Cerveau::class)->recommandations('fraude'));

        $this->assertEmpty(array_filter($titres, fn ($t) => str_contains($t, 'Camille Fidele')));
    }

    /** UN PARRAIN, BEAUCOUP DE FILLEULS, AUCUNE MISSION. */
    public function test_il_signale_une_chaine_de_parrainage_sans_mission(): void
    {
        $parrain = User::factory()->create(['name' => 'Paul Parraine']);

        foreach (range(1, 12) as $i) {
            Referral::create([
                'referrer_user_id' => $parrain->id,
                'referee_email' => 'filleul'.$i.'@exemple.test',
                'referral_code' => 'CODE'.$i,
                'status' => 'invited',
            ]);
        }

        $titres = array_map(fn ($r) => $r->titre, app(Cerveau::class)->recommandations('fraude'));

        $this->assertNotEmpty(array_filter($titres, fn ($t) => str_contains($t, 'Paul Parraine')));
    }

    /**
     * LA MISE EN REVUE N'EST PAS UNE SUSPENSION.
     *
     * Un compte bloqué à tort est un client perdu et un litige : le cerveau met le dossier sur
     * une pile, il ne ferme pas la porte.
     */
    public function test_la_mise_en_revue_ne_suspend_pas_le_compte(): void
    {
        $suspect = User::factory()->client()->create(['is_active' => true]);

        app(Cerveau::class)->appliquer(
            $this->titulaire(),
            RegistreDesGestes::METTRE_EN_REVUE,
            ['user_id' => $suspect->id, 'motif' => 'Test'],
        );

        $this->assertTrue((bool) $suspect->fresh()->is_active, 'Le compte a été suspendu : ce n’est pas le geste.');
        $this->assertSame(1, RiskHold::query()->where('user_id', $suspect->id)->count());
    }

    // ── L'écran ────────────────────────────────────────────────────────────

    /** LE PREMIER CLIC N'APPLIQUE RIEN : il montre ce que le geste implique. */
    public function test_le_premier_clic_n_applique_rien(): void
    {
        $campagne = $this->campagne('Vieille campagne', 120);

        Livewire::actingAs($this->titulaire())
            ->test(LeCerveau::class)
            ->call('preparerLeGeste', RegistreDesGestes::SUSPENDRE_CAMPAGNE, ['id' => $campagne->id])
            ->assertSee('Ce que ça implique');

        $this->assertSame(MarketingCampaign::STATUS_RUNNING, $campagne->fresh()->status);
    }

    /** TÉMOIN — le second clic, lui, applique. */
    public function test_temoin_le_second_clic_applique(): void
    {
        $campagne = $this->campagne('Vieille campagne', 120);

        Livewire::actingAs($this->titulaire())
            ->test(LeCerveau::class)
            ->call('preparerLeGeste', RegistreDesGestes::SUSPENDRE_CAMPAGNE, ['id' => $campagne->id])
            ->call('appliquerLeGeste')
            ->assertSet('erreur', null);

        $this->assertSame(MarketingCampaign::STATUS_PAUSED, $campagne->fresh()->status);
    }

    /** LE PLUS GRAVE EN TÊTE : un écran qui noie une alerte rouge ne sert à rien. */
    public function test_les_alertes_graves_passent_en_tete(): void
    {
        $parrain = User::factory()->create(['name' => 'Paul Parraine']);

        foreach (range(1, 12) as $i) {
            Referral::create([
                'referrer_user_id' => $parrain->id,
                'referee_email' => 'f'.$i.'@exemple.test',
                'referral_code' => 'C'.$i,
                'status' => 'invited',
            ]);
        }

        $this->campagne('Vieille campagne', 120);

        $recommandations = app(Cerveau::class)->recommandations();

        $this->assertNotEmpty($recommandations);
        $this->assertSame('danger', $recommandations[0]->ton);
    }

    /**
     * UNE CAMPAGNE, SANS PASSER PAR LA FABRIQUE.
     *
     * `MarketingCampaignFactory` declare `protected $model = PromoCampaign::class` : elle
     * construit un objet d'une AUTRE table. Piege prealable a ce lot, signale et contourne.
     */
    private function campagne(string $nom, int $ageEnJours): MarketingCampaign
    {
        return MarketingCampaign::create([
            'code' => 'camp-'.uniqid(),
            'name' => $nom,
            'type' => MarketingCampaign::TYPE_SINGLE_BLAST,
            'status' => MarketingCampaign::STATUS_RUNNING,
            'started_at' => now()->subDays($ageEnJours),
        ]);
    }

    private function titulaire(): User
    {
        return $this->prendreLeSiege(['role' => 'admin']);
    }
}
