<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationReevaluation;
use App\Services\Automation\FileDeReevaluation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_la_file_se_lit_groupee_par_evenement(): void
    {
        $this->file()->deposer('alerte.payout_failed', 'alerte', 7);
        $this->file()->deposer('alerte.payout_failed', 'alerte', 9);
        $this->file()->deposer('booking.annulee', 'booking', 3);

        $lue = $this->file()->parEvenement();

        $this->assertSame(['alerte.payout_failed', 'booking.annulee'], array_keys($lue));
        $this->assertSame([7, 9], $lue['alerte.payout_failed']['identifiants']);
        $this->assertSame('alerte', $lue['alerte.payout_failed']['entite']);
        $this->assertSame([3], $lue['booking.annulee']['identifiants']);
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
}
