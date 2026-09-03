<?php

namespace Tests\Feature\Email;

use App\Livewire\Admin\ProductEmailsCenter;
use App\Models\EmailSendRule;
use App\Models\EmailTemplate;
use App\Models\EmailTheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ATELIER D'E-MAILS : L'ECRAN N'AFFICHE PLUS, IL EDITE.
 *
 * Il savait montrer six apercus de gabarits codes en dur. Il les compose desormais en blocs, les
 * duplique, les supprime, et rejoue l'apercu sous n'importe quel theme sans rien envoyer.
 */
class LAtelierDEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_atelier_ouvre_sur_un_gabarit_et_charge_ses_blocs(): void
    {
        Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed')
            ->assertSet('nom', 'Rendez-vous confirmé')
            ->assertCount('blocs', 6);
    }

    public function test_un_bloc_s_ajoute_se_deplace_et_se_retire(): void
    {
        $composant = Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'status_update');

        $avant = count($composant->get('blocs'));

        $composant->call('ajouterBloc', 'divider')
            ->assertCount('blocs', $avant + 1);

        // Le dernier bloc remonte d'un cran : il n'est plus en queue.
        $composant->call('deplacerBloc', $avant, -1);
        $this->assertSame('divider', $composant->get('blocs')[$avant - 1]['type']);

        $composant->call('retirerBloc', $avant - 1)
            ->assertCount('blocs', $avant);
    }

    /** UN TYPE INCONNU N'ENTRE PAS DANS LE DOCUMENT. */
    public function test_un_type_de_bloc_inconnu_est_refuse(): void
    {
        $composant = Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'status_update');

        $avant = count($composant->get('blocs'));

        $composant->call('ajouterBloc', 'script')->assertCount('blocs', $avant);
    }

    /** TEMOIN — un type connu entre bien, lui ; sans quoi le refus mesurerait une panne. */
    public function test_temoin_un_type_connu_entre(): void
    {
        $composant = Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'status_update');

        $avant = count($composant->get('blocs'));

        $composant->call('ajouterBloc', 'heading')->assertCount('blocs', $avant + 1);
    }

    public function test_l_enregistrement_ecrit_les_blocs_et_l_objet(): void
    {
        Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'status_update')
            ->set('objet', 'Votre intervention a changé')
            ->set('blocs', [['type' => 'heading', 'text' => 'Titre réécrit']])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $gabarit = EmailTemplate::query()->where('code', 'status_update')->firstOrFail();

        $this->assertSame('Votre intervention a changé', $gabarit->subject_pattern);
        $this->assertSame('Titre réécrit', $gabarit->blocks[0]['text']);
    }

    /** UNE COPIE NE PART PAS TOUTE SEULE : elle naît inactive, et s'active à la main. */
    public function test_une_copie_nait_inactive(): void
    {
        Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed')
            ->call('dupliquer');

        $copie = EmailTemplate::query()->where('name', 'like', '%(copie)')->firstOrFail();

        $this->assertFalse((bool) $copie->is_active);
        $this->assertNotSame('booking_confirmed', $copie->code);
        $this->assertSame(6, count($copie->blocks), 'La copie a perdu les blocs de son original.');
    }

    /** UN GABARIT NEUF NAÎT INACTIF LUI AUSSI : rien ne part sans une relecture. */
    public function test_un_gabarit_neuf_nait_inactif(): void
    {
        Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->call('nouveauGabarit')
            ->assertSet('actif', false);

        $this->assertDatabaseHas('email_templates', ['name' => 'Nouveau gabarit', 'is_active' => false]);
    }

    public function test_la_suppression_passe_par_une_confirmation(): void
    {
        $gabarit = EmailTemplate::query()->where('code', 'status_update')->firstOrFail();

        $composant = Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'status_update')
            ->call('supprimer');

        // Le 3e argument d'`assertDatabaseHas` est une CONNEXION, pas un message : l'assertion
        // porte donc le sien elle-meme.
        $this->assertTrue(EmailTemplate::query()->whereKey($gabarit->id)->exists(),
            'Un appel direct à supprimer, sans confirmation, a détruit le gabarit.');

        $composant->call('demanderLaSuppression', $gabarit->id)->call('supprimer');

        $this->assertDatabaseMissing('email_templates', ['id' => $gabarit->id]);
    }

    /**
     * L'APERÇU SE REJOUE SOUS N'IMPORTE QUEL THÈME, SANS RIEN ENVOYER.
     *
     * C'est ce qui permet de voir un e-mail en tenue de Black Friday au mois de mai.
     */
    public function test_l_apercu_se_rejoue_sous_le_theme_demande(): void
    {
        $noel = EmailTheme::factory()->saison('2026-12-20', '2026-12-31', 40)
            ->create(['code' => 'saison-test-noel', 'color_accent' => '#c81d25']);

        $composant = Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed');

        $this->assertStringNotContainsString('#c81d25', (string) $composant->get('previewHtml'));

        $composant->set('themeDApercu', (string) $noel->id);

        $this->assertStringContainsString('#c81d25', (string) $composant->get('previewHtml'),
            'Le thème demandé ne teinte pas l’aperçu.');
    }

    /** L'APERÇU MONTRE DES VALEURS, PAS DES ACCOLADES. */
    public function test_l_apercu_remplace_les_variables_par_des_exemples(): void
    {
        $html = (string) Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed')
            ->get('previewHtml');

        $this->assertStringNotContainsString('{{client_name}}', $html);
        $this->assertStringContainsString('Client Démo', $html);
    }

    /**
     * LA CAPACITÉ GARDE AUSSI LE COMPOSANT : `module_gate` pose `manage-communication` sur la
     * route, mais `/livewire/update` ne rejoue aucun middleware.
     */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $sansCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-calendar'],
        ]);

        Livewire::actingAs($sansCapacite)->test(ProductEmailsCenter::class)->assertForbidden();
    }

    /** TEMOIN — la même visite avec la capacité aboutit. */
    public function test_temoin_un_administrateur_avec_la_capacite_entre(): void
    {
        $avecCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-communication'],
        ]);

        Livewire::actingAs($avecCapacite)->test(ProductEmailsCenter::class)->assertOk();
    }

    // ── Les regles d'envoi ─────────────────────────────────────────────────

    /** UNE REGLE NEUVE NAIT INACTIVE : rien ne part avant qu'on l'ait relue. */
    public function test_une_regle_neuve_nait_inactive(): void
    {
        Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed')
            ->call('ajouterUneRegle');

        $gabarit = EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail();

        $this->assertSame(1, $gabarit->sendRules()->count());
        $this->assertFalse((bool) $gabarit->sendRules()->first()->is_active);
    }

    /**
     * CHAQUE REGLE PORTE SON PROPRE BROUILLON.
     *
     * Une seule propriete partagee attacherait la saisie faite sur une regle a l'enregistrement
     * d'une autre — le defaut deja paye sur les approbations entreprises.
     */
    public function test_la_saisie_d_une_regle_ne_part_pas_avec_une_autre(): void
    {
        $gabarit = EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail();

        $premiere = EmailSendRule::factory()->create(['email_template_id' => $gabarit->id, 'name' => 'Première']);
        $seconde = EmailSendRule::factory()->create(['email_template_id' => $gabarit->id, 'name' => 'Seconde']);

        Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed')
            ->set('brouillons.'.$premiere->id.'.name', 'Nom destiné à la PREMIÈRE')
            ->call('enregistrerUneRegle', $seconde->id);

        $this->assertSame('Seconde', $seconde->fresh()->name,
            'Le nom saisi sur une autre règle s’est attaché à celle-ci.');
    }

    /** TEMOIN — la saisie faite sur LA BONNE regle arrive bien jusqu'a la base. */
    public function test_temoin_la_saisie_de_la_bonne_regle_est_enregistree(): void
    {
        $gabarit = EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail();
        $regle = EmailSendRule::factory()->create(['email_template_id' => $gabarit->id, 'name' => 'Avant']);

        Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed')
            ->set('brouillons.'.$regle->id.'.name', 'Rappel J-1')
            ->set('brouillons.'.$regle->id.'.trigger_type', 'reminder')
            ->set('brouillons.'.$regle->id.'.offset_minutes', -1440)
            ->call('enregistrerUneRegle', $regle->id);

        $regle->refresh();

        $this->assertSame('Rappel J-1', $regle->name);
        $this->assertSame('reminder', $regle->trigger_type);
        $this->assertSame(-1440, $regle->offset_minutes);
    }

    /** UN TYPE DE DECLENCHEUR INCONNU NE S'ECRIT PAS. */
    public function test_un_declencheur_inconnu_est_ignore(): void
    {
        $gabarit = EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail();
        $regle = EmailSendRule::factory()->surEvenement('booking.confirmed')->create([
            'email_template_id' => $gabarit->id,
        ]);

        Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed')
            ->set('brouillons.'.$regle->id.'.trigger_type', 'appeler_le_client')
            ->call('enregistrerUneRegle', $regle->id);

        $this->assertSame('event', $regle->fresh()->trigger_type);
    }

    /** LE PLAFOND NE DESCEND PAS SOUS ZERO, et zero veut dire « aucun plafond ». */
    public function test_le_plafond_ne_descend_pas_sous_zero(): void
    {
        $gabarit = EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail();
        $regle = EmailSendRule::factory()->create(['email_template_id' => $gabarit->id]);

        Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed')
            ->set('brouillons.'.$regle->id.'.cap_per_recipient', -5)
            ->set('brouillons.'.$regle->id.'.cap_window_hours', 0)
            ->call('enregistrerUneRegle', $regle->id);

        $regle->refresh();

        $this->assertSame(0, $regle->cap_per_recipient);
        $this->assertSame(1, $regle->cap_window_hours, 'Une fenêtre nulle rendrait le plafond inapplicable.');
    }

    public function test_une_regle_se_suspend_et_se_supprime(): void
    {
        $gabarit = EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail();
        $regle = EmailSendRule::factory()->create(['email_template_id' => $gabarit->id, 'is_active' => true]);

        $composant = Livewire::actingAs($this->admin())->test(ProductEmailsCenter::class)
            ->set('templateKey', 'booking_confirmed')
            ->call('basculerUneRegle', $regle->id);

        $this->assertFalse((bool) $regle->fresh()->is_active);

        $composant->call('retirerUneRegle', $regle->id);

        $this->assertDatabaseMissing('email_send_rules', ['id' => $regle->id]);
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
