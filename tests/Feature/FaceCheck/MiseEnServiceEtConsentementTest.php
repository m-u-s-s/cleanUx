<?php

namespace Tests\Feature\FaceCheck;

use App\Livewire\Provider\FaceCheckPage;
use App\Models\ProviderFaceProfile;
use App\Models\Trade;
use App\Models\User;
use App\Notifications\FaceCheck\FaceCheckBlockedNotification;
use App\Notifications\FaceCheck\FaceCheckUnblockedNotification;
use App\Services\FaceCheck\FaceCheckService;
use Database\Seeders\TradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Feature\FaceCheck\Concerns\ActiveLeControleFacial;
use Tests\TestCase;

/**
 * MISE EN SERVICE, CONSENTEMENT ET NOTIFICATIONS.
 *
 * Ce qui se joue ici tient en une phrase : un module de contrôle d'identité n'est utile que s'il
 * est ALLUMÉ sur les bons métiers, que la personne a VRAIMENT consenti, et qu'elle SAIT quand on
 * lui coupe l'accès. Les trois manquaient à la première livraison.
 */
class MiseEnServiceEtConsentementTest extends TestCase
{
    use ActiveLeControleFacial;
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Mise en service : quels métiers, et le choix de l'admin qui survit
    // ─────────────────────────────────────────────────────────────────────────

    public function test_le_seeder_coche_les_metiers_ou_lon_est_seul_chez_quelquun(): void
    {
        $this->seed(TradeSeeder::class);

        $codes = (array) config('face_check.default_trade_codes');
        $this->assertNotEmpty($codes);

        foreach ($codes as $code) {
            $metier = Trade::query()->where('code', $code)->first();

            $this->assertNotNull($metier, "Métier {$code} absent du catalogue.");
            $this->assertTrue(
                (bool) $metier->requires_face_check,
                "Le métier {$code} devrait exiger le contrôle facial."
            );
        }
    }

    /** TÉMOIN : les autres métiers, eux, ne sont pas cochés. Sinon on mesurerait un `true` global. */
    public function test_les_autres_metiers_ne_sont_pas_coches(): void
    {
        $this->seed(TradeSeeder::class);

        $codes = (array) config('face_check.default_trade_codes');

        $autres = Trade::query()->whereNotIn('code', $codes)->get();

        $this->assertGreaterThan(0, $autres->count());

        foreach ($autres as $metier) {
            $this->assertFalse(
                (bool) $metier->requires_face_check,
                "Le métier {$metier->code} ne devrait rien exiger."
            );
        }
    }

