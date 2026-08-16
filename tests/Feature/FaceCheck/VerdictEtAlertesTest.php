<?php

namespace Tests\Feature\FaceCheck;

use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceIncident;
use App\Models\ProviderFaceProfile;
use App\Models\ProviderOnboardingDocument;
use App\Models\User;
use App\Notifications\FaceCheck\FaceCheckIncidentRaised;
use App\Services\FaceCheck\Data\FaceCheckDecision;
use App\Services\FaceCheck\FaceCheckGate;
use App\Services\FaceCheck\FaceCheckIncidentService;
use App\Services\FaceCheck\FaceCheckService;
use App\Services\FaceCheck\FaceIdDocumentMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FaceCheck\Concerns\ActiveLeControleFacial;
use Tests\TestCase;

class VerdictEtAlertesTest extends TestCase
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
        app(FaceCheckService::class)->enroll(
            $this->prestataire,
            'reference#face:jean',
            'image/jpeg',
            true,
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Le verdict
    // ─────────────────────────────────────────────────────────────

    public function test_un_bon_selfie_fait_passer_et_repousse_lecheance(): void
    {
        $service = app(FaceCheckService::class);
        $controle = $this->controleDu();

        $controle = $service->submit($controle, 'selfie#face:jean', 'image/jpeg');

        $this->assertSame(ProviderFaceCheck::STATUS_PASSED, $controle->status);
        $this->assertSame(ProviderFaceCheck::SOURCE_AUTO, $controle->decision_source);

        $profil = $service->profileFor($this->prestataire);
        $this->assertSame(0, $profil->consecutive_failures);
        $this->assertTrue($profil->next_check_due_at->isFuture());
        $this->assertTrue(app(FaceCheckGate::class)->inspectProvider($this->prestataire)->allowed());
    }

    public function test_un_mauvais_visage_consomme_un_essai_sans_clore_le_controle(): void
    {
        $service = app(FaceCheckService::class);
        $controle = $service->submit($this->controleDu(), 'selfie#face:pierre', 'image/jpeg');

        $this->assertSame(ProviderFaceCheck::STATUS_PENDING, $controle->status);
        $this->assertSame(2, $controle->attempt_number);
        $this->assertSame('score_below_threshold', $controle->failure_reason);
        $this->assertNull($controle->answered_at, 'Le prestataire peut recommencer.');
    }

    public function test_epuiser_les_essais_fait_echouer_le_controle_et_previent_ladmin(): void
    {
        $admin = $this->unAdmin();
        $service = app(FaceCheckService::class);
        $controle = $this->controleDu();

        for ($i = 0; $i < 3; $i++) {
            $controle = $service->submit($controle, 'selfie#face:pierre', 'image/jpeg');
        }

        $this->assertSame(ProviderFaceCheck::STATUS_FAILED, $controle->status);
        $this->assertSame(1, $service->profileFor($this->prestataire)->consecutive_failures);

        $this->assertDatabaseHas('provider_face_incidents', [
            'user_id' => $this->prestataire->id,
            'type' => ProviderFaceIncident::TYPE_REPEATED_FAILURE,
        ]);

        Notification::assertSentTo($admin, FaceCheckIncidentRaised::class);
    }

    /**
     * UN CONTRÔLE ÉCHOUÉ N'OUVRE AUCUNE FENÊTRE.
     *
     * Le blocage dur ne survient qu'au second échec. Sans cette garantie, un imposteur travaillerait
     * entre les deux — et le seuil de deux échecs serait une faveur qu'on lui fait.
     */
    public function test_un_controle_echoue_exige_aussitot_un_nouveau_controle(): void
    {
        $service = app(FaceCheckService::class);
        $controle = $this->controleDu();

        for ($i = 0; $i < 3; $i++) {
            $controle = $service->submit($controle, 'selfie#face:pierre', 'image/jpeg');
        }

        $verdict = app(FaceCheckGate::class)->inspectProvider($this->prestataire);

        $this->assertFalse($verdict->allowed());
        $this->assertSame(FaceCheckDecision::CHECK_REQUIRED, $verdict->code);
        $this->assertSame(ProviderFaceCheck::TRIGGER_RISK_FAILURES, $verdict->trigger);
    }

    public function test_deux_controles_echoues_bloquent_durement(): void
    {
        $service = app(FaceCheckService::class);

        foreach ([1, 2] as $tour) {
            $controle = $this->controleDu();
            for ($i = 0; $i < 3; $i++) {
                $controle = $service->submit($controle, 'selfie#face:pierre', 'image/jpeg');
            }
        }

        $profil = $service->profileFor($this->prestataire);
        $this->assertTrue($profil->isBlocked());
        $this->assertSame(ProviderFaceProfile::BLOCK_FAILED_CHECKS, $profil->block_reason);
        $this->assertSame(
            FaceCheckDecision::BLOCKED,
            app(FaceCheckGate::class)->inspectProvider($this->prestataire)->code
        );
    }

    public function test_une_vivacite_ratee_alerte_des_la_premiere_fois(): void
    {
        $admin = $this->unAdmin();
        app(FaceCheckService::class)->submit($this->controleDu(), 'selfie#face:jean#liveness:fail', 'image/jpeg');

        $this->assertDatabaseHas('provider_face_incidents', [
            'user_id' => $this->prestataire->id,
            'type' => ProviderFaceIncident::TYPE_LIVENESS_FAIL,
            'severity' => ProviderFaceIncident::SEVERITY_CRITICAL,
        ]);

        Notification::assertSentTo($admin, FaceCheckIncidentRaised::class);
    }

    /** Témoin : le BON visage, en vrai, ne déclenche rien. */
    public function test_un_bon_selfie_nalerte_personne(): void
    {
        $admin = $this->unAdmin();
        app(FaceCheckService::class)->submit($this->controleDu(), 'selfie#face:jean', 'image/jpeg');

        $this->assertSame(0, ProviderFaceIncident::query()->count());
        Notification::assertNotSentTo($admin, FaceCheckIncidentRaised::class);
    }

    public function test_un_verdict_differe_garde_la_porte_fermee(): void
    {
        $service = app(FaceCheckService::class);
        $controle = $service->submit($this->controleDu(), 'selfie#face:jean#pending', 'image/jpeg');

        $this->assertSame(ProviderFaceCheck::STATUS_PENDING, $controle->status);
        $this->assertNotNull($controle->answered_at);

        $verdict = app(FaceCheckGate::class)->inspectProvider($this->prestataire);
        $this->assertSame(FaceCheckDecision::CHECK_PENDING, $verdict->code);

        // Et il se conclut à la relecture.
        $controle = $service->resolvePending($controle);
        $this->assertSame(ProviderFaceCheck::STATUS_PASSED, $controle->status);
        $this->assertTrue(app(FaceCheckGate::class)->inspectProvider($this->prestataire)->allowed());
    }

    // ─────────────────────────────────────────────────────────────
    // Abandons : la gradation
    // ─────────────────────────────────────────────────────────────

    public function test_un_premier_abandon_nalerte_personne(): void
    {
        $admin = $this->unAdmin();

        app(FaceCheckService::class)->abandon($this->controleDu());

        $this->assertSame(0, ProviderFaceIncident::query()->count());
        Notification::assertNotSentTo($admin, FaceCheckIncidentRaised::class);
    }

    public function test_trois_abandons_ouvrent_un_incident(): void
    {
        $admin = $this->unAdmin();

        $this->abandonner(3);

        $incident = ProviderFaceIncident::query()->firstOrFail();
        $this->assertSame(ProviderFaceIncident::TYPE_REPEATED_ABANDON, $incident->type);
        $this->assertSame(ProviderFaceIncident::SEVERITY_WARNING, $incident->severity);
        Notification::assertSentTo($admin, FaceCheckIncidentRaised::class);
    }

    public function test_six_abandons_deviennent_une_suspicion_de_fraude(): void
    {
        $this->abandonner(6);

        $incident = ProviderFaceIncident::query()->firstOrFail();

        $this->assertSame(ProviderFaceIncident::SEVERITY_CRITICAL, $incident->severity);
        $this->assertSame(6, $incident->occurrence_count);
        $this->assertSame(1, ProviderFaceIncident::query()->count(), 'Un seul dossier, pas six.');
    }

    /**
     * UN CONTRÔLE EXPIRÉ N'EST PAS UN ABANDON. Le prestataire n'a peut-être jamais vu l'écran.
     */
    public function test_un_controle_expire_ne_compte_pas_comme_un_abandon(): void
    {
        $service = app(FaceCheckService::class);

        for ($i = 0; $i < 6; $i++) {
            $controle = $this->controleDu();
            $controle->forceFill(['expires_at' => now()->subHour()])->save();
            $service->expireStale();
        }

        $this->assertSame(6, ProviderFaceCheck::query()->where('status', ProviderFaceCheck::STATUS_EXPIRED)->count());
        $this->assertSame(0, ProviderFaceIncident::query()->count());
    }

    // ─────────────────────────────────────────────────────────────
    // Le signalement de panne
    // ─────────────────────────────────────────────────────────────

    public function test_signaler_une_panne_ouvre_un_dossier_et_ne_debloque_rien(): void
    {
        $admin = $this->unAdmin();
        $service = app(FaceCheckService::class);

        $profil = $service->profileFor($this->prestataire);
        $service->block($profil, ProviderFaceProfile::BLOCK_FAILED_CHECKS);

        app(FaceCheckIncidentService::class)->reportByProvider(
            $this->prestataire,
            'La caméra reste noire.',
            ['platform' => 'android', 'app_version' => '1.4.0'],
        );

        $this->assertDatabaseHas('provider_face_incidents', [
            'user_id' => $this->prestataire->id,
            'type' => ProviderFaceIncident::TYPE_PROVIDER_REPORT,
        ]);
        Notification::assertSentTo($admin, FaceCheckIncidentRaised::class);

        // LE POINT DU TEST : toujours bloqué.
        $this->assertTrue($service->profileFor($this->prestataire)->isBlocked());
        $this->assertSame(
            FaceCheckDecision::BLOCKED,
            app(FaceCheckGate::class)->inspectProvider($this->prestataire)->code
        );
    }

    public function test_seul_un_administrateur_leve_le_blocage(): void
    {
        $admin = $this->unAdmin();
        $service = app(FaceCheckService::class);
        $profil = $service->profileFor($this->prestataire);

        $service->block($profil, ProviderFaceProfile::BLOCK_FAILED_CHECKS);
        $service->unblock($profil, $admin, 'Vérifié en visio.');

        $profil->refresh();
        $this->assertFalse($profil->isBlocked());
        $this->assertSame($admin->id, $profil->unblocked_by_user_id);
        $this->assertSame(0, $profil->consecutive_failures);

        // On débloque pour laisser une chance de prouver, pas pour dispenser de prouver.
        $verdict = app(FaceCheckGate::class)->inspectProvider($this->prestataire);
        $this->assertSame(FaceCheckDecision::CHECK_REQUIRED, $verdict->code);
    }

    // ─────────────────────────────────────────────────────────────
    // Appariement avec la pièce d'identité
    // ─────────────────────────────────────────────────────────────

    public function test_le_visage_est_apparie_a_la_piece_didentite(): void
    {
        $this->deposerUnePiece('carte#face:jean');

        $profil = app(FaceIdDocumentMatcher::class)->match(
            app(FaceCheckService::class)->profileFor($this->prestataire)
        );

        $this->assertSame(ProviderFaceProfile::MATCH_OK, $profil->id_match_status);
        $this->assertNotNull($profil->id_match_score);
        $this->assertFalse($profil->isBlocked());
    }

    public function test_un_visage_qui_ne_correspond_pas_a_la_piece_bloque_et_alerte(): void
    {
        $admin = $this->unAdmin();
        $this->deposerUnePiece('carte#face:quelquun-dautre');

        $profil = app(FaceIdDocumentMatcher::class)->match(
            app(FaceCheckService::class)->profileFor($this->prestataire)
        );

        $this->assertSame(ProviderFaceProfile::MATCH_MISMATCH, $profil->id_match_status);
        $this->assertTrue($profil->isBlocked());
        $this->assertSame(ProviderFaceProfile::BLOCK_ID_MISMATCH, $profil->block_reason);

        $this->assertDatabaseHas('provider_face_incidents', [
            'type' => ProviderFaceIncident::TYPE_ID_MISMATCH,
            'severity' => ProviderFaceIncident::SEVERITY_CRITICAL,
        ]);
        Notification::assertSentTo($admin, FaceCheckIncidentRaised::class);
    }

    /**
     * UN PDF NE SE COMPARE PAS — et ce n'est pas la faute du prestataire.
     */
    public function test_une_piece_non_comparable_ne_bloque_personne(): void
    {
        $this->deposerUnePiece('%PDF-1.4 contenu', 'application/pdf');

        $profil = app(FaceIdDocumentMatcher::class)->match(
            app(FaceCheckService::class)->profileFor($this->prestataire)
        );

        $this->assertSame(ProviderFaceProfile::MATCH_INCONCLUSIVE, $profil->id_match_status);
        $this->assertFalse($profil->isBlocked(), 'Un scan illisible n’est pas une fraude.');

        $this->assertDatabaseHas('provider_face_incidents', [
            'type' => ProviderFaceIncident::TYPE_ID_MISMATCH,
            'severity' => ProviderFaceIncident::SEVERITY_INFO,
        ]);
    }

    public function test_sans_piece_deposee_lappariement_reste_en_attente(): void
    {
        $profil = app(FaceIdDocumentMatcher::class)->match(
            app(FaceCheckService::class)->profileFor($this->prestataire)
        );

        $this->assertSame(ProviderFaceProfile::MATCH_PENDING, $profil->id_match_status);
        $this->assertFalse($profil->isBlocked());
        $this->assertSame(0, ProviderFaceIncident::query()->count());
    }

    public function test_ladministrateur_peut_trancher_lappariement_contre_lautomatique(): void
    {
        $admin = $this->unAdmin();
        $this->deposerUnePiece('carte#face:quelquun-dautre');

        $service = app(FaceCheckService::class);
        $profil = app(FaceIdDocumentMatcher::class)->match($service->profileFor($this->prestataire));
        $this->assertTrue($profil->isBlocked());

        $profil = $service->overrideIdMatch($profil, $admin, true, 'Pièce refaite, vérifiée à la main.');

        $this->assertSame(ProviderFaceProfile::MATCH_MANUAL_OVERRIDE, $profil->id_match_status);
        $this->assertFalse($profil->fresh()->isBlocked());
        $this->assertSame($admin->id, $profil->reviewed_by_user_id);
    }

    // ─────────────────────────────────────────────────────────────
    // Aides
    // ─────────────────────────────────────────────────────────────

    private function controleDu(): ProviderFaceCheck
    {
        $service = app(FaceCheckService::class);
        $profil = $service->profileFor($this->prestataire);
        $profil->forceFill(['next_check_due_at' => now()->subMinute()])->save();

        return $service->openCheck($this->prestataire, ProviderFaceCheck::TRIGGER_INTERVAL);
    }

    private function abandonner(int $combien): void
    {
        $service = app(FaceCheckService::class);

        for ($i = 0; $i < $combien; $i++) {
            $service->abandon($this->controleDu());
        }
    }

    private function deposerUnePiece(string $contenu, string $mime = 'image/jpeg'): ProviderOnboardingDocument
    {
        $chemin = "providers/{$this->prestataire->id}/onboarding/identity_card/piece.jpg";
        Storage::disk('private')->put($chemin, $contenu);

        return ProviderOnboardingDocument::create([
            'user_id' => $this->prestataire->id,
            'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            'status' => ProviderOnboardingDocument::STATUS_APPROVED,
            'file_path' => $chemin,
            'file_name' => 'piece.jpg',
            'mime_type' => $mime,
            'file_size' => strlen($contenu),
        ]);
    }

    private function unAdmin(): User
    {
        return User::factory()->create([
            'platform_role' => 'admin',
            'is_active' => true,
        ]);
    }
}
