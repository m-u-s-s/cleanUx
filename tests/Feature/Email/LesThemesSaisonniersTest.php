<?php

namespace Tests\Feature\Email;

use App\Livewire\Admin\EmailThemesStudio;
use App\Models\EmailTheme;
use App\Models\User;
use App\Services\Email\MoteurDeThemeEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'HABILLAGE DES E-MAILS ET SON CALENDRIER.
 *
 * Quatre saisons arrivent posees — Black Friday, Noel, Paques, nouvel an chinois — et INACTIVES :
 * un theme qui s'allumerait tout seul repeindrait les e-mails d'une plateforme sans que personne
 * l'ait decide.
 */
class LesThemesSaisonniersTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_quatre_saisons_sont_posees_et_toutes_inactives(): void
    {
        foreach (['black-friday', 'noel', 'paques', 'nouvel-an-chinois'] as $code) {
            $this->assertDatabaseHas('email_themes', ['code' => $code, 'is_active' => false]);
        }
    }

    /** TEMOIN — le theme permanent, lui, est bien actif : sans quoi rien n'habillerait un e-mail. */
    public function test_temoin_le_theme_permanent_est_actif(): void
    {
        $this->assertDatabaseHas('email_themes', ['code' => 'brio', 'is_active' => true, 'is_default' => true]);
    }

    /**
     * LA RECURRENCE ANNUELLE N'EST PAS UNIFORME.
     *
     * Noel tombe au meme jour chaque annee. Black Friday, Paques et le nouvel an chinois SE
     * DEPLACENT : les marquer annuels produirait, l'annee suivante, un Black Friday le mauvais jour.
     */
    public function test_seul_noel_se_repete_a_l_identique_chaque_annee(): void
    {
        $this->assertTrue((bool) EmailTheme::query()->where('code', 'noel')->value('recurs_yearly'));

        foreach (['black-friday', 'paques', 'nouvel-an-chinois'] as $code) {
            $this->assertFalse((bool) EmailTheme::query()->where('code', $code)->value('recurs_yearly'),
                "La saison {$code} se deplace chaque annee : elle ne peut pas etre marquee annuelle.");
        }
    }

    /** UNE SAISON INACTIVE N'HABILLE RIEN, meme au coeur de sa fenetre. */
    public function test_une_saison_inactive_n_habille_rien(): void
    {
        $moteur = app(MoteurDeThemeEmail::class);

        $this->assertSame('brio', $moteur->pour(null, Carbon::parse('2026-11-27'))->code);
    }

    /** TEMOIN — la meme saison, une fois activee, prend bien la main. */
    public function test_temoin_la_meme_saison_activee_prend_la_main(): void
    {
        EmailTheme::query()->where('code', 'black-friday')->update(['is_active' => true]);

        $moteur = app(MoteurDeThemeEmail::class);

        $this->assertSame('black-friday', $moteur->pour(null, Carbon::parse('2026-11-27'))->code);
    }

    public function test_l_editeur_enregistre_les_couleurs_et_la_fenetre(): void
    {
        $theme = EmailTheme::query()->where('code', 'paques')->firstOrFail();

        Livewire::actingAs($this->admin())->test(EmailThemesStudio::class)
            ->call('ouvrir', $theme->id)
            ->set('nom', 'Pâques 2028')
            ->set('couleurs.color_accent', '#123456')
            ->set('debut', '2028-04-10')
            ->set('fin', '2028-04-18')
            ->set('priorite', 65)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $theme->refresh();

        $this->assertSame('Pâques 2028', $theme->name);
        $this->assertSame('#123456', $theme->color_accent);
        $this->assertSame('2028-04-10', $theme->starts_on?->format('Y-m-d'));
        $this->assertSame(65, $theme->priority);
    }

    /** UNE COULEUR EST UN CODE HEXADECIMAL : elle part en style en ligne vers l'exterieur. */
    public function test_une_couleur_invalide_est_refusee(): void
    {
        $theme = EmailTheme::query()->where('code', 'paques')->firstOrFail();

        Livewire::actingAs($this->admin())->test(EmailThemesStudio::class)
            ->call('ouvrir', $theme->id)
            ->set('couleurs.color_accent', 'rouge vif')
            ->call('enregistrer')
            ->assertHasErrors(['couleurs.color_accent']);

        $this->assertNotSame('rouge vif', $theme->fresh()->color_accent);
    }

    /**
     * UN SEUL THEME PAR DEFAUT.
     *
     * Deux « par defaut » coexistants feraient choisir le moteur par identifiant — au hasard.
     */
    public function test_designer_un_nouveau_theme_permanent_retire_l_ancien(): void
    {
        $ancien = EmailTheme::query()->where('code', 'brio')->firstOrFail();
        $nouveau = EmailTheme::query()->where('code', 'noel')->firstOrFail();

        Livewire::actingAs($this->admin())->test(EmailThemesStudio::class)
            ->call('ouvrir', $nouveau->id)
            ->set('parDefaut', true)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $nouveau->fresh()->is_default);
        $this->assertFalse((bool) $ancien->fresh()->is_default, 'Deux thèmes permanents coexistent.');
    }

    /** LE THEME PAR DEFAUT NE SE SUPPRIME PAS : il est le socle de tout le reste. */
    public function test_le_theme_permanent_ne_se_supprime_pas(): void
    {
        $permanent = EmailTheme::query()->where('code', 'brio')->firstOrFail();

        Livewire::actingAs($this->admin())->test(EmailThemesStudio::class)
            ->call('demanderLaSuppression', $permanent->id)
            ->call('supprimer');

        $this->assertDatabaseHas('email_themes', ['id' => $permanent->id]);
    }

    /** TEMOIN — une saison, elle, se supprime bien. */
    public function test_temoin_une_saison_se_supprime(): void
    {
        $saison = EmailTheme::query()->where('code', 'paques')->firstOrFail();

        Livewire::actingAs($this->admin())->test(EmailThemesStudio::class)
            ->call('demanderLaSuppression', $saison->id)
            ->call('supprimer');

        $this->assertDatabaseMissing('email_themes', ['id' => $saison->id]);
    }

    /** L'APERCU SUIT LA COULEUR EN COURS D'EDITION, pas celle enregistree. */
    public function test_l_apercu_suit_la_couleur_avant_enregistrement(): void
    {
        $theme = EmailTheme::query()->where('code', 'noel')->firstOrFail();

        $composant = Livewire::actingAs($this->admin())->test(EmailThemesStudio::class)
            ->call('ouvrir', $theme->id)
            ->set('couleurs.color_accent', '#abcdef');

        $this->assertStringContainsString('#abcdef', (string) $composant->get('apercu'));
        $this->assertNotSame('#abcdef', $theme->fresh()->color_accent, 'La couleur a été écrite sans enregistrement.');
    }

    /** LA CAPACITE GARDE AUSSI CE COMPOSANT — il est imbrique, donc atteignable directement. */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $sansCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-calendar'],
        ]);

        Livewire::actingAs($sansCapacite)->test(EmailThemesStudio::class)->assertForbidden();
    }

    /** TEMOIN — la meme visite avec la capacite aboutit. */
    public function test_temoin_un_administrateur_avec_la_capacite_entre(): void
    {
        $avecCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-communication'],
        ]);

        Livewire::actingAs($avecCapacite)->test(EmailThemesStudio::class)->assertOk();
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
