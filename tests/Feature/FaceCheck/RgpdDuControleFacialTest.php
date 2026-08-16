<?php

namespace Tests\Feature\FaceCheck;

use App\Models\PlatformModule;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use App\Services\FaceCheck\FaceCheckService;
use App\Services\Gdpr\DataErasureService;
use App\Services\Gdpr\DataExportService;
use App\Services\Gdpr\RetentionPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FaceCheck\Concerns\ActiveLeControleFacial;
use Tests\TestCase;

/**
 * LE VISAGE EST UNE DONNÉE DE L'ARTICLE 9 — et la plateforme doit pouvoir le prouver.
 *
 * Trois obligations distinctes, trois tests qui vérifient LE DISQUE et pas seulement la colonne :
 * effacer une ligne en laissant l'image en place donne un registre conforme et un stockage qui ne
 * l'est pas. C'est le défaut exact que `DataErasureService` avait sur les pièces d'identité.
 */
class RgpdDuControleFacialTest extends TestCase
{
    use ActiveLeControleFacial;
    use RefreshDatabase;

    private User $prestataire;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Notification::fake();
        $this->activerLeControleFacial();

        $this->prestataire = $this->prestataireSoumis();
        app(FaceCheckService::class)->enroll($this->prestataire, 'ref#face:jean', 'image/jpeg', true);
    }

    // ─── Rétention ───────────────────────────────────────────────────────────

    public function test_les_selfies_de_controle_sont_purges_du_disque(): void
    {
        $controle = $this->unControlePasse(ilYA: 40);
        $chemin = $controle->selfie_path;

        $this->assertTrue(Storage::disk('private')->exists($chemin));

        app(RetentionPolicyService::class)->enforceAll();

        $this->assertFalse(Storage::disk('private')->exists($chemin), 'Le FICHIER doit partir, pas seulement la colonne.');

        $controle->refresh();
        $this->assertNull($controle->selfie_path);
        $this->assertNotNull($controle->selfie_purged_at);

        // Le verdict survit : il ne porte aucun visage, et il explique une décision six mois plus tard.
        $this->assertSame(ProviderFaceCheck::STATUS_PASSED, $controle->status);
        $this->assertNotNull($controle->score);
    }

    /** TÉMOIN : un selfie récent, lui, reste. Sinon on mesurerait une purge aveugle. */
    public function test_un_selfie_recent_nest_pas_purge(): void
    {
        $controle = $this->unControlePasse(ilYA: 2);

        app(RetentionPolicyService::class)->enforceAll();

        $this->assertTrue(Storage::disk('private')->exists($controle->refresh()->selfie_path));
    }

    /**
     * LA DURÉE VIENT DES RÉGLAGES DU MODULE, pas d'une seconde source.
     */
    public function test_la_duree_de_conservation_suit_le_reglage_de_ladministrateur(): void
    {
        $controle = $this->unControlePasse(ilYA: 10);

        // 30 jours par défaut : dix jours, ça reste.
        app(RetentionPolicyService::class)->enforceAll();
        $this->assertNotNull($controle->refresh()->selfie_path);

        PlatformModule::query()->where('key', 'security.face_check')->update([
            'settings' => ['allowed_zone_ids' => [$this->zoneDuControle->id], 'face_check' => ['selfie_retention_days' => 5]],
        ]);
        $this->oublierLesCachesDuControleFacial();

        app(RetentionPolicyService::class)->enforceAll();
        $this->assertNull($controle->refresh()->selfie_path);
    }

    // ─── Effacement ──────────────────────────────────────────────────────────

    public function test_leffacement_supprime_le_visage_de_reference_et_les_selfies(): void
    {
        $controle = $this->unControlePasse(ilYA: 1);
        $profil = app(FaceCheckService::class)->profileFor($this->prestataire);

        $cheminReference = $profil->reference_path;
        $cheminSelfie = $controle->selfie_path;

        app(DataErasureService::class)->anonymizeUser($this->prestataire);

        $this->assertFalse(Storage::disk('private')->exists($cheminReference));
        $this->assertFalse(Storage::disk('private')->exists($cheminSelfie));

        $profil->refresh();
        $this->assertNull($profil->reference_path);
        $this->assertNull($profil->reference_hash);
        $this->assertSame(ProviderFaceProfile::STATUS_REVOKED, $profil->status);
        $this->assertNotNull($profil->consent_withdrawn_at);
    }

    // ─── Export ──────────────────────────────────────────────────────────────

    public function test_lexport_rend_les_metadonnees_et_jamais_limage(): void
    {
        $this->unControlePasse(ilYA: 1);

        $export = app(DataExportService::class)->collectFor($this->prestataire);

        $this->assertArrayHasKey('face_checks', $export);
        $this->assertNotNull($export['face_checks']['enrolment']);
        $this->assertSame('1.0', $export['face_checks']['enrolment']['consent_version']);
        $this->assertCount(1, $export['face_checks']['checks']);
        $this->assertSame('passed', $export['face_checks']['checks'][0]['status']);

        // AUCUNE image, aucun chemin, aucun gabarit ne doit sortir dans un JSON portable.
        $json = (string) json_encode($export);
        $this->assertStringNotContainsString('reference_path', $json);
        $this->assertStringNotContainsString('selfie_path', $json);
        $this->assertStringNotContainsString('.enc', $json);
    }

    // ─── Entretien ───────────────────────────────────────────────────────────

    public function test_un_controle_reste_ouvert_finit_par_expirer_et_ne_bloque_pas_le_suivant(): void
    {
        $service = app(FaceCheckService::class);
        $profil = $service->profileFor($this->prestataire);
        $profil->forceFill(['next_check_due_at' => now()->subMinute()])->save();

        $controle = $service->openCheck($this->prestataire, ProviderFaceCheck::TRIGGER_INTERVAL);
        $controle->forceFill(['expires_at' => now()->subHour()])->save();

        $this->artisan('face-check:maintenance')->assertSuccessful();

        $this->assertSame(ProviderFaceCheck::STATUS_EXPIRED, $controle->refresh()->status);

        // Et un nouveau contrôle peut s'ouvrir : le prestataire n'est pas coincé.
        $suivant = $service->openCheck($this->prestataire, ProviderFaceCheck::TRIGGER_INTERVAL);
        $this->assertNotSame($controle->id, $suivant->id);
    }

    // ─── Aides ───────────────────────────────────────────────────────────────

    private function unControlePasse(int $ilYA): ProviderFaceCheck
    {
        $service = app(FaceCheckService::class);
        $profil = $service->profileFor($this->prestataire);
        $profil->forceFill(['next_check_due_at' => now()->subMinute()])->save();

        $controle = $service->openCheck($this->prestataire, ProviderFaceCheck::TRIGGER_INTERVAL);
        $controle = $service->submit($controle, 'selfie#face:jean', 'image/jpeg');

        $controle->forceFill(['requested_at' => now()->subDays($ilYA)])->save();

        return $controle->refresh();
    }
}
