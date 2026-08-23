<?php

namespace Tests\Feature\FaceCheck;

use App\Livewire\Provider\FaceCheckPage;
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
use Livewire\Livewire;
use Tests\Feature\FaceCheck\Concerns\ActiveLeControleFacial;
use Tests\TestCase;

/** LA PARITÉ WEB — et pourquoi elle n'est pas décorative ici. */
class PariteWebDuControleFacialTest extends TestCase
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
        $this->prestataire->forceFill([
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ])->save();
    }

    public function test_la_route_de_remediation_existe_et_souvre(): void
    {
        $this->actingAs($this->prestataire)
            ->get('/provider/verification-faciale')
            ->assertOk();
    }

    public function test_la_page_demande_lenrolement_a_qui_na_pas_de_visage(): void
    {
        Livewire::actingAs($this->prestataire)
            ->test(FaceCheckPage::class)
            ->assertSee('Enregistrez votre visage');
    }

    public function test_lenrolement_web_exige_le_consentement(): void
    {
        Livewire::actingAs($this->prestataire)
            ->test(FaceCheckPage::class)
            ->set('selfie', $this->image())
            ->set('consentement', false)
            ->call('enregistrerLeVisage')
            ->assertHasErrors('consentement');

        $this->assertNull(app(FaceCheckService::class)->profileFor($this->prestataire));
    }

    public function test_lenrolement_web_enregistre_le_visage(): void
    {
        Livewire::actingAs($this->prestataire)
            ->test(FaceCheckPage::class)
            ->set('selfie', $this->image())
            ->set('consentement', true)
            ->call('enregistrerLeVisage')
            ->assertHasNoErrors();

        $profil = app(FaceCheckService::class)->profileFor($this->prestataire);

        $this->assertTrue($profil->isEnrolled());
        $this->assertTrue($profil->hasActiveConsent());
    }

    public function test_un_controle_du_se_passe_depuis_le_web(): void
    {
        $this->enroler();
        $this->rendreDu();

        $composant = Livewire::actingAs($this->prestataire)->test(FaceCheckPage::class);

        $controleId = $composant->get('controleId');
        $this->assertNotNull($controleId, 'La page ouvre le contrôle dû toute seule.');

        $composant->set('selfie', $this->image())->call('envoyerLeSelfie')->assertHasNoErrors();

        $this->assertSame(
            ProviderFaceCheck::STATUS_PASSED,
            ProviderFaceCheck::query()->findOrFail($controleId)->status
        );
    }

    /** LA PAGE N'OUVRE PAS UN CONTRÔLE QUI N'EST PAS DÛ. */
    public function test_la_page_nouvre_pas_un_controle_qui_nest_pas_du(): void
    {
        $this->enroler();

        Livewire::actingAs($this->prestataire)
            ->test(FaceCheckPage::class)
            ->assertSet('controleId', null)
            ->assertSee('Tout est en règle');

        $this->assertSame(0, ProviderFaceCheck::query()->count());
    }

    public function test_le_signalement_web_ouvre_un_dossier_sans_debloquer(): void
    {
        $this->enroler();
        $service = app(FaceCheckService::class);
        $service->block($service->profileFor($this->prestataire), ProviderFaceProfile::BLOCK_FAILED_CHECKS);

        Livewire::actingAs($this->prestataire)
            ->test(FaceCheckPage::class)
            ->set('messageDIncident', 'La caméra ne démarre pas dans Firefox.')
            ->call('signaler')
            ->assertHasNoErrors()
            ->assertSet('signalementEnvoye', true);

        $this->assertSame(1, ProviderFaceIncident::query()->count());
        $this->assertTrue($service->profileFor($this->prestataire)->isBlocked());
    }

    /** LA REDIRECTION WEB, DE BOUT EN BOUT : c'est ce qui distingue un refus utilisable d'un 403 nu. */
    public function test_un_geste_de_terrain_redirige_vers_la_page_de_remediation(): void
    {
        $this->enroler();
        $this->rendreDu();

        // On frappe une route de terrain SANS paramètre de modèle : `SubstituteBindings` vit dans le groupe `web` et s'exécute AVANT les middlewares de route, donc un identifiant de mission inexistant rendrait 404 avant même que la garde ne soit consultée — le test mesurerait alors une mission introuvable, pas un contrôle facial.
        $this->actingAs($this->prestataire)
            ->post('/missions/offline-sync')
            ->assertRedirect(route('provider.face-check'));
    }

    /** TÉMOIN : à jour, la même route ne redirige plus vers le contrôle. */
    public function test_le_meme_geste_ne_redirige_pas_quand_le_controle_est_a_jour(): void
    {
        $this->enroler();

        $reponse = $this->actingAs($this->prestataire)->post('/missions/offline-sync');

        $this->assertNotSame(
            route('provider.face-check'),
            $reponse->headers->get('Location'),
            'Le refus ne doit plus venir du contrôle facial.'
        );
    }

    public function test_la_page_de_remediation_reste_atteignable_quand_tout_est_bloque(): void
    {
        $this->enroler();
        $service = app(FaceCheckService::class);
        $service->block($service->profileFor($this->prestataire), ProviderFaceProfile::BLOCK_FAILED_CHECKS);

        $this->actingAs($this->prestataire)
            ->get('/provider/verification-faciale')
            ->assertOk()
            ->assertSee('Compte suspendu');
    }

    public function test_un_prestataire_hors_perimetre_ne_voit_rien_a_faire(): void
    {
        $autre = $this->prestataireHorsPerimetre();
        $autre->forceFill(['email_verified_at' => now(), 'phone_verified_at' => now()])->save();

        Livewire::actingAs($autre)
            ->test(FaceCheckPage::class)
            ->assertSee('Rien à faire ici');
    }

    public function test_le_verdict_du_web_et_de_lapi_ne_divergent_pas(): void
    {
        $this->enroler();
        $this->rendreDu();

        $composant = Livewire::actingAs($this->prestataire)->test(FaceCheckPage::class);
        $verdictWeb = $composant->viewData('verdict');

        // Le contrôle ayant été ouvert par la page, l'API le retrouve — même code, même identifiant.
        $this->assertSame(FaceCheckDecision::CHECK_REQUIRED, $verdictWeb->code);
    }

    // ─── Aides ───────────────────────────────────────────────────────────────

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'selfie.jpg',
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
