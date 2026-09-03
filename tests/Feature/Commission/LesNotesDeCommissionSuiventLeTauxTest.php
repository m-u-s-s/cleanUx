<?php

namespace Tests\Feature\Commission;

use App\Livewire\PeerRental\PeerStayEditor;
use App\Models\CommissionRule;
use App\Models\PeerStay;
use App\Models\User;
use App\Services\Commission\GestionDesCommissions;
use App\Services\Commission\ResolveurDeCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LES NOTES AFFICHENT LE TAUX QUI S'APPLIQUE, PAS CELUI QU'ON CROIT.
 *
 * Une note qui recopie un chiffre ment le jour où le taux change — et c'est précisément le
 * défaut que ce socle existe pour supprimer. Le seul test qui vaille est donc celui-ci :
 * CHANGER LE TAUX, ET REGARDER LA PAGE.
 *
 * Chaque cas porte son témoin : une note qui n'afficherait jamais rien passerait au vert sur un
 * `assertDontSee`, et ne prouverait aucune des deux choses.
 */
class LesNotesDeCommissionSuiventLeTauxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'brio.platform_fee_percent' => 15,
            'brio.minimum_commission_cents' => 200,
            'peer_rental.commission_percent' => 25,
        ]);

        app(ResolveurDeCommission::class)->oublierLeCache();
    }

    /** SANS RÈGLE, la note dit le taux d'origine du module. */
    public function test_la_note_annonce_le_taux_d_origine(): void
    {
        $rendu = Blade::render('<x-note-commission />');

        $this->assertStringContainsString('15 %', $rendu);
        $this->assertStringContainsString('taux par défaut de la plateforme', $rendu);
    }

    /**
     * LE CAS QUI COMPTE : on change le taux, la note change.
     *
     * C'est la seule preuve que la note lit le résolveur au lieu de recopier un chiffre.
     */
    public function test_la_note_suit_le_taux_quand_il_change(): void
    {
        $avant = Blade::render('<x-note-commission />');
        $this->assertStringContainsString('15 %', $avant);

        app(GestionDesCommissions::class)->creer($this->titulaire(), [
            'label' => 'Course à 8 %', 'module' => CommissionRule::MODULE_PRESTATION, 'percent' => 8,
        ]);

        $apres = Blade::render('<x-note-commission />');

        $this->assertStringContainsString('8 %', $apres);
        $this->assertStringContainsString('Course à 8 %', $apres);
        $this->assertStringNotContainsString('15 %', $apres);
    }

    /** LA GRATUITÉ SE DIT AUTREMENT : « 0 % » et « aucune commission » ne sont pas la même nouvelle. */
    public function test_la_note_annonce_la_gratuite_en_toutes_lettres(): void
    {
        app(GestionDesCommissions::class)->creer($this->titulaire(), [
            'label' => 'Lancement offert', 'module' => CommissionRule::MODULE_PRESTATION,
            'percent' => 0, 'min_cents' => 0,
        ]);

        $rendu = Blade::render('<x-note-commission />');

        $this->assertStringContainsString('Aucune commission', $rendu);
        $this->assertStringContainsString('Lancement offert', $rendu);
    }

    /**
     * L'EXEMPLE CHIFFRÉ PASSE PAR LE MÊME PARTAGE QUE L'ARGENT RÉEL.
     *
     * Le recalculer à la main dans la vue ferait diverger la note et la facture au premier
     * plancher de commission.
     */
    public function test_l_exemple_chiffre_dit_ce_que_le_prestataire_recoit(): void
    {
        $rendu = Blade::render('<x-note-commission :montant-cents="10000" />');

        $this->assertStringContainsString('100,00 €', $rendu);
        $this->assertStringContainsString('85,00 €', $rendu);
    }

    /** TÉMOIN — à 8 %, le même exemple annonce 92 €, pas 85 €. */
    public function test_temoin_l_exemple_suit_le_taux_lui_aussi(): void
    {
        app(GestionDesCommissions::class)->creer($this->titulaire(), [
            'label' => 'Course', 'module' => CommissionRule::MODULE_PRESTATION, 'percent' => 8,
        ]);

        $rendu = Blade::render('<x-note-commission :montant-cents="10000" />');

        $this->assertStringContainsString('92,00 €', $rendu);
        $this->assertStringNotContainsString('85,00 €', $rendu);
    }

    /** LE PLANCHER SE DIT QUAND IL MORD : sinon le taux affiché ment sur les petits montants. */
    public function test_la_note_avoue_le_plancher_quand_il_mord(): void
    {
        // 15 % de 5 € font 0,75 € ; le plancher de 2 € prend le dessus.
        $rendu = Blade::render('<x-note-commission :montant-cents="500" />');

        $this->assertStringContainsString('plancher de commission', $rendu);
        $this->assertStringContainsString('40 %', $rendu);
    }

    /** TÉMOIN — sur un montant où le plancher ne mord pas, la note n'en parle pas. */
    public function test_temoin_sans_plancher_qui_mord_la_note_n_en_parle_pas(): void
    {
        $rendu = Blade::render('<x-note-commission :montant-cents="10000" />');

        $this->assertStringNotContainsString('plancher de commission', $rendu);
    }

    /** CHAQUE MODULE A SON TAUX : la note d'un logement ne dit pas celui d'une mission. */
    public function test_la_note_distingue_les_modules(): void
    {
        $rendu = Blade::render(
            '<x-note-commission :module="\'peer_rental\'" type-de-bien="stay" />'
        );

        $this->assertStringContainsString('25 %', $rendu);
    }

    /** LA NOTE EST BIEN RENDUE PAR L'ÉCRAN, pas seulement par le composant isolé. */
    public function test_l_editeur_de_logement_affiche_la_note(): void
    {
        app(GestionDesCommissions::class)->creer($this->titulaire(), [
            'label' => 'Logements à 12 %', 'module' => CommissionRule::MODULE_LOCATION_MEMBRES,
            'asset_type' => 'stay', 'percent' => 12,
        ]);

        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->assertSee('Logements à 12 %')
            ->assertSee('12 %');
    }

    /** TÉMOIN — sans règle, le même écran annonce le taux d'origine. */
    public function test_temoin_sans_regle_l_editeur_annonce_le_taux_d_origine(): void
    {
        $logement = PeerStay::factory()->create();

        Livewire::actingAs($logement->owner)
            ->test(PeerStayEditor::class, ['stay' => $logement])
            ->assertSee('25 %');
    }

    private function titulaire(): User
    {
        return $this->prendreLeSiege(['role' => 'admin']);
    }
}
