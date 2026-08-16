<?php

namespace Tests\Feature\FaceCheck;

use App\Livewire\Admin\FaceCheck\FaceCheckCenter;
use App\Models\PlatformModule;
use App\Models\ProviderFaceIncident;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use App\Services\FaceCheck\FaceCheckService;
use App\Services\FaceCheck\FaceCheckSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Feature\FaceCheck\Concerns\ActiveLeControleFacial;
use Tests\TestCase;

class AdminControleFacialTest extends TestCase
{
    use ActiveLeControleFacial;
    use RefreshDatabase;

    private User $admin;

    private User $prestataire;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Notification::fake();
        $this->activerLeControleFacial();

        $this->admin = User::factory()->create([
            'platform_role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->prestataire = $this->prestataireSoumis();
        app(FaceCheckService::class)->enroll($this->prestataire, 'ref#face:jean', 'image/jpeg', true);
    }

    // ─── L'accès ─────────────────────────────────────────────────────────────

    public function test_lecran_est_ferme_a_qui_nest_pas_administrateur(): void
    {
        $this->actingAs($this->prestataire)
            ->get('/admin/verification-faciale')
            ->assertForbidden();
    }

    /** TÉMOIN : l'administrateur, lui, entre. */
    public function test_ladministrateur_ouvre_lecran(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/verification-faciale')
            ->assertOk()
            ->assertSee('Vérification faciale');
    }

    // ─── Les décisions ───────────────────────────────────────────────────────

    public function test_ladministrateur_leve_un_blocage(): void
    {
        $service = app(FaceCheckService::class);
        $profil = $service->profileFor($this->prestataire);
        $service->block($profil, ProviderFaceProfile::BLOCK_FAILED_CHECKS);

        Livewire::actingAs($this->admin)
            ->test(FaceCheckCenter::class)
            ->call('leverLeBlocage', $profil->id, 'Vérifié en visioconférence.');

        $profil->refresh();
        $this->assertFalse($profil->isBlocked());
        $this->assertSame($this->admin->id, $profil->unblocked_by_user_id);
    }

    public function test_ladministrateur_tranche_lappariement_et_sa_decision_prime(): void
    {
        $service = app(FaceCheckService::class);
        $profil = $service->profileFor($this->prestataire);
        $profil->forceFill(['id_match_status' => ProviderFaceProfile::MATCH_MISMATCH])->save();
        $service->block($profil, ProviderFaceProfile::BLOCK_ID_MISMATCH);

        Livewire::actingAs($this->admin)
            ->test(FaceCheckCenter::class)
            ->call('validerLAppariement', $profil->id);

        $profil->refresh();
        $this->assertSame(ProviderFaceProfile::MATCH_MANUAL_OVERRIDE, $profil->id_match_status);
        $this->assertFalse($profil->isBlocked());
    }

    public function test_ladministrateur_revoque_un_visage_et_le_fichier_disparait(): void
    {
        $profil = app(FaceCheckService::class)->profileFor($this->prestataire);
        $chemin = $profil->reference_path;
        $this->assertTrue(Storage::disk('private')->exists($chemin));

        Livewire::actingAs($this->admin)
            ->test(FaceCheckCenter::class)
            ->call('revoquerLeVisage', $profil->id);

        $this->assertFalse(Storage::disk('private')->exists($chemin));
        $this->assertNull($profil->refresh()->reference_path);
    }

    public function test_ladministrateur_clot_un_incident(): void
    {
        $incident = ProviderFaceIncident::factory()->create(['user_id' => $this->prestataire->id]);

        Livewire::actingAs($this->admin)
            ->test(FaceCheckCenter::class)
            ->call('cloreLIncident', $incident->id, 'fixed');

        $this->assertSame(ProviderFaceIncident::STATUS_RESOLVED, $incident->refresh()->status);
        $this->assertSame($this->admin->id, $incident->resolved_by_user_id);
    }

    /** Une résolution inconnue venue du navigateur ne devient pas une résolution valide. */
    public function test_une_resolution_inconnue_est_ramenee_a_ecartee(): void
    {
        $incident = ProviderFaceIncident::factory()->create(['user_id' => $this->prestataire->id]);

        Livewire::actingAs($this->admin)
            ->test(FaceCheckCenter::class)
            ->call('cloreLIncident', $incident->id, 'tout_va_bien_promis');

        $this->assertSame(ProviderFaceIncident::STATUS_DISMISSED, $incident->refresh()->status);
    }

    // ─── Les réglages ────────────────────────────────────────────────────────

    public function test_les_reglages_senregistrent_sans_effacer_laudience_par_zone(): void
    {
        $zones = PlatformModule::query()->where('key', 'security.face_check')->first()->settingsList('allowed_zone_ids');
        $this->assertNotEmpty($zones);

        Livewire::actingAs($this->admin)
            ->test(FaceCheckCenter::class)
            ->set('minHours', 12)
            ->set('maxHours', 36)
            ->set('selfieRetentionDays', 7)
            ->call('enregistrerLesReglages')
            ->assertHasNoErrors();

        $module = PlatformModule::query()->where('key', 'security.face_check')->first();

        $this->assertSame(12, $module->settingsValue('face_check.min_hours'));
        $this->assertSame(7, $module->settingsValue('face_check.selfie_retention_days'));
        $this->assertSame($zones, $module->settingsList('allowed_zone_ids'), 'L’audience par zone ne doit pas être effacée.');

        // Et le moteur lit bien la nouvelle valeur.
        app(FaceCheckSettings::class)->forget();
        $this->assertSame(12, app(FaceCheckSettings::class)->minHours());
    }

    public function test_un_intervalle_inverse_est_refuse_a_la_saisie(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FaceCheckCenter::class)
            ->set('minHours', 48)
            ->set('maxHours', 12)
            ->call('enregistrerLesReglages')
            ->assertHasErrors('maxHours');
    }

