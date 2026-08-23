<?php

namespace Tests\Feature\FaceCheck;

use App\Models\PlatformModule;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceIncident;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use Database\Seeders\PlatformModuleSeeder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Le socle : les trois tables existent, les colonnes de garde ne sont pas assignables en masse, et le chiffré au repos fait bien un aller-retour. */
class SocleDuControleFacialTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_trois_tables_existent_avec_leurs_colonnes_de_garde(): void
    {
        $this->assertTrue(Schema::hasTable('provider_face_profiles'));
        $this->assertTrue(Schema::hasTable('provider_face_checks'));
        $this->assertTrue(Schema::hasTable('provider_face_incidents'));

        $this->assertTrue(Schema::hasColumns('provider_face_profiles', [
            'user_id', 'status', 'reference_path', 'reference_hash', 'consent_given_at',
            'consent_version', 'consent_withdrawn_at', 'id_document_id', 'id_match_status',
            'id_match_score', 'next_check_due_at', 'consecutive_failures', 'blocked_at',
            'block_reason', 'metadata',
        ]));

        $this->assertTrue(Schema::hasColumns('provider_face_checks', [
            'user_id', 'provider_face_profile_id', 'triggered_by', 'status', 'decision_source',
            'score', 'liveness_result', 'selfie_path', 'selfie_purged_at', 'attempt_number',
            'requested_at', 'expires_at', 'failure_reason', 'raw',
        ]));

        $this->assertTrue(Schema::hasColumns('provider_face_incidents', [
            'user_id', 'provider_face_check_id', 'type', 'severity', 'status', 'message',
            'diagnostics', 'occurrence_count', 'resolution',
        ]));

        $this->assertTrue(Schema::hasColumn('trades', 'requires_face_check'));
    }

    /** TÉMOIN POSITIF DE L'ASSIGNATION DE MASSE. */
    public function test_les_colonnes_ordinaires_sont_bien_assignables_en_masse(): void
    {
        $user = User::factory()->create();

        $profil = ProviderFaceProfile::create([
            'user_id' => $user->id,
            'consent_version' => '1.0',
            'reference_hash' => str_repeat('b', 64),
        ]);

        $this->assertSame('1.0', $profil->consent_version);
        $this->assertSame(str_repeat('b', 64), $profil->reference_hash);
    }

    /** Le dépôt active le refus EXPLICITE hors production : une colonne gardée ne se contente pas d'être écartée en silence, elle lève. */
    public function test_les_colonnes_de_garde_ne_sont_pas_assignables_en_masse(): void
    {
        $user = User::factory()->create();

        $this->expectException(MassAssignmentException::class);

        ProviderFaceProfile::create([
            'user_id' => $user->id,
            // Toutes refusées : ce sont elles qui portent la garde.
            'status' => ProviderFaceProfile::STATUS_ENROLLED,
            'next_check_due_at' => now()->addYears(10),
            'consecutive_failures' => 99,
        ]);
    }

    public function test_le_verdict_dun_controle_nest_pas_assignable_en_masse(): void
    {
        $profil = ProviderFaceProfile::factory()->enrolled()->create();

        $this->expectException(MassAssignmentException::class);

        ProviderFaceCheck::create([
            'user_id' => $profil->user_id,
            'provider_face_profile_id' => $profil->id,
            'triggered_by' => ProviderFaceCheck::TRIGGER_INTERVAL,
            'requested_at' => now(),
            // Refusés.
            'status' => ProviderFaceCheck::STATUS_PASSED,
            'score' => 100,
            'liveness_result' => ProviderFaceCheck::LIVENESS_PASS,
        ]);
    }

    public function test_les_colonnes_sensibles_sont_chiffrees_au_repos(): void
    {
        $profil = ProviderFaceProfile::factory()->enrolled()->create([
            'metadata' => ['external_reference' => 'onfido-applicant-1234'],
        ]);

        $brut = DB::table('provider_face_profiles')
            ->where('id', $profil->id)
            ->value('metadata');

        $this->assertIsString($brut);
        $this->assertStringNotContainsString('onfido-applicant-1234', $brut);
        $this->assertSame(
            ['external_reference' => 'onfido-applicant-1234'],
            $profil->fresh()->metadata
        );
    }

    public function test_une_echeance_nulle_vaut_controle_du(): void
    {
        $sansEcheance = ProviderFaceProfile::factory()->enrolled()->create();
        $sansEcheance->forceFill(['next_check_due_at' => null])->save();

        $this->assertTrue($sansEcheance->isCheckDue());

        $aJour = ProviderFaceProfile::factory()->enrolled()->create();
        $this->assertFalse($aJour->isCheckDue());

        $echu = ProviderFaceProfile::factory()->due()->create();
        $this->assertTrue($echu->isCheckDue());
    }

    public function test_le_retrait_du_consentement_rend_le_consentement_inactif(): void
    {
        $profil = ProviderFaceProfile::factory()->enrolled()->create();
        $this->assertTrue($profil->hasActiveConsent());

        $profil->update(['consent_withdrawn_at' => now()]);

        $this->assertFalse($profil->fresh()->hasActiveConsent());
    }

    /** LE MODULE EST EN SERVICE, EN STRATÉGIE `global`. */
    public function test_le_module_plateforme_est_en_service_et_global(): void
    {
        $this->seed(PlatformModuleSeeder::class);

        $module = PlatformModule::query()->where('key', 'security.face_check')->first();

        $this->assertNotNull($module);
        $this->assertTrue($module->is_enabled);
        $this->assertSame('global', $module->rollout_strategy);
    }

    /** LES RÉGLAGES SEMÉS SONT CEUX DE LA CONFIG, pas une copie qui dérive. */
    public function test_les_reglages_semes_suivent_la_config(): void
    {
        $this->seed(PlatformModuleSeeder::class);

        $module = PlatformModule::query()->where('key', 'security.face_check')->firstOrFail();

        foreach ([
            'min_hours' => config('face_check.interval.min_hours'),
            'max_hours' => config('face_check.interval.max_hours'),
            'max_attempts' => config('face_check.max_attempts'),
            'failure_threshold' => config('face_check.failure_threshold'),
            'abandon_threshold' => config('face_check.abandon.threshold'),
            'selfie_retention_days' => config('face_check.selfie_retention_days'),
        ] as $cle => $attendu) {
            $this->assertSame((int) $attendu, (int) $module->settingsValue("face_check.{$cle}"), $cle);
        }
    }

    public function test_le_seeder_nefface_pas_les_reglages_deja_poses(): void
    {
        $this->seed(PlatformModuleSeeder::class);

        $module = PlatformModule::query()->where('key', 'security.face_check')->firstOrFail();
        $reglages = $module->settings;
        $reglages['face_check']['min_hours'] = 12;
        $reglages['allowed_zone_ids'] = [7, 9];
        $module->update(['settings' => $reglages, 'is_enabled' => true]);

        $this->seed(PlatformModuleSeeder::class);

        $module->refresh();
        $this->assertSame(12, $module->settingsValue('face_check.min_hours'));
        $this->assertSame([7, 9], $module->settingsList('allowed_zone_ids'));
        $this->assertTrue($module->is_enabled);
    }

    public function test_un_incident_est_ouvert_tant_quil_nest_pas_clos(): void
    {
        $ouvert = ProviderFaceIncident::factory()->create();
        $this->assertTrue($ouvert->isOpen());

        $clos = ProviderFaceIncident::factory()->create([
            'resolution' => 'fixed',
        ]);
        $clos->forceFill(['status' => ProviderFaceIncident::STATUS_RESOLVED])->save();

        $this->assertFalse($clos->fresh()->isOpen());
        $this->assertSame(1, ProviderFaceIncident::query()->open()->count());
    }
}
