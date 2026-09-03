<?php

namespace Tests\Feature\Email;

use App\Models\EmailTemplate;
use App\Models\EmailTheme;
use App\Services\Email\MoteurDeThemeEmail;
use App\Services\Email\RenduDeBlocsEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LE SOCLE DU STUDIO D'E-MAILS.
 *
 * `email_templates` existait avec son moteur de rendu et ZERO ligne : personne n'appelait
 * `renderFromTemplate()`, et l'ecran affichait six gabarits ecrits en dur dans un `match` PHP.
 * Ils vivent desormais en base, en BLOCS, habilles par un THEME choisi a la date d'envoi.
 */
class LeSocleDuStudioDEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_six_gabarits_sont_en_base(): void
    {
        foreach ([
            'booking_confirmed', 'booking_reminder', 'feedback_request',
            'finance_reminder', 'new_booking_admin', 'status_update',
        ] as $code) {
            $this->assertDatabaseHas('email_templates', ['code' => $code, 'is_active' => true]);
        }
    }

    /** CHAQUE GABARIT PORTE DES BLOCS : sans eux il n'y aurait rien a editer ni a rendre. */
    public function test_chaque_gabarit_porte_des_blocs_et_ses_variables(): void
    {
        foreach (EmailTemplate::all() as $gabarit) {
            $this->assertNotEmpty($gabarit->blocks, "Le gabarit {$gabarit->code} n’a aucun bloc.");
            $this->assertNotEmpty($gabarit->required_variables, "Le gabarit {$gabarit->code} ne déclare aucune variable.");
        }
    }

    public function test_le_rendu_remplace_les_variables_et_habille_le_document(): void
    {
        $gabarit = EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail();
        $theme = app(MoteurDeThemeEmail::class)->pour($gabarit);

        $html = app(RenduDeBlocsEmail::class)->documentComplet(
            $gabarit->blocks,
            $theme,
            ['client_name' => 'Marie', 'service' => 'Nettoyage', 'action_url' => 'https://brio.test/espace'],
            $gabarit->subject_pattern,
        );

        $this->assertStringContainsString('Marie', $html);
        $this->assertStringContainsString('https://brio.test/espace', $html);
        $this->assertStringNotContainsString('{{client_name}}', $html, 'Une variable est restée à nu dans le rendu.');
    }

    /**
     * UN BLOC EST DU CONTENU SAISI, PAS DU HTML.
     *
     * Sans echappement, l'editeur deviendrait une porte d'injection vers la boite de reception
     * d'un client — le pire endroit ou un script puisse arriver.
     */
    public function test_le_texte_d_un_bloc_est_echappe(): void
    {
        $html = app(RenduDeBlocsEmail::class)->enHtml(
            [['type' => 'paragraph', 'text' => '<script>alert(1)</script>']],
            app(MoteurDeThemeEmail::class)->parDefaut(),
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /** UN LIEN N'EST PAS DU TEXTE : seuls http, https et mailto sortent du moteur. */
    public function test_un_lien_hostile_ne_sort_pas_du_moteur(): void
    {
        $theme = app(MoteurDeThemeEmail::class)->parDefaut();
        $rendu = app(RenduDeBlocsEmail::class);

        $hostile = $rendu->enHtml([['type' => 'button', 'text' => 'Cliquer', 'url' => 'javascript:alert(1)']], $theme);
        $this->assertStringNotContainsString('javascript:', $hostile);
        $this->assertStringNotContainsString('<a', $hostile, 'Un bouton sans URL valide ne doit pas exister du tout.');
    }

    /** TEMOIN — le meme bloc avec une URL legitime rend bien son bouton. */
    public function test_temoin_un_lien_legitime_rend_son_bouton(): void
    {
        $html = app(RenduDeBlocsEmail::class)->enHtml(
            [['type' => 'button', 'text' => 'Cliquer', 'url' => 'https://brio.test/ok']],
            app(MoteurDeThemeEmail::class)->parDefaut(),
        );

        $this->assertStringContainsString('https://brio.test/ok', $html);
        $this->assertStringContainsString('<a', $html);
    }

    /** LA SAISON L'EMPORTE sur le theme permanent, a l'interieur de sa fenetre. */
    public function test_un_theme_saisonnier_gagne_dans_sa_fenetre(): void
    {
        EmailTheme::factory()->saison('2026-11-24', '2026-11-30', 50)->create(['code' => 'saison-test-bf']);

        $moteur = app(MoteurDeThemeEmail::class);

        $this->assertSame('saison-test-bf', $moteur->pour(null, Carbon::parse('2026-11-27'))->code);
    }

    /** TEMOIN — hors de la fenetre, le theme permanent reprend la main. */
    public function test_temoin_hors_fenetre_le_theme_permanent_reprend(): void
    {
        EmailTheme::factory()->saison('2026-11-24', '2026-11-30', 50)->create(['code' => 'saison-test-bf']);

        $this->assertSame('brio', app(MoteurDeThemeEmail::class)->pour(null, Carbon::parse('2026-12-15'))->code);
    }

    /**
     * UNE FENETRE ANNUELLE PEUT CHEVAUCHER LE 31 DECEMBRE.
     *
     * Du 20 decembre au 2 janvier, le debut est POSTERIEUR a la fin dans l'annee civile : une
     * comparaison naive rendrait faux les deux semaines ou le theme doit justement s'appliquer.
     */
    public function test_une_fenetre_annuelle_franchit_le_nouvel_an(): void
    {
        EmailTheme::factory()->saison('2020-12-20', '2021-01-02', 40, true)->create(['code' => 'saison-test-noel']);

        $moteur = app(MoteurDeThemeEmail::class);

        $this->assertSame('saison-test-noel', $moteur->pour(null, Carbon::parse('2026-12-24'))->code, 'Avant le passage d’année.');
        $this->assertSame('saison-test-noel', $moteur->pour(null, Carbon::parse('2027-01-01'))->code, 'Après le passage d’année.');
        $this->assertSame('brio', $moteur->pour(null, Carbon::parse('2027-01-10'))->code, 'Hors fenêtre.');
    }

    /** A FENETRES QUI SE CHEVAUCHENT, LA PRIORITE TRANCHE. */
    public function test_la_priorite_departage_deux_saisons(): void
    {
        EmailTheme::factory()->saison('2026-11-20', '2026-12-05', 10)->create(['code' => 'saison-test-longue']);
        EmailTheme::factory()->saison('2026-11-24', '2026-11-30', 50)->create(['code' => 'saison-test-bf']);

        $this->assertSame('saison-test-bf', app(MoteurDeThemeEmail::class)->pour(null, Carbon::parse('2026-11-27'))->code);
    }

    /**
     * UNE FACTURE NE SE DEGUISE PAS.
     *
     * Le gabarit qui impose son theme le garde en toute saison : c'est la seule protection contre
     * un rappel de paiement habille en Black Friday.
     */
    public function test_un_gabarit_qui_impose_son_theme_ignore_la_saison(): void
    {
        EmailTheme::factory()->saison('2026-11-24', '2026-11-30', 50)->create(['code' => 'saison-test-bf']);

        $facture = EmailTemplate::query()->where('code', 'finance_reminder')->firstOrFail();

        $this->assertNotNull($facture->email_theme_id, 'Le rappel de facture n’impose plus son thème.');
        $this->assertSame('brio', app(MoteurDeThemeEmail::class)->pour($facture, Carbon::parse('2026-11-27'))->code);
    }

    /** TEMOIN — un gabarit qui n'impose rien suit bien la saison, lui. */
    public function test_temoin_un_gabarit_libre_suit_la_saison(): void
    {
        EmailTheme::factory()->saison('2026-11-24', '2026-11-30', 50)->create(['code' => 'saison-test-bf']);

        $libre = EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail();

        $this->assertNull($libre->email_theme_id);
        $this->assertSame('saison-test-bf', app(MoteurDeThemeEmail::class)->pour($libre, Carbon::parse('2026-11-27'))->code);
    }

    /** LE REPLI : un envoi ne depend jamais de la presence d'une ligne en base. */
    public function test_le_moteur_rend_un_theme_meme_sans_aucune_ligne(): void
    {
        EmailTheme::query()->delete();

        $theme = app(MoteurDeThemeEmail::class)->parDefaut();

        $this->assertSame('brio', $theme->code);
        $this->assertFalse($theme->exists, 'Le repli ne doit pas etre enregistre en base.');
    }
}
