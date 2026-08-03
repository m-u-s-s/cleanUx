<?php

namespace Tests\Feature\Admin\Console;

use App\Models\FeatureFlagOverride;
use App\Models\PromoCode;
use App\Models\ProviderBadge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Le lot « création simple et bascules ».
 *
 * Ce que ces trois domaines ont en commun : ils se créent depuis la console, et ils ne se
 * SUPPRIMENT pas — on suspend, on désactive. Les rachats, attributions et historiques déjà posés
 * pointent sur ces lignes ; les effacer laisserait des références que plus rien n'explique.
 */
class CreationResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    public static function domainesDeCreation(): array
    {
        return [
            'codes promo' => ['promo-codes'],
            'badges' => ['badges'],
            'feature flags' => ['feature-flags'],
        ];
    }

    #[DataProvider('domainesDeCreation')]
    public function test_un_domaine_de_creation_offre_un_formulaire(string $resource): void
    {
        $this->actingAsAdmin();

        $this->assertNotEmpty($this->getJson("/api/admin/console/{$resource}")->json('resource.form'));
    }

    #[DataProvider('domainesDeCreation')]
    public function test_chaque_bascule_destructive_dit_ce_qu_elle_change(string $resource): void
    {
        $this->actingAsAdmin();

        $actions = $this->getJson("/api/admin/console/{$resource}")->json('resource.actions');
        $destructives = array_filter($actions, fn (array $a) => $a['destructive']);

        $this->assertNotEmpty($destructives, "« {$resource} » n’a aucune bascule d’arrêt.");

        foreach ($destructives as $action) {
            // Une confirmation vide se valide sans qu'on sache ce qu'on arrête.
            $this->assertNotEmpty($action['confirm'], "L’action {$action['key']} ne dit pas ce qu’elle change.");
        }
    }

    // ── codes promo ─────────────────────────────────────────────────────────────────────────

    public function test_un_code_promo_se_cree_puis_se_suspend_sans_disparaitre(): void
    {
        $this->actingAsAdmin();

        $res = $this->postJson('/api/admin/console/promo-codes', [
            'code' => 'BIENVENUE10',
            'name' => 'Bienvenue 10 %',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
        ])->assertStatus(201);

        $id = $res->json('row.id');

        $this->postJson("/api/admin/console/promo-codes/{$id}/actions/pause")->assertOk();

        // Suspendu, pas supprimé : les rachats déjà consentis pointent sur cette ligne.
        $code = PromoCode::find($id);
        $this->assertNotNull($code);
        $this->assertSame(PromoCode::STATUS_PAUSED, $code->status);
    }

    public function test_un_code_promo_refuse_une_fin_de_validite_anterieure_a_son_debut(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/console/promo-codes', [
            'code' => 'INCOHERENT',
            'name' => 'Incohérent',
            'discount_type' => 'fixed_amount',
            'discount_value' => 5,
            'status' => PromoCode::STATUS_DRAFT,
            'valid_from' => '2026-09-01',
            'valid_until' => '2026-08-01',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['valid_until']]);
    }

    public function test_le_filtre_utilisables_ecarte_les_codes_expires_et_suspendus(): void
    {
        $this->actingAsAdmin();

        PromoCode::factory()->create([
            'code' => 'UTILISABLE',
            'status' => PromoCode::STATUS_ACTIVE,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);
        PromoCode::factory()->create([
            'code' => 'PERIME',
            'status' => PromoCode::STATUS_ACTIVE,
            'valid_until' => now()->subDay(),
        ]);
        PromoCode::factory()->create([
            'code' => 'SUSPENDU',
            'status' => PromoCode::STATUS_PAUSED,
            'valid_until' => now()->addMonth(),
        ]);

        $rows = $this->getJson('/api/admin/console/promo-codes?filters[utilisables]=1')
            ->assertOk()->json('rows');

        $this->assertSame(['UTILISABLE'], array_column($rows, 'code'));
    }

    // ── badges ──────────────────────────────────────────────────────────────────────────────

    public function test_un_badge_se_desactive_sans_effacer_ceux_deja_decernes(): void
    {
        $this->actingAsAdmin();
        $badge = ProviderBadge::factory()->create(['is_active' => true]);

        $this->postJson("/api/admin/console/badges/{$badge->id}/actions/deactivate")->assertOk();

        $frais = ProviderBadge::find($badge->id);
        $this->assertNotNull($frais, 'La définition du badge doit survivre à sa désactivation.');
        $this->assertFalse((bool) $frais->is_active);
    }

    public function test_un_badge_exige_un_critere_et_un_seuil(): void
    {
        $this->actingAsAdmin();

        // Un badge sans critère ne serait jamais décerné : le moteur d'évaluation n'aurait rien
        // à comparer, et personne ne verrait qu'il ne se passe rien.
        $this->postJson('/api/admin/console/badges', ['code' => 'X', 'name' => 'Sans critère'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['tier', 'criterion_type', 'threshold']]);
    }

    // ── feature flags ───────────────────────────────────────────────────────────────────────

    public function test_une_derogation_exige_un_motif_ecrit(): void
    {
        $this->actingAsAdmin();

        // « test » n'explique rien à qui relit dans six mois : la dérogation deviendrait
        // permanente par peur d'y toucher, pas par décision.
        $this->postJson('/api/admin/console/feature-flags', [
            'flag_key' => 'nouvelle-recherche',
            'is_enabled' => true,
            'reason' => 'test',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['reason']]);
    }

    public function test_une_derogation_enregistre_qui_l_a_basculee(): void
    {
        $admin = $this->actingAsAdmin();
        $drapeau = FeatureFlagOverride::factory()->create(['is_enabled' => false]);

        $this->postJson("/api/admin/console/feature-flags/{$drapeau->id}/actions/enable")->assertOk();

        $frais = $drapeau->fresh();
        $this->assertTrue((bool) $frais->is_enabled);
        // Une dérogation qui change le comportement de la plateforme sans laisser de nom n'est
        // pas rattrapable.
        $this->assertSame($admin->id, $frais->updated_by_user_id);
    }

    // ── cohérence avec l'annuaire ───────────────────────────────────────────────────────────

    public function test_l_annuaire_annonce_dix_modules_disponibles(): void
    {
        $this->actingAsAdmin();

        $counts = $this->getJson('/api/admin/catalog')->assertOk()->json('counts');

        $this->assertSame(10, $counts['covered']);
        $this->assertSame($counts['total'] - 10, $counts['pending']);
    }
}
