<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LE CENTRE E-MAILS PERD L'EMPILEMENT DE PILOTAGE — ET IL ETAIT LE DERNIER A LE PORTER.
 *
 * Trois blocs passaient entre le bandeau de communication et le contenu de la page : « Pilotage
 * operationnel & qualite plateforme », dont les quatre tuiles etaient figees dans le gabarit,
 * quatre cartes de raccourcis, et une « Checklist go-live » sans etat.
 *
 * Cette page etant le dernier porteur, les quatre gabarits de `pilotage/` ont quitte le depot. Les
 * quatre liens des raccourcis figuraient tous au catalogue des modules : rien n'est injoignable.
 */
class LesEmailsOuvrentSurLeurPropreTitreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * L'esperluette n'est pas echappee dans les gabarits, d'ou le `false` des assertions.
     *
     * @return array<string, array{0: string}>
     */
    public static function blocsRetires(): array
    {
        return [
            'bandeau de pilotage' => ['Pilotage opérationnel & qualité plateforme'],
            'cartes de raccourcis' => ['Suivre les indicateurs de performance.'],
            'checklist go-live' => ['Checklist go-live'],
        ];
    }

    #[DataProvider('blocsRetires')]
    public function test_la_page_n_ouvre_plus_sur_ce_bloc(string $phrase): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/emails')
            ->assertOk()
            ->assertDontSee($phrase, false);
    }

    /**
     * TEMOIN — LE BANDEAU DE COMMUNICATION RESTE, LUI.
     *
     * C'est le controle qui distingue « le bon empilement est parti » de « la page a ete videe ».
     * Sans lui, les refus ci-dessus passeraient au vert sur un ecran devenu blanc.
     */
    public function test_temoin_le_bandeau_de_communication_reste(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/emails')
            ->assertOk()
            ->assertSee('Centre de communication & suivi qualité', false);
    }

    /** TEMOIN — la page rend son propre contenu : le retrait n'a pas casse sa racine unique. */
    public function test_temoin_la_page_rend_son_contenu(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/emails')
            ->assertOk()
            ->assertSee('Emails produit', false);
    }

    /** Les quatre gabarits de `pilotage/` ont quitte le depot avec leur dernier porteur. */
    public function test_les_gabarits_de_pilotage_ont_quitte_le_depot(): void
    {
        foreach ([
            'livewire.admin.pilotage.layout-stack',
            'livewire.admin.pilotage.phase2s-banner',
            'livewire.admin.pilotage.quick-actions',
            'livewire.admin.pilotage.go-live-checklist',
        ] as $gabarit) {
            $this->assertFalse(view()->exists($gabarit),
                "Le gabarit {$gabarit} existe encore alors que plus aucune vue ne l’inclut.");
        }
    }

    /**
     * TEMOIN — le controle ci-dessus sait reconnaitre un gabarit QUI EXISTE.
     *
     * Sans lui, `view()->exists()` pourrait rendre faux pour une mauvaise raison — un chemin mal
     * ecrit, par exemple — et la boucle passerait au vert en mesurant sa propre faute de frappe.
     */
    public function test_temoin_le_controle_reconnait_un_gabarit_vivant(): void
    {
        $this->assertTrue(view()->exists('livewire.shared.communication.hero'),
            'Le controle ne voit plus un gabarit pourtant present : il ne prouve plus rien.');
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
