<?php

namespace Tests\Feature\Platform;

use App\Livewire\Admin\LeSiegeDeLaPlateforme;
use App\Models\PlatformBankAccount;
use App\Models\PlatformVaultAccess;
use App\Models\User;
use App\Services\Platform\CoffreBancaire;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE COFFRE — le compte qui reçoit les commissions.
 *
 * CE QU'ON PROTÈGE N'EST PAS L'IBAN, c'est le CHANGEMENT d'IBAN : un IBAN figure sur chaque
 * facture émise, mais remplacer celui de la plateforme détourne tous les encaissements à venir.
 *
 * Chaque refus porte son témoin : un garde vert parce que le chemin est cassé ne prouve rien.
 */
class LeCoffreBancaireTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'coffre-brio-2026';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('coffre:code:1');
    }

    // ── La porte ───────────────────────────────────────────────────────────

    public function test_un_administrateur_meme_complet_n_ouvre_pas_le_coffre(): void
    {
        $admin = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => array_keys(User::allowedAdminPermissions()),
        ]);

        $this->expectException(DomainException::class);

        app(CoffreBancaire::class)->ouvrir($admin, self::CODE);
    }

    // ── Le premier dépôt ───────────────────────────────────────────────────

    /**
     * LE COFFRE VIDE N'A PAS DE CODE À DEMANDER.
     *
     * Exiger un code qui n'existe pas rendrait le premier dépôt impossible ; l'accès au siège
     * est déjà la preuve. Dès le second, l'ancien code est exigé.
     */
    public function test_le_premier_compte_se_depose_sans_code_prealable(): void
    {
        $titulaire = $this->titulaire();

        $compte = app(CoffreBancaire::class)->remplacerLeCompte(
            $titulaire,
            ['holder_name' => 'Brio SRL', 'iban' => 'BE68 5390 0754 7034'],
            '',
            self::CODE,
        );

        $this->assertTrue($compte->is_active);
        $this->assertSame('7034', $compte->iban_last4);
        $this->assertNotNull($titulaire->fresh()->vault_code_hash);
    }

    /** L'IBAN EST CHIFFRÉ AU REPOS : une copie de la base ne le rend pas. */
    public function test_l_iban_ne_se_lit_pas_en_clair_dans_la_base(): void
    {
        app(CoffreBancaire::class)->remplacerLeCompte(
            $this->titulaire(),
            ['holder_name' => 'Brio SRL', 'iban' => 'BE68 5390 0754 7034'],
            '',
            self::CODE,
        );

        $brut = (string) DB::table('platform_bank_accounts')->value('iban');

        $this->assertStringNotContainsString('BE68539007547034', $brut);
        $this->assertStringNotContainsString('5390', $brut);
    }

    /** TÉMOIN — le modèle, lui, le relit bien. */
    public function test_temoin_le_modele_relit_l_iban(): void
    {
        app(CoffreBancaire::class)->remplacerLeCompte(
            $this->titulaire(),
            ['holder_name' => 'Brio SRL', 'iban' => 'BE68 5390 0754 7034'],
            '',
            self::CODE,
        );

        $this->assertSame('BE68539007547034', PlatformBankAccount::query()->actif()->first()?->iban);
    }

    // ── Le code ────────────────────────────────────────────────────────────

    public function test_un_mauvais_code_n_ouvre_rien(): void
    {
        $titulaire = $this->titulaire();
        $this->deposerUnPremierCompte($titulaire);

        $this->expectException(DomainException::class);

        app(CoffreBancaire::class)->ouvrir($titulaire, 'ce-n-est-pas-le-code');
    }

    /** TÉMOIN — le bon code ouvre. */
    public function test_temoin_le_bon_code_ouvre(): void
    {
        $titulaire = $this->titulaire();
        $this->deposerUnPremierCompte($titulaire);

        $compte = app(CoffreBancaire::class)->ouvrir($titulaire, self::CODE);

        $this->assertSame('7034', $compte?->iban_last4);
    }

    /** UN CODE TROP COURT EST REFUSÉ : il se devinerait en le répétant. */
    public function test_un_code_trop_court_est_refuse(): void
    {
        $this->expectException(DomainException::class);

        app(CoffreBancaire::class)->remplacerLeCompte(
            $this->titulaire(),
            ['holder_name' => 'Brio SRL', 'iban' => 'BE68 5390 0754 7034'],
            '',
            'court',
        );
    }

    /**
     * LE CODE PEUT RESTER LE MÊME — c'est une décision du titulaire.
     *
     * Ce qui compte n'est pas qu'il change, mais qu'il soit SAISI à chaque fois : un changement
     * de destination bancaire ne doit jamais se faire d'un seul geste distrait.
     */
    public function test_le_code_peut_rester_le_meme_d_un_changement_a_l_autre(): void
    {
        $titulaire = $this->titulaire();
        $this->deposerUnPremierCompte($titulaire);

        $compte = app(CoffreBancaire::class)->remplacerLeCompte(
            $titulaire,
            ['holder_name' => 'Brio SRL', 'iban' => 'FR14 2004 1010 0505 0001 3M02 606'],
            self::CODE,
            self::CODE,
        );

        $this->assertSame('2606', $compte->iban_last4);
    }

    /** LE SECOND CHANGEMENT EXIGE L'ANCIEN CODE. */
    public function test_le_second_changement_exige_l_ancien_code(): void
    {
        $titulaire = $this->titulaire();
        $this->deposerUnPremierCompte($titulaire);

        $this->expectException(DomainException::class);

        app(CoffreBancaire::class)->remplacerLeCompte(
            $titulaire,
            ['holder_name' => 'Voleur', 'iban' => 'FR14 2004 1010 0505 0001 3M02 606'],
            'mauvais-code',
            'nouveau-code-long',
        );
    }

    // ── L'historique ───────────────────────────────────────────────────────

    /**
     * ON NE MODIFIE JAMAIS UNE LIGNE : on en ajoute une, l'ancienne se ferme.
     *
     * Un détournement qui pourrait réécrire en place effacerait sa propre trace ; ici il en
     * laisse deux.
     */
    public function test_l_ancien_compte_se_ferme_et_reste(): void
    {
        $titulaire = $this->titulaire();
        $this->deposerUnPremierCompte($titulaire);

        app(CoffreBancaire::class)->remplacerLeCompte(
            $titulaire,
            ['holder_name' => 'Brio SRL', 'iban' => 'FR14 2004 1010 0505 0001 3M02 606'],
            self::CODE,
            self::CODE,
        );

        $this->assertSame(2, PlatformBankAccount::query()->count());
        $this->assertSame(1, PlatformBankAccount::query()->actif()->count());
        $this->assertNotNull(PlatformBankAccount::query()->where('iban_last4', '7034')->first()?->closed_at);
    }

    /** LES REFUS SONT TRACÉS AUTANT QUE LES RÉUSSITES : c'est eux qui annoncent une intrusion. */
    public function test_un_refus_laisse_sa_trace(): void
    {
        $titulaire = $this->titulaire();
        $this->deposerUnPremierCompte($titulaire);

        try {
            app(CoffreBancaire::class)->ouvrir($titulaire, 'mauvais');
        } catch (DomainException) {
            // attendu
        }

        $this->assertSame(1, PlatformVaultAccess::query()->where('action', PlatformVaultAccess::REFUSE)->count());
    }

    /** UNE SÉRIE DE CODES FAUX FERME LA PORTE : un code se devine en le répétant. */
    public function test_cinq_essais_ferment_la_porte(): void
    {
        $titulaire = $this->titulaire();
        $this->deposerUnPremierCompte($titulaire);

        foreach (range(1, 5) as $i) {
            try {
                app(CoffreBancaire::class)->ouvrir($titulaire, 'faux-'.$i);
            } catch (DomainException) {
                // attendu
            }
        }

        // Le sixième essai est refusé AVANT même de comparer : c'est la limite qui parle.
        try {
            app(CoffreBancaire::class)->ouvrir($titulaire, self::CODE);
            $this->fail('Le coffre s’est ouvert malgré cinq échecs.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Trop d’essais', $e->getMessage());
        }
    }

    // ── Un IBAN qui n'en est pas un ────────────────────────────────────────

    public function test_un_iban_manifestement_invalide_est_refuse(): void
    {
        $this->expectException(DomainException::class);

        app(CoffreBancaire::class)->remplacerLeCompte(
            $this->titulaire(),
            ['holder_name' => 'Brio SRL', 'iban' => '1234'],
            '',
            self::CODE,
        );
    }

    public function test_un_compte_sans_titulaire_est_refuse(): void
    {
        $this->expectException(DomainException::class);

        app(CoffreBancaire::class)->remplacerLeCompte(
            $this->titulaire(),
            ['holder_name' => '  ', 'iban' => 'BE68 5390 0754 7034'],
            '',
            self::CODE,
        );
    }

    // ── L'écran ────────────────────────────────────────────────────────────

    /** L'ÉCRAN NE MONTRE QUE QUATRE CHIFFRES — jamais l'IBAN entier. */
    public function test_l_ecran_ne_montre_que_les_quatre_derniers(): void
    {
        $titulaire = $this->titulaire();
        $this->deposerUnPremierCompte($titulaire);

        Livewire::actingAs($titulaire)
            ->test(LeSiegeDeLaPlateforme::class)
            ->assertSee('7034')
            ->assertDontSee('BE68539007547034');
    }

    /** LE COFFRE NE RESTE PAS OUVERT DERRIÈRE SOI. */
    public function test_le_coffre_se_referme(): void
    {
        $titulaire = $this->titulaire();
        $this->deposerUnPremierCompte($titulaire);

        Livewire::actingAs($titulaire)
            ->test(LeSiegeDeLaPlateforme::class)
            ->set('codeDuCoffre', self::CODE)
            ->call('ouvrirLeCoffre')
            ->assertSet('coffreOuvert', true)
            ->call('refermerLeCoffre')
            ->assertSet('coffreOuvert', false)
            ->assertSet('codeDuCoffre', '');
    }

    private function deposerUnPremierCompte(User $titulaire): void
    {
        app(CoffreBancaire::class)->remplacerLeCompte(
            $titulaire,
            ['holder_name' => 'Brio SRL', 'iban' => 'BE68 5390 0754 7034'],
            '',
            self::CODE,
        );
    }

    private function titulaire(): User
    {
        return $this->prendreLeSiege(['role' => 'admin']);
    }
}