    /**
     * LE CHOIX DE L'ADMINISTRATEUR SURVIT AU SEEDER.
     *
     * Un administrateur qui décoche un métier ne doit pas voir son choix effacé au prochain
     * passage. Comparer `created_at` à `updated_at` aurait semblé équivalent et ne l'est pas : un
     * métier ancien jamais modifié porte les deux dates identiques, et se serait vu re-cocher à
     * chaque passage — sans que rien ne le signale.
     */
    public function test_le_seeder_nefface_pas_le_choix_de_ladministrateur(): void
    {
        $this->seed(TradeSeeder::class);

        $code = (string) ((array) config('face_check.default_trade_codes'))[0];
        Trade::query()->where('code', $code)->update(['requires_face_check' => false]);

        $this->seed(TradeSeeder::class);

        $this->assertFalse(
            (bool) Trade::query()->where('code', $code)->value('requires_face_check'),
            'Le seeder a réécrit un choix humain.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Consentement : une seule source, servie aux deux surfaces
    // ─────────────────────────────────────────────────────────────────────────

    public function test_lapi_sert_le_texte_de_consentement(): void
    {
        Storage::fake('private');
        $this->activerLeControleFacial();

        $prestataire = $this->prestataireSoumis();
        Sanctum::actingAs($prestataire);

        $reponse = $this->getJson('/api/provider/face-check/status')->assertOk();

        $texte = $reponse->json('data.consent_text');

        $this->assertIsString($texte);
        $this->assertNotSame('', $texte);
        // La durée de conservation est injectée : un texte qui annoncerait « :days jours » serait
        // un texte non substitué, et c'est ce que verrait le prestataire.
        $this->assertStringNotContainsString(':days', $texte);
        $this->assertStringContainsString('30', $texte);
        $this->assertNotNull($reponse->json('data.consent_legal_note'));
        $this->assertSame('1.0', $reponse->json('data.consent_version'));
    }

    public function test_le_texte_de_consentement_est_traduit(): void
    {
        Storage::fake('private');
        $this->activerLeControleFacial();

        $prestataire = $this->prestataireSoumis();
        Sanctum::actingAs($prestataire);

        $fr = $this->getJson('/api/provider/face-check/status')->json('data.consent_text');

        app()->setLocale('nl');
        $nl = $this->getJson('/api/provider/face-check/status')->json('data.consent_text');

        app()->setLocale('en');
        $en = $this->getJson('/api/provider/face-check/status')->json('data.consent_text');

        $this->assertNotSame($fr, $nl);
        $this->assertNotSame($fr, $en);
        $this->assertStringNotContainsString(':days', (string) $nl);
        $this->assertStringNotContainsString(':days', (string) $en);
    }

    /**
     * LE MÊME TEXTE DES DEUX CÔTÉS — c'est tout l'intérêt de le servir depuis le serveur.
     *
     * Deux copies d'un texte relu une seule fois, et c'est celle qu'on n'a pas relue qui
     * s'affiche.
     */
    public function test_le_web_et_lapi_affichent_le_meme_texte_de_consentement(): void
    {
        Storage::fake('private');
        $this->activerLeControleFacial();

        $prestataire = $this->prestataireSoumis();
        $prestataire->forceFill(['email_verified_at' => now(), 'phone_verified_at' => now()])->save();

        Sanctum::actingAs($prestataire);
        $parLApi = $this->getJson('/api/provider/face-check/status')->json('data.consent_text');

        $parLeWeb = Livewire::actingAs($prestataire)
            ->test(FaceCheckPage::class)
            ->viewData('texteDuConsentement');

        $this->assertSame($parLApi, $parLeWeb);
    }

    public function test_le_web_refuse_lenrolement_sans_consentement(): void
    {
        Storage::fake('private');
        $this->activerLeControleFacial();

        $prestataire = $this->prestataireSoumis();

        Livewire::actingAs($prestataire)
            ->test(FaceCheckPage::class)
            ->set('selfie', UploadedFile::fake()->createWithContent('s.jpg', 'x#face:a'))
            ->set('consentement', false)
            ->call('enregistrerLeVisage')
            ->assertHasErrors('consentement');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Notifications : le prestataire sait ce qui lui arrive
    // ─────────────────────────────────────────────────────────────────────────

    public function test_le_prestataire_est_prevenu_de_son_blocage_et_du_motif(): void
    {
        Storage::fake('private');
        Notification::fake();
        $this->activerLeControleFacial();

        $prestataire = $this->prestataireSoumis();
        $service = app(FaceCheckService::class);
        $service->enroll($prestataire, 'ref#face:jean', 'image/jpeg', true);

        $service->block($service->profileFor($prestataire), ProviderFaceProfile::BLOCK_ID_MISMATCH);

        Notification::assertSentTo(
            $prestataire,
            FaceCheckBlockedNotification::class,
            function (FaceCheckBlockedNotification $notification) use ($prestataire) {
                $charge = $notification->toArray($prestataire);

                // Le motif doit être LISIBLE, pas un code : « id_mismatch » n'apprend rien.
                $this->assertSame(ProviderFaceProfile::BLOCK_ID_MISMATCH, $charge['block_reason']);
                $this->assertStringContainsString('pièce d’identité', (string) $charge['message']);

                return true;
            }
        );
    }

    public function test_le_prestataire_est_prevenu_de_la_levee_du_blocage(): void
    {
        Storage::fake('private');
        Notification::fake();
        $this->activerLeControleFacial();

        $prestataire = $this->prestataireSoumis();
        $admin = User::factory()->create(['platform_role' => 'admin', 'is_active' => true]);

        $service = app(FaceCheckService::class);
        $service->enroll($prestataire, 'ref#face:jean', 'image/jpeg', true);
        $profil = $service->profileFor($prestataire);

        $service->block($profil, ProviderFaceProfile::BLOCK_FAILED_CHECKS);
        $service->unblock($profil, $admin, 'Vérifié en visio.');

        Notification::assertSentTo($prestataire, FaceCheckUnblockedNotification::class);
    }

    /** TÉMOIN : un blocage déjà posé ne renotifie pas — sinon chaque appel spammerait. */
    public function test_un_blocage_deja_pose_ne_renotifie_pas(): void
    {
        Storage::fake('private');
        Notification::fake();
        $this->activerLeControleFacial();

        $prestataire = $this->prestataireSoumis();
        $service = app(FaceCheckService::class);
        $service->enroll($prestataire, 'ref#face:jean', 'image/jpeg', true);
        $profil = $service->profileFor($prestataire);

        $service->block($profil, ProviderFaceProfile::BLOCK_FAILED_CHECKS);
        $service->block($profil->refresh(), ProviderFaceProfile::BLOCK_FAILED_CHECKS);

        Notification::assertSentToTimes($prestataire, FaceCheckBlockedNotification::class, 1);
    }

    /**
     * UNE PANNE DE MESSAGERIE N'ANNULE PAS UN BLOCAGE.
     *
     * Le blocage est déjà écrit quand la notification part. Laisser remonter l'exception annulerait
     * un geste de sécurité pour un e-mail — le mauvais arbitrage.
     */
    public function test_une_panne_de_notification_nannule_pas_le_blocage(): void
    {
        Storage::fake('private');
        $this->activerLeControleFacial();

        $prestataire = $this->prestataireSoumis();
        $service = app(FaceCheckService::class);
        $service->enroll($prestataire, 'ref#face:jean', 'image/jpeg', true);

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));

        $profil = $service->block($service->profileFor($prestataire), ProviderFaceProfile::BLOCK_ADMIN);

        $this->assertTrue($profil->fresh()->isBlocked());
    }
}
