<?php

namespace Tests\Feature\Admin\Console;

use App\Models\BusinessEntity;
use App\Models\ComplaintCase;
use App\Models\EnterpriseBookingApproval;
use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Les files de décision servies par le moteur. */
class DecisionResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    // ── forme commune des files de décision ─────────────────────────────────────────────────

    public static function filesDeDecision(): array
    {
        return [
            'KYC' => ['kyc'],
            'KYB' => ['kyb'],
            'approbations entreprises' => ['enterprise-approvals'],
            'litiges' => ['disputes'],
        ];
    }

    #[DataProvider('filesDeDecision')]
    public function test_une_file_de_decision_ne_se_remplit_pas_a_la_main(string $resource): void
    {
        $this->actingAsAdmin();

        // Ces dossiers naissent d'une demande extérieure. Un formulaire donnerait à l'admin le
        // moyen de fabriquer une vérification ou un litige qui n'a jamais été demandé.
        $this->assertSame([], $this->getJson("/api/admin/console/{$resource}")->json('resource.form'));

        $this->postJson("/api/admin/console/{$resource}", ['x' => 1])
            ->assertStatus(405)
            ->assertJsonPath('error', 'read_only_resource');
    }

    #[DataProvider('filesDeDecision')]
    public function test_une_file_de_decision_expose_au_moins_une_action(string $resource): void
    {
        $this->actingAsAdmin();

        $actions = $this->getJson("/api/admin/console/{$resource}")->json('resource.actions');

        // Une file sans action serait une liste morte : on la consulterait sans pouvoir décider.
        $this->assertNotEmpty($actions, "La file « {$resource} » n’offre aucune décision.");
    }

    #[DataProvider('filesDeDecision')]
    public function test_chaque_file_offre_un_refus_qui_exige_un_motif(string $resource): void
    {
        $this->actingAsAdmin();

        $actions = collect($this->getJson("/api/admin/console/{$resource}")->json('resource.actions'));
        $refus = $actions->first(fn (array $a) => in_array($a['key'], ['reject', 'dismiss'], true));

        $this->assertNotNull($refus, "La file « {$resource} » n’offre aucun refus.");

        // Un refus sans explication écrite n'est ni contestable par la personne concernée, ni
        // auditable ensuite. L'action DÉCLARE le motif qu'elle exige, et le moteur le demande.
        $motif = collect($refus['fields'])->firstWhere('key', 'reason');
        $this->assertNotNull($motif, "Le refus de « {$resource} » ne demande aucun motif.");
        $this->assertTrue($motif['required']);
        $this->assertTrue($refus['destructive']);
        $this->assertNotEmpty($refus['confirm']);
    }

    #[DataProvider('filesDeDecision')]
    public function test_un_refus_sans_motif_est_rejete_par_le_serveur(string $resource): void
    {
        $this->actingAsAdmin();

        $modele = $this->uneLigneDe($resource);

        // « court » fait cinq caractères, la règle en exige dix. Le mobile dessine la feuille ;
        // c'est le SERVEUR qui tient la règle — sans cette validation, une application modifiée
        // pourrait refuser sans rien écrire.
        $this->postJson("/api/admin/console/{$resource}/{$modele}/actions/reject", ['reason' => 'court'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['reason']]);
    }

    /** Crée une ligne du domaine et rend son identifiant. */
    private function uneLigneDe(string $resource): int
    {
        return match ($resource) {
            'kyc' => KycVerification::factory()->create()->id,
            'kyb' => BusinessEntity::factory()->create()->id,
            'enterprise-approvals' => EnterpriseBookingApproval::factory()->create()->id,
            'disputes' => ComplaintCase::factory()->create()->id,
        };
    }

    // ── KYC ─────────────────────────────────────────────────────────────────────────────────

    public function test_la_validation_kyc_passe_par_le_service(): void
    {
        $admin = $this->actingAsAdmin();
        $verification = KycVerification::factory()->create([
            'status' => KycVerification::STATUS_IN_REVIEW,
        ]);

        $this->postJson("/api/admin/console/kyc/{$verification->id}/actions/approve")->assertOk();

        $frais = $verification->fresh();
        $this->assertSame(KycVerification::STATUS_CLEAR, $frais->status);
        // La trace de revue est l'effet de bord du service : elle prouve la délégation.
        $this->assertSame($admin->id, $frais->reviewed_by_user_id);
        $this->assertNotNull($frais->reviewed_at);
    }

    public function test_le_filtre_a_traiter_kyc_reprend_le_scope_du_modele(): void
    {
        $this->actingAsAdmin();
        KycVerification::factory()->create(['status' => KycVerification::STATUS_IN_REVIEW]);
        KycVerification::factory()->create(['status' => KycVerification::STATUS_CLEAR]);

        $rows = $this->getJson('/api/admin/console/kyc?filters[a_traiter]=1')->assertOk()->json('rows');

        $this->assertCount(1, $rows);
        $this->assertSame(KycVerification::STATUS_IN_REVIEW, $rows[0]['status']);
    }

    // ── KYB ─────────────────────────────────────────────────────────────────────────────────

    public function test_l_approbation_kyb_passe_par_le_service(): void
    {
        $this->actingAsAdmin();
        $entite = BusinessEntity::factory()->create(['status' => BusinessEntity::STATUS_PENDING]);

        $this->postJson("/api/admin/console/kyb/{$entite->id}/actions/approve")->assertOk();

        $frais = $entite->fresh();
        $this->assertSame(BusinessEntity::STATUS_VERIFIED, $frais->status);
        $this->assertNotNull($frais->verified_at);
    }

    // ── Litiges ─────────────────────────────────────────────────────────────────────────────

    public function test_l_escalade_d_un_litige_passe_par_le_service(): void
    {
        $this->actingAsAdmin();
        $litige = ComplaintCase::factory()->create(['status' => ComplaintCase::STATUS_OPEN]);

        $this->postJson("/api/admin/console/disputes/{$litige->id}/actions/escalate")->assertOk();

        $this->assertSame(ComplaintCase::STATUS_ESCALATED, $litige->fresh()->status);
    }

    public function test_un_refus_motive_est_delegue_au_service(): void
    {
        $this->actingAsAdmin();
        $verification = KycVerification::factory()->create([
            'status' => KycVerification::STATUS_IN_REVIEW,
        ]);

        $this->postJson("/api/admin/console/kyc/{$verification->id}/actions/reject", [
            'reason' => 'Document illisible : la date de naissance n’apparaît pas.',
        ])->assertOk();

        $frais = $verification->fresh();
        $this->assertSame(KycVerification::STATUS_REJECTED, $frais->status);
        // Le motif est CONSERVÉ : c'est ce qui rend le refus contestable.
        $this->assertStringContainsString('illisible', (string) $frais->rejection_reason);
    }

    public function test_l_escalade_est_annoncee_comme_destructive(): void
    {
        $this->actingAsAdmin();

        $escalade = collect($this->getJson('/api/admin/console/disputes')->json('resource.actions'))
            ->firstWhere('key', 'escalate');

        $this->assertTrue($escalade['destructive']);
        $this->assertNotEmpty($escalade['confirm']);
    }

    public function test_le_filtre_des_litiges_en_retard_ignore_les_dossiers_clos(): void
    {
        $this->actingAsAdmin();
        ComplaintCase::factory()->create([
            'status' => ComplaintCase::STATUS_OPEN,
            'due_at' => now()->subDay(),
        ]);
        ComplaintCase::factory()->create([
            'status' => ComplaintCase::STATUS_CLOSED,
            'due_at' => now()->subWeek(),
        ]);

        $rows = $this->getJson('/api/admin/console/disputes?filters[en_retard]=1')->assertOk()->json('rows');

        // Un dossier clos en retard n'appelle aucune action : le compter noierait ceux qui en
        // appellent une.
        $this->assertCount(1, $rows);
        $this->assertSame(ComplaintCase::STATUS_OPEN, $rows[0]['status']);
    }

    // ── cohérence avec l'annuaire ───────────────────────────────────────────────────────────

    public function test_les_quatre_modules_sont_annonces_disponibles(): void
    {
        $this->actingAsAdmin();

        $modules = collect($this->getJson('/api/admin/catalog')->json('groups'))
            ->flatMap(fn (array $g) => $g['modules'])
            ->keyBy('key');

        $enRetard = [];

        foreach (['kyc', 'kyb', 'enterprise-approvals', 'disputes'] as $cle) {
            $couverture = $modules[$cle]['coverage'] ?? 'ABSENT';

            if ($couverture !== 'descriptor') {
                $enRetard[] = "{$cle} : annoncé « {$couverture} »";
            }
        }

        $this->assertSame([], $enRetard, 'Ces modules de décision sont livrés mais annoncés autrement.');
    }
}
