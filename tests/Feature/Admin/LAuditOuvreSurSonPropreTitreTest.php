<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LE CENTRE D'AUDIT OUVRE SUR SON PROPRE TITRE.
 *
 * Quatre blocs passaient avant : un heros de gouvernance, puis les empilements « preparation
 * production », « communication » et « pilotage ». Aucun ne portait de donnee — heros, tuiles
 * chiffrees en dur et memos de process — et le titre de la page arrivait en cinquieme position.
 *
 * Les dix liens qu'ils portaient figurent tous au catalogue des modules : rien n'est devenu
 * injoignable. Le heros de gouvernance, lui, n'avait plus d'appelant et a ete supprime.
 */
class LAuditOuvreSurSonPropreTitreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les esperluettes ne sont PAS echappees dans les gabarits, d'ou le `false` des assertions :
     * `assertSee` echappe par defaut et chercherait `&amp;`.
     *
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function blocsRetires(): array
    {
        return [
            'heros de gouvernance' => ['Sécurité, audit et préparation production', null],
            'preparation production' => ['Centre de préparation production', 'livewire.admin.readiness.hero'],
            'communication' => ['Centre de communication & suivi qualité', 'livewire.shared.communication.hero'],
            'pilotage' => ['Pilotage opérationnel & qualité plateforme', 'livewire.admin.pilotage.phase2s-banner'],
        ];
    }

    #[DataProvider('blocsRetires')]
    public function test_la_page_n_ouvre_plus_sur_ce_bloc(string $phrase, ?string $gabarit): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/audit/logs')
            ->assertOk()
            ->assertDontSee($phrase, false);
    }

    /**
     * TEMOIN — la phrase cherchee est bien celle du gabarit, a l'accent et a l'esperluette pres.
     *
     * Sans lui, chaque refus ci-dessus passerait au vert sur une faute de frappe : il mesurerait
     * sa propre erreur au lieu du retrait.
     */
    #[DataProvider('blocsRetires')]
    public function test_temoin_la_phrase_est_bien_celle_du_gabarit(string $phrase, ?string $gabarit): void
    {
        if ($gabarit === null) {
            // Le heros de gouvernance n'a plus d'appelant : c'est sa disparition qui est verifiee.
            $this->assertFalse(view()->exists('livewire.admin.governance.hero'),
                'Le heros de gouvernance existe encore alors que plus aucune vue ne l’inclut.');

            return;
        }

        $this->assertStringContainsString($phrase, view($gabarit)->render());
    }

    /** TEMOIN — deux de ces blocs restent VISIBLES en HTTP la ou ils sont encore inclus. */
    public function test_temoin_les_blocs_restent_visibles_sur_le_tableau_de_bord(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/business-dashboard')
            ->assertOk()
            ->assertSee('Centre de préparation production', false)
            ->assertSee('Pilotage opérationnel & qualité plateforme', false);
    }

    /** TEMOIN — la page rend son propre contenu : le retrait n'a pas casse sa racine unique. */
    public function test_temoin_la_page_ouvre_sur_son_titre(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/audit/logs')
            ->assertOk()
            ->assertSee('Centre d’audit et logs', false)
            ->assertSee('Filtres d’audit', false);
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