    // ─── Les images ──────────────────────────────────────────────────────────

    public function test_une_image_de_visage_exige_une_url_signee(): void
    {
        $profil = app(FaceCheckService::class)->profileFor($this->prestataire);

        $this->actingAs($this->admin)
            ->get("/admin/verification-faciale/profils/{$profil->id}/reference")
            ->assertForbidden();
    }

    public function test_une_url_signee_ouvre_limage_a_un_administrateur(): void
    {
        $profil = app(FaceCheckService::class)->profileFor($this->prestataire);

        $url = URL::temporarySignedRoute('admin.face-check.reference', now()->addMinutes(10), [
            'profile' => $profil->id,
        ]);

        $reponse = $this->actingAs($this->admin)->get($url);

        $reponse->assertOk();
        // Une image de visage ne se met en cache nulle part -- ni navigateur, ni proxy.
        $this->assertStringContainsString('no-store', (string) $reponse->headers->get('Cache-Control'));
        $this->assertSame('ref#face:jean', $reponse->getContent());
    }

    /**
     * LE POINT QUI COMPTE : une signature qui fuite ne suffit pas.
     */
    public function test_une_url_signee_ne_suffit_pas_a_qui_nest_pas_administrateur(): void
    {
        $profil = app(FaceCheckService::class)->profileFor($this->prestataire);

        $url = URL::temporarySignedRoute('admin.face-check.reference', now()->addMinutes(10), [
            'profile' => $profil->id,
        ]);

        $this->actingAs($this->prestataire)->get($url)->assertForbidden();
    }

    public function test_une_image_purgee_repond_410_et_non_404(): void
    {
        $profil = app(FaceCheckService::class)->profileFor($this->prestataire);
        Storage::disk('private')->delete($profil->reference_path);

        $url = URL::temporarySignedRoute('admin.face-check.reference', now()->addMinutes(10), [
            'profile' => $profil->id,
        ]);

        // 410 : l'image A EXISTÉ. « Jamais eu d'image » et « effacée » n'appellent pas la même conclusion.
        $this->actingAs($this->admin)->get($url)->assertStatus(410);
    }

    public function test_chaque_consultation_dune_image_laisse_une_trace(): void
    {
        $profil = app(FaceCheckService::class)->profileFor($this->prestataire);

        $url = URL::temporarySignedRoute('admin.face-check.reference', now()->addMinutes(10), [
            'profile' => $profil->id,
        ]);

        $this->actingAs($this->admin)->get($url)->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'face_check.reference_viewed',
        ]);
    }
}
