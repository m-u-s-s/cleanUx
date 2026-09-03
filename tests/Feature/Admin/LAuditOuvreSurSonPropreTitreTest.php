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
            'heros de gouvernance' => ['Sécurité, audit et préparation production', null,
                'livewire.admin.governance.hero'],
            'preparation production' => ['Centre de préparation production', 'livewire.admin.readiness.hero', null],
            'communication' => ['Centre de communication & suivi qualité', 'livewire.shared.communication.hero', null],
            'pilotage' => ['Pilotage opérationnel & qualité plateforme', null,
                'livewire.admin.pilotage.phase2s-banner'],
        ];
    }

    #[DataProvider('blocsRetires')]
    public function test_la_page_n_ouvre_plus_sur_ce_bloc(string $phrase, ?string $gabarit, ?string $disparu): void
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
    public function test_temoin_la_phrase_est_bien_celle_du_gabarit(string $phrase, ?string $gabarit, ?string $disparu): void
    {
        if ($gabarit === null) {
            // Le bloc a quitte le produit : c'est LA DISPARITION DE SON PROPRE GABARIT qui est
            // verifiee. Verifier celui d'un voisin serait un temoin qui mesure autre chose.
            $this->assertFalse(view()->exists((string) $disparu),
                "Le gabarit {$disparu} existe encore alors que plus aucune vue ne l’inclut.");

            return;
        }

        $this->assertStringContainsString($phrase, view($gabarit)->render());
    }

    /** TEMOIN — le bloc encore vivant reste VISIBLE en HTTP ; celui qui est parti a quitte le disque. */
    public function test_temoin_chaque_bloc_est_verifie_la_ou_il_est(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/platform-readiness')
            ->assertOk()
            ->assertSee('Centre de préparation production', false);

        // L EMPILEMENT DE PILOTAGE A QUITTE LE PRODUIT le 2026-09-03, avec sa derniere page
        // porteuse. Le temoin ne peut plus etre une page : c'est la disparition du gabarit qui
        // garantit desormais que la phrase cherchee etait bien la sienne.
        $this->assertFalse(view()->exists('livewire.admin.pilotage.phase2s-banner'),
            'Le bandeau de pilotage existe encore alors que plus aucune vue ne l’inclut.');
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
