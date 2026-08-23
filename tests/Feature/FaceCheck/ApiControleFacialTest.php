<?php

namespace Tests\Feature\FaceCheck;

use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceIncident;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use App\Services\FaceCheck\Data\FaceCheckDecision;
use App\Services\FaceCheck\FaceCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\FaceCheck\Concerns\ActiveLeControleFacial;
use Tests\TestCase;

class ApiControleFacialTest extends TestCase
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
        Sanctum::actingAs($this->prestataire);
    }

    // ─── L'état ──────────────────────────────────────────────────────────────

    public function test_letat_dit_quun_enrolement_est_attendu(): void
    {
        $this->getJson('/api/provider/face-check/status')
            ->assertOk()
            ->assertJsonPath('data.required', true)
            ->assertJsonPath('data.state', FaceCheckDecision::ENROLMENT_REQUIRED)
            ->assertJsonPath('data.enrolled', false);
    }

    /** TÉMOIN : hors périmètre, l'API le dit clairement et ne demande rien. */
    public function test_un_prestataire_hors_perimetre_nest_pas_sollicite(): void
    {
        Sanctum::actingAs($this->prestataireHorsPerimetre());

        $this->getJson('/api/provider/face-check/status')
            ->assertOk()
            ->assertJsonPath('data.required', false);
    }

    /** LE POINT LE PLUS IMPORTANT DE TOUT LE MODULE. */
    public function test_lecheance_ne_sort_par_aucune_reponse_dapi(): void
    {
        $this->enroler();

        $reponses = [
            $this->getJson('/api/provider/face-check/status'),
            $this->postJson('/api/provider/face-check/start'),
        ];

        // Toutes les reponses qui fuient d'un coup : une serialisation trop bavarde l'est sur
        // TOUTES les routes de la meme famille, jamais sur une seule.
        $fuites = [];

        foreach ($reponses as $i => $reponse) {
            $corps = (string) $reponse->getContent();

            foreach (['next_check_due_at', 'due_at'] as $champ) {
                if (str_contains($corps, $champ)) {
                    $fuites[] = "reponse #{$i} : « {$champ} »";
                }
            }
        }

        $this->assertSame([], $fuites, 'Ces reponses annoncent au prestataire quand son prochain controle tombera.');
    }

    // ─── L'enrôlement ────────────────────────────────────────────────────────

    public function test_lenrolement_exige_le_consentement_explicite(): void
    {
        $this->postJson('/api/provider/face-check/enroll', [
            'image' => $this->selfie(),
            'consent' => false,
        ])->assertStatus(422);

        $this->assertDatabaseCount('provider_face_profiles', 0);
    }

    public function test_lenrolement_enregistre_le_visage_et_le_consentement(): void
    {
        $this->postJson('/api/provider/face-check/enroll', [
            'image' => $this->selfie(),
            'consent' => true,
        ])->assertCreated()->assertJsonPath('data.enrolled', true);

        $profil = app(FaceCheckService::class)->profileFor($this->prestataire);

        $this->assertTrue($profil->isEnrolled());
        $this->assertTrue($profil->hasActiveConsent());
        $this->assertSame('1.0', $profil->consent_version);
        $this->assertNotNull($profil->reference_path);
    }

    // ─── Le contrôle ─────────────────────────────────────────────────────────

    public function test_on_nouvre_pas_un_controle_qui_nest_pas_du(): void
    {
        $this->enroler();

        // Fraîchement enrôlé et à jour : rien à faire.
        $this->postJson('/api/provider/face-check/start')
            ->assertOk()
            ->assertJsonPath('data.state', FaceCheckDecision::OK)
            ->assertJsonPath('data.check', null);

        $this->assertDatabaseCount('provider_face_checks', 0);
    }

    public function test_un_controle_du_souvre_puis_se_passe(): void
    {
        $this->enroler();
        $this->rendreDu();

        $ouverture = $this->postJson('/api/provider/face-check/start')->assertCreated();
        $id = $ouverture->json('data.id');

        $this->postJson("/api/provider/face-check/{$id}/submit", [
            'image' => $this->selfie('live.jpg'),
        ])->assertOk()->assertJsonPath('data.status', ProviderFaceCheck::STATUS_PASSED);
    }

    public function test_on_ne_soumet_pas_le_controle_dun_autre(): void
    {
        $autre = $this->prestataireSoumis();
        app(FaceCheckService::class)->enroll($autre, 'ref#face:autre', 'image/jpeg', true);
        $profil = app(FaceCheckService::class)->profileFor($autre);
        $profil->forceFill(['next_check_due_at' => now()->subMinute()])->save();
        $controleDeLautre = app(FaceCheckService::class)->openCheck($autre, ProviderFaceCheck::TRIGGER_INTERVAL);

        $this->enroler();

        $this->postJson("/api/provider/face-check/{$controleDeLautre->id}/submit", [
            'image' => $this->selfie('live.jpg'),
        ])->assertForbidden();
    }

    // ─── Le signalement de panne ─────────────────────────────────────────────

    public function test_signaler_une_panne_ouvre_un_dossier_et_le_dit_franchement(): void
    {
        $this->enroler();
        $service = app(FaceCheckService::class);
        $service->block($service->profileFor($this->prestataire), ProviderFaceProfile::BLOCK_FAILED_CHECKS);

        $this->postJson('/api/provider/face-check/incidents', [
            'message' => 'La caméra reste noire quand j\'ouvre le contrôle.',
            'diagnostics' => ['platform' => 'android', 'app_version' => '1.4.0'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.unblocks', false);

        $this->assertSame(1, ProviderFaceIncident::query()->where('user_id', $this->prestataire->id)->count());
        $this->assertTrue($service->profileFor($this->prestataire)->isBlocked());
    }

    // ─── La porte, vue de l'API ──────────────────────────────────────────────

    public function test_une_route_gardee_repond_403_avec_un_code_lisible(): void
    {
        $this->enroler();
        $this->rendreDu();

        $this->postJson('/api/provider/presence-v2/online', ['lat' => 50.85, 'lng' => 4.35])
            ->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error_code', FaceCheckDecision::CHECK_REQUIRED);
    }

    /** TÉMOIN : à jour, la même route passe. */
    public function test_la_meme_route_passe_quand_le_controle_est_a_jour(): void
    {
        $this->enroler();

        $this->postJson('/api/provider/presence-v2/online', ['lat' => 50.85, 'lng' => 4.35])
            ->assertOk();
    }

    /** LE PARCOURS DE REMÉDIATION RESTE ATTEIGNABLE QUAND TOUT LE RESTE EST FERMÉ. */
    public function test_les_routes_du_controle_restent_ouvertes_a_un_compte_bloque(): void
    {
        $this->enroler();
        $service = app(FaceCheckService::class);
        $service->block($service->profileFor($this->prestataire), ProviderFaceProfile::BLOCK_FAILED_CHECKS);

        $this->getJson('/api/provider/face-check/status')->assertOk();
        $this->postJson('/api/provider/face-check/incidents', ['message' => 'Ça ne marche pas du tout.'])
            ->assertCreated();

        // Et la porte est bien fermée ailleurs — sinon on ne mesurerait rien.
        $this->postJson('/api/provider/presence-v2/online', ['lat' => 50.85, 'lng' => 4.35])
            ->assertForbidden()
            ->assertJsonPath('error_code', FaceCheckDecision::BLOCKED);
    }

    // ─── Le retrait du consentement ──────────────────────────────────────────

    public function test_le_retrait_du_consentement_supprime_le_visage(): void
    {
        $this->enroler();
        $chemin = app(FaceCheckService::class)->profileFor($this->prestataire)->reference_path;
        $this->assertTrue(Storage::disk('private')->exists($chemin));

        $this->postJson('/api/provider/face-check/consent/withdraw', ['confirm' => true])->assertOk();

        $this->assertFalse(Storage::disk('private')->exists($chemin));
        $this->assertNull(app(FaceCheckService::class)->profileFor($this->prestataire)->reference_path);
    }

    // ─── Aides ───────────────────────────────────────────────────────────────

    /** UN VRAI CONTENU PLUTÔT QU'UNE IMAGE GÉNÉRÉE. */
    private function selfie(string $nom = 'selfie.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $nom,
            'JPEG-BYTES#face:'.$this->prestataire->id,
        );
    }

    private function enroler(): void
    {
        app(FaceCheckService::class)->enroll(
            $this->prestataire,
            'reference#face:'.$this->prestataire->id,
            'image/jpeg',
            true,
        );
    }

    private function rendreDu(): void
    {
        app(FaceCheckService::class)
            ->profileFor($this->prestataire)
            ->forceFill(['next_check_due_at' => now()->subMinute()])
            ->save();
    }
}
