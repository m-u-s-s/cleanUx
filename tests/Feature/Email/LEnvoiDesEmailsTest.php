<?php

namespace Tests\Feature\Email;

use App\Models\Booking;
use App\Models\EmailSendRule;
use App\Models\EmailTemplate;
use App\Models\MarketingOptOut;
use App\Models\User;
use App\Services\Automation\Actions\EnvoyerUnEmail;
use App\Services\Automation\Catalogue;
use App\Services\Email\EnvoiDEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * QUAND UN E-MAIL PART, ET COMBIEN DE FOIS.
 *
 * Trois freins se posent AVANT le rendu : le gabarit est-il actif, le destinataire a-t-il refuse,
 * le plafond est-il atteint. Composer un document qu'on n'enverra pas est du travail perdu — et
 * confondre « refuse » avec « envoye » rend le journal inutilisable le jour ou un e-mail manque.
 */
class LEnvoiDesEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_gabarit_actif_part(): void
    {
        $resultat = app(EnvoiDEmail::class)->envoyer(
            $this->gabarit(),
            'client@brio.test',
            ['client_name' => 'Marie'],
        );

        $this->assertTrue($resultat->parti, $resultat->raison);
        $this->assertDatabaseHas('email_messages', [
            'to_email' => 'client@brio.test',
            'template_code' => 'booking_confirmed',
        ]);
    }

    /** UN BROUILLON NE PART PAS, meme appele par une regle. */
    public function test_un_gabarit_inactif_ne_part_pas(): void
    {
        $gabarit = $this->gabarit();
        $gabarit->update(['is_active' => false]);

        $resultat = app(EnvoiDEmail::class)->envoyer($gabarit, 'client@brio.test');

        $this->assertFalse($resultat->parti);
        $this->assertStringContainsString('inactif', $resultat->raison);
        $this->assertDatabaseMissing('email_messages', ['to_email' => 'client@brio.test']);
    }

    /**
     * LE PLAFOND BORNE UN MEME DESTINATAIRE SUR UNE FENETRE GLISSANTE.
     *
     * Sans lui, une regle mal reglee transforme la plateforme en source de courrier indesirable —
     * et brule l'adresse d'expedition avec elle.
     */
    public function test_le_plafond_arrete_le_deuxieme_envoi(): void
    {
        $gabarit = $this->gabarit();
        $regle = EmailSendRule::factory()->plafond(1, 24)->create(['email_template_id' => $gabarit->id]);

        $envoi = app(EnvoiDEmail::class);

        $this->assertTrue($envoi->envoyer($gabarit, 'client@brio.test', [], $regle)->parti);

        $second = $envoi->envoyer($gabarit, 'client@brio.test', [], $regle);

        $this->assertFalse($second->parti);
        $this->assertStringContainsString('Plafond', $second->raison);
        $this->assertSame(1, DB::table('email_messages')->where('to_email', 'client@brio.test')->count());
    }

    /** TEMOIN — sans plafond, le second envoi passe : le refus mesure bien la borne. */
    public function test_temoin_sans_plafond_le_second_envoi_passe(): void
    {
        $gabarit = $this->gabarit();
        $regle = EmailSendRule::factory()->plafond(0)->create(['email_template_id' => $gabarit->id]);

        $envoi = app(EnvoiDEmail::class);
        $envoi->envoyer($gabarit, 'client@brio.test', [], $regle);

        $this->assertTrue($envoi->envoyer($gabarit, 'client@brio.test', [], $regle)->parti);
        $this->assertSame(2, DB::table('email_messages')->where('to_email', 'client@brio.test')->count());
    }

    /** LE PLAFOND EST PAR DESTINATAIRE : un autre lecteur n'est pas puni pour le premier. */
    public function test_le_plafond_ne_deborde_pas_sur_un_autre_destinataire(): void
    {
        $gabarit = $this->gabarit();
        $regle = EmailSendRule::factory()->plafond(1, 24)->create(['email_template_id' => $gabarit->id]);

        $envoi = app(EnvoiDEmail::class);
        $envoi->envoyer($gabarit, 'premier@brio.test', [], $regle);

        $this->assertTrue($envoi->envoyer($gabarit, 'second@brio.test', [], $regle)->parti);
    }

    /**
     * UNE ALERTE DE FRAUDE NE SE REFUSE PAS.
     *
     * Refuser une publicite n'est pas renoncer a etre prevenu qu'on vous vole.
     */
    public function test_une_alerte_de_fraude_part_malgre_le_desabonnement(): void
    {
        $this->desabonne('client@brio.test');

        $fraude = $this->gabarit();
        $fraude->update(['code' => 'alerte_fraude', 'category' => 'fraude']);

        $this->assertTrue(app(EnvoiDEmail::class)->envoyer($fraude, 'client@brio.test')->parti);
    }

    /** TEMOIN — une campagne marketing, elle, respecte bien le desabonnement. */
    public function test_temoin_le_marketing_respecte_le_desabonnement(): void
    {
        $this->desabonne('client@brio.test');

        $campagne = $this->gabarit();
        $campagne->update(['code' => 'promo_ete', 'category' => 'marketing']);

        $resultat = app(EnvoiDEmail::class)->envoyer($campagne, 'client@brio.test');

        $this->assertFalse($resultat->parti);
        $this->assertStringContainsString('désabonné', $resultat->raison);
    }

    /**
     * COCHER « IGNORER L'OPT-OUT » NE REND PAS UNE CAMPAGNE INCONTOURNABLE.
     *
     * Seule la CATEGORIE du gabarit decide. Sans cette limite, un reglage de regle suffirait a
     * ecrire a quelqu'un qui a dit non.
     */
    public function test_une_regle_ne_peut_pas_rendre_le_marketing_incontournable(): void
    {
        $this->desabonne('client@brio.test');

        $campagne = $this->gabarit();
        $campagne->update(['code' => 'promo_ete', 'category' => 'marketing']);

        $regle = EmailSendRule::factory()->create([
            'email_template_id' => $campagne->id,
            'respects_opt_out' => false,
        ]);

        $this->assertFalse(app(EnvoiDEmail::class)->envoyer($campagne, 'client@brio.test', [], $regle)->parti);
    }

    /**
     * LE DRAPEAU NE PEUT QUE RESSERRER : une regle PEUT decider de respecter l'opt-out la ou la
     * categorie l'exempterait. C'est son seul pouvoir, et il va dans le sens protecteur.
     */
    public function test_une_regle_peut_resserrer_sur_une_categorie_exemptee(): void
    {
        $this->desabonne('client@brio.test');

        $transactionnel = $this->gabarit();

        $regle = EmailSendRule::factory()->create([
            'email_template_id' => $transactionnel->id,
            'respects_opt_out' => true,
        ]);

        $this->assertFalse(app(EnvoiDEmail::class)->envoyer($transactionnel, 'client@brio.test', [], $regle)->parti);
    }

    /** TEMOIN — la meme categorie SANS regle resserrante part bien, elle. */
    public function test_temoin_la_categorie_exemptee_part_sans_regle_resserrante(): void
    {
        $this->desabonne('client@brio.test');

        $this->assertTrue(app(EnvoiDEmail::class)->envoyer($this->gabarit(), 'client@brio.test')->parti);
    }

    /** LE MOTEUR D'AUTOMATISATION SAIT DESORMAIS ENVOYER UN E-MAIL. */
    public function test_l_action_d_envoi_figure_au_catalogue_du_moteur(): void
    {
        $this->assertArrayHasKey('email.envoyer', app(Catalogue::class)->actions());
    }

    /** UN REFUS N'EST PAS UN SUCCES : le journal du moteur doit pouvoir les distinguer. */
    public function test_l_action_echoue_quand_l_envoi_est_refuse(): void
    {
        $gabarit = $this->gabarit();
        $gabarit->update(['is_active' => false]);

        $resultat = app(EnvoyerUnEmail::class)->executer(
            Booking::factory()->create(),
            ['gabarit' => 'booking_confirmed', 'destinataire' => 'client@brio.test'],
        );

        $this->assertFalse($resultat->reussie);
    }

    /** TEMOIN — la meme action sur un gabarit actif reussit. */
    public function test_temoin_l_action_reussit_sur_un_gabarit_actif(): void
    {
        $this->gabarit();

        $resultat = app(EnvoyerUnEmail::class)->executer(
            Booking::factory()->create(),
            ['gabarit' => 'booking_confirmed', 'destinataire' => 'client@brio.test'],
        );

        $this->assertTrue($resultat->reussie, $resultat->message ?? '');
    }

    /** LE DECLENCHEUR SE LIT EN UNE PHRASE, et le signe porte l'intention. */
    public function test_un_rappel_se_lit_avant_ou_apres_selon_son_signe(): void
    {
        $avant = EmailSendRule::factory()->rappel('booking.date', -1440)->make();
        $apres = EmailSendRule::factory()->rappel('booking.date', 120)->make();

        $this->assertStringContainsString('avant', $avant->enUnePhrase());
        $this->assertStringContainsString('1 jour(s)', $avant->enUnePhrase());
        $this->assertStringContainsString('après', $apres->enUnePhrase());
        $this->assertStringContainsString('2 heure(s)', $apres->enUnePhrase());
    }

    private function gabarit(): EmailTemplate
    {
        return EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail();
    }

    /**
     * LE DESABONNEMENT SE POSE SUR UN COMPTE, PAS SUR UNE ADRESSE.
     *
     * `marketing_opt_outs` porte `user_id` et `channel`. Les deux services interrogeaient une
     * colonne `email` qui n'existe pas : sur SQLite l'identifiant inconnu devient une chaine
     * litterale et la comparaison rend toujours faux — le refus n'a jamais fonctionne.
     */
    private function desabonne(string $email): void
    {
        $compte = User::factory()->create(['email' => $email]);

        MarketingOptOut::query()->create([
            'user_id' => $compte->id,
            'channel' => MarketingOptOut::CHANNEL_EMAIL,
            'opted_out_at' => now(),
        ]);
    }
}
