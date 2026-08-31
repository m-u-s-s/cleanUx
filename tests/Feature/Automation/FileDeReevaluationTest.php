<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationReevaluation;
use App\Services\Automation\FileDeReevaluation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FileDeReevaluationTest extends TestCase
{
    use RefreshDatabase;

    private function file(): FileDeReevaluation
    {
        return app(FileDeReevaluation::class);
    }

    /** DEUX FOIS LE MEME DEPOT = UNE LIGNE, et le second dit `false` sans lever. */
    public function test_un_depot_en_double_ne_leve_pas_et_ne_duplique_pas(): void
    {
        $this->assertTrue($this->file()->deposer('alerte.payout_failed', 'alerte', 7));
        $this->assertFalse($this->file()->deposer('alerte.payout_failed', 'alerte', 7));

        $this->assertSame(1, AutomationReevaluation::count());
    }

    /** TEMOIN — un depot NEUF rend bien `true` et ecrit. Sans lui, le test ci-dessus
     *  resterait vert si `deposer()` ne faisait jamais rien. */
    public function test_temoin_un_depot_neuf_ecrit_et_rend_vrai(): void
    {
        $this->assertTrue($this->file()->deposer('alerte.payout_failed', 'alerte', 7));
        $this->assertTrue($this->file()->deposer('alerte.payout_failed', 'alerte', 8));

        $this->assertSame(2, AutomationReevaluation::count());
    }

    /** Un identifiant absent n'entre PAS dans la file : rien a reevaluer. */
    public function test_un_identifiant_nul_n_est_pas_depose(): void
    {
        $this->assertFalse($this->file()->deposer('alerte.payout_failed', 'alerte', null));

        $this->assertSame(0, AutomationReevaluation::count());
    }

    /** Le garde promet de ne pas MEME ESSAYER : sans lui, l'insert part et c'est SQLite qui le
     *  refuse (NOT NULL, meme SQLSTATE 23000 que le doublon) — `DB::listen` ne le voit pas, car
     *  Laravel ne journalise que les requetes qui aboutissent ; l'evenement `creating`, lui,
     *  se declenche avant l'appel SQL, qu'il reussisse ou non. */
    public function test_un_identifiant_nul_ne_touche_pas_la_base(): void
    {
        $tentatives = 0;
        AutomationReevaluation::creating(function () use (&$tentatives): void {
            $tentatives++;
        });

        $this->assertFalse($this->file()->deposer('alerte.payout_failed', 'alerte', null));

        $this->assertSame(0, $tentatives, 'Le garde laisse partir une tentative d\'ecriture pour rien.');
    }

    /**
     * C2 — `parEvenement()` rend une LISTE de groupes, chacun portant son evenement ET
     * son entite : plus de cle associative par seul evenement, qui masquait l'entite.
     */
    public function test_la_file_se_lit_groupee_par_evenement(): void
    {
        $this->file()->deposer('alerte.payout_failed', 'alerte', 7);
        $this->file()->deposer('alerte.payout_failed', 'alerte', 9);
        $this->file()->deposer('booking.annulee', 'booking', 3);

        $lue = $this->file()->parEvenement();

        $this->assertCount(2, $lue);
        $this->assertSame('alerte.payout_failed', $lue[0]['evenement']);
        $this->assertSame('alerte', $lue[0]['entite']);
        $this->assertSame([7, 9], $lue[0]['identifiants']);
        $this->assertSame('booking.annulee', $lue[1]['evenement']);
        $this->assertSame('booking', $lue[1]['entite']);
        $this->assertSame([3], $lue[1]['identifiants']);

        // `lignes` doit porter les vrais identifiants de LIGNES DE FILE (id), pas les
        // identifiants d'entite : la tache 8 purge sur ceux-la, jamais sur les autres.
        $idsAlerte = AutomationReevaluation::where('evenement', 'alerte.payout_failed')->orderBy('id')->pluck('id')->all();
        $idsBooking = AutomationReevaluation::where('evenement', 'booking.annulee')->orderBy('id')->pluck('id')->all();
        $this->assertSame($idsAlerte, $lue[0]['lignes']);
        $this->assertSame($idsBooking, $lue[1]['lignes']);
    }

    /** DEFAUT C2 — `entite_type` est ecrite par LIGNE : deux depots du meme evenement sur
     *  deux entites doivent rendre DEUX groupes, jamais l'identifiant de l'un chez l'autre. */
    public function test_deux_entites_pour_le_meme_evenement_font_deux_groupes(): void
    {
        $this->file()->deposer('alerte.webhook_backlog', 'alerte', 11);
        $this->file()->deposer('alerte.webhook_backlog', 'booking', 22);

        $lue = $this->file()->parEvenement();

        $this->assertCount(2, $lue);

        $parEntite = collect($lue)->keyBy('entite');
        $this->assertSame('alerte.webhook_backlog', $parEntite['alerte']['evenement']);
        $this->assertSame([11], $parEntite['alerte']['identifiants']);
        $this->assertSame('alerte.webhook_backlog', $parEntite['booking']['evenement']);
        $this->assertSame([22], $parEntite['booking']['identifiants']);
    }

    /** TEMOIN — meme evenement, MEME entite : un seul groupe, deux identifiants. */
    public function test_temoin_meme_evenement_et_meme_entite_font_un_seul_groupe(): void
    {
        $this->file()->deposer('alerte.webhook_backlog', 'alerte', 11);
        $this->file()->deposer('alerte.webhook_backlog', 'alerte', 22);

        $lue = $this->file()->parEvenement();

        $this->assertCount(1, $lue);
        $this->assertSame([11, 22], $lue[0]['identifiants']);
    }

    /** TEMOIN DU RATTRAPAGE — une panne qui n'est PAS un doublon doit remonter. Sans lui,
     *  un `catch` trop large ferait taire une table absente et la file cesserait de se
     *  remplir sans que rien ne le dise. */
    public function test_une_panne_qui_n_est_pas_un_doublon_remonte(): void
    {
        Schema::drop('automation_reevaluations');

        $this->expectException(QueryException::class);

        $this->file()->deposer('alerte.payout_failed', 'alerte', 7);
    }

    public function test_la_purge_ne_retire_que_les_lignes_nommees(): void
    {
        $this->file()->deposer('alerte.payout_failed', 'alerte', 7);
        $this->file()->deposer('booking.annulee', 'booking', 3);

        $garder = AutomationReevaluation::where('evenement', 'booking.annulee')->value('id');
        $retirer = AutomationReevaluation::where('evenement', 'alerte.payout_failed')->pluck('id')->all();

        $this->file()->purger($retirer);

        $this->assertSame([$garder], AutomationReevaluation::pluck('id')->all());
    }

    /** Le court-circuit evite une requete inutile sur une liste vide. */
    public function test_purger_une_liste_vide_ne_touche_pas_la_base(): void
    {
        $requetes = 0;
        DB::listen(function () use (&$requetes): void {
            $requetes++;
        });

        $this->file()->purger([]);

        $this->assertSame(0, $requetes, 'purger([]) ne devrait emettre aucune requete.');
    }
}
