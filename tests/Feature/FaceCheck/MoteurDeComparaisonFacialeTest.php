<?php

namespace Tests\Feature\FaceCheck;

use App\Models\KycCheck;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use App\Services\FaceCheck\Data\FaceDocumentCompareRequest;
use App\Services\FaceCheck\Data\FaceEnrollRequest;
use App\Services\FaceCheck\Data\FaceVerifyRequest;
use App\Services\FaceCheck\Data\FaceVerifyResult;
use App\Services\FaceCheck\FaceImageStore;
use App\Services\FaceCheck\FaceMatchProviderInterface;
use App\Services\FaceCheck\Providers\FaceMatchMockProvider;
use App\Services\FaceCheck\Providers\OnfidoFaceMatchProvider;
use App\Services\Kyc\Providers\OnfidoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoteurDeComparaisonFacialeTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_fournisseur_par_defaut_est_le_bouchon(): void
    {
        $this->assertInstanceOf(
            FaceMatchMockProvider::class,
            app(FaceMatchProviderInterface::class)
        );
    }

    public function test_le_fournisseur_se_choisit_explicitement(): void
    {
        config()->set('face_check.provider', 'onfido');
        app()->forgetInstance(FaceMatchProviderInterface::class);

        $this->assertInstanceOf(
            OnfidoFaceMatchProvider::class,
            app(FaceMatchProviderInterface::class)
        );
    }

    /** LA DIFFÉRENCE DÉLIBÉRÉE AVEC LE MODULE KYC. */
    public function test_un_jeton_onfido_present_ne_fait_pas_basculer_tout_seul(): void
    {
        config()->set('face_check.onfido.api_token', 'un-jeton-qui-traine');
        config()->set('face_check.provider', 'mock');
        app()->forgetInstance(FaceMatchProviderInterface::class);

        $this->assertInstanceOf(
            FaceMatchMockProvider::class,
            app(FaceMatchProviderInterface::class)
        );
    }

    public function test_le_bouchon_est_deterministe(): void
    {
        $moteur = new FaceMatchMockProvider;
        $user = User::factory()->create();

        $premier = $moteur->verify(new FaceVerifyRequest(
            user: $user,
            probeContents: 'jpeg-bytes#face:alice',
            referenceContents: 'autres-bytes#face:alice',
        ));

        $second = $moteur->verify(new FaceVerifyRequest(
            user: $user,
            probeContents: 'jpeg-bytes#face:alice',
            referenceContents: 'autres-bytes#face:alice',
        ));

        $this->assertSame(FaceVerifyResult::PASSED, $premier->outcome);
        $this->assertSame($premier->score, $second->outcome === FaceVerifyResult::PASSED ? $second->score : null);
    }

    public function test_un_autre_visage_echoue(): void
    {
        $moteur = new FaceMatchMockProvider;

        $resultat = $moteur->verify(new FaceVerifyRequest(
            user: User::factory()->create(),
            probeContents: 'bytes#face:bob',
            referenceContents: 'bytes#face:alice',
        ));

        $this->assertSame(FaceVerifyResult::FAILED, $resultat->outcome);
        $this->assertSame('score_below_threshold', $resultat->failureReason);
    }

    public function test_la_vivacite_est_rendue_separement_du_score(): void
    {
        $moteur = new FaceMatchMockProvider;

        $resultat = $moteur->verify(new FaceVerifyRequest(
            user: User::factory()->create(),
            probeContents: 'bytes#face:alice#liveness:fail',
            referenceContents: 'bytes#face:alice',
        ));

        // Le bon visage, mais sur une photo d'une photo : le score ne suffit pas à le voir.
        $this->assertSame(FaceVerifyResult::PASSED, $resultat->outcome);
        $this->assertSame(FaceVerifyResult::LIVENESS_FAIL, $resultat->liveness);
    }

    public function test_un_verdict_differe_nest_pas_un_verdict_favorable(): void
    {
        $moteur = new FaceMatchMockProvider;

        $resultat = $moteur->verify(new FaceVerifyRequest(
            user: User::factory()->create(),
            probeContents: 'bytes#face:alice#pending',
            referenceContents: 'bytes#face:alice',
        ));

        $this->assertTrue($resultat->isPending());
        $this->assertNotSame(FaceVerifyResult::PASSED, $resultat->outcome);
        $this->assertNull($resultat->score);

        // Et il se conclut à la relecture.
        $conclu = $moteur->fetchVerification((string) $resultat->externalCheckId);
        $this->assertSame(FaceVerifyResult::PASSED, $conclu->outcome);
    }

    public function test_une_reference_absente_ne_fabrique_pas_un_verdict(): void
    {
        $moteur = new FaceMatchMockProvider;

        $resultat = $moteur->verify(new FaceVerifyRequest(
            user: User::factory()->create(),
            probeContents: 'bytes#face:alice',
            referenceContents: null,
        ));

        $this->assertSame(FaceVerifyResult::FAILED, $resultat->outcome);
        $this->assertSame('reference_missing', $resultat->failureReason);
    }

    public function test_un_document_illisible_rend_un_verdict_non_concluant(): void
    {
        $moteur = new FaceMatchMockProvider;

        $resultat = $moteur->compareWithDocument(new FaceDocumentCompareRequest(
            user: User::factory()->create(),
            referenceContents: 'bytes#face:alice',
            documentContents: 'scan#unreadable',
        ));

        $this->assertFalse($resultat->conclusive);
        $this->assertSame('document_unreadable', $resultat->reason);
        $this->assertNull($resultat->score);
    }

    /** Témoin positif : la comparaison AVEC un document lisible, elle, conclut. */
    public function test_un_document_lisible_conclut(): void
    {
        $moteur = new FaceMatchMockProvider;

        $resultat = $moteur->compareWithDocument(new FaceDocumentCompareRequest(
            user: User::factory()->create(),
            referenceContents: 'bytes#face:alice',
            documentContents: 'carte#face:alice',
        ));

        $this->assertTrue($resultat->conclusive);
        $this->assertNotNull($resultat->score);
    }

    public function test_lenrolement_rend_un_identifiant_stable(): void
    {
        $moteur = new FaceMatchMockProvider;
        $user = User::factory()->create();

        $a = $moteur->enroll(new FaceEnrollRequest($user, 'bytes#face:alice'));
        $b = $moteur->enroll(new FaceEnrollRequest($user, 'autres-bytes#face:alice'));

        $this->assertSame($a->externalFaceId, $b->externalFaceId);
    }

    // ────────────────────────────────────────────────────────────────
    // Le magasin d'images
    // ────────────────────────────────────────────────────────────────

    public function test_une_image_de_visage_est_ecrite_chiffree_sur_le_disque_prive(): void
    {
        Storage::fake('private');

        $store = app(FaceImageStore::class);
        $user = User::factory()->create();

        $chemin = $store->putReference($user, 'CONTENU-BINAIRE-DU-VISAGE');

        $surDisque = Storage::disk('private')->get($chemin);

        $this->assertIsString($surDisque);
        $this->assertStringNotContainsString('CONTENU-BINAIRE-DU-VISAGE', $surDisque);
        $this->assertSame('CONTENU-BINAIRE-DU-VISAGE', $store->get($chemin));
    }

    public function test_un_selfie_de_controle_seface_du_disque(): void
    {
        Storage::fake('private');

        $store = app(FaceImageStore::class);
        $profil = ProviderFaceProfile::factory()->enrolled()->create();
        $controle = ProviderFaceCheck::factory()->create([
            'user_id' => $profil->user_id,
            'provider_face_profile_id' => $profil->id,
        ]);

        $chemin = $store->putSelfie($controle, 'sel-fie');
        $this->assertTrue(Storage::disk('private')->exists($chemin));

        $store->forget($chemin);

        $this->assertFalse(Storage::disk('private')->exists($chemin));
        $this->assertNull($store->get($chemin));
    }

    public function test_un_chemin_inconnu_ne_leve_pas(): void
    {
        Storage::fake('private');

        $store = app(FaceImageStore::class);

        $this->assertNull($store->get('providers/9999/face/inexistant.enc'));
        $this->assertNull($store->get(null));
        $this->assertFalse($store->forget(null));
    }

    // ────────────────────────────────────────────────────────────────
    // Le défaut Onfido corrigé au passage
    // ────────────────────────────────────────────────────────────────

    public function test_onfido_ne_range_plus_tous_les_rapports_en_document(): void
    {
        $adaptateur = new class extends OnfidoProvider
        {
            /** @param array<string, mixed> $body */
            public function exposerMapping(array $body, ?string $result): array
            {
                return $this->mapReports($body, $result);
            }

            /** @return array<string, mixed>|null */
            protected function fetchReports(array $body): array
            {
                return [
                    ['id' => 'r1', 'name' => 'document', 'result' => 'clear'],
                    [
                        'id' => 'r2',
                        'name' => 'facial_similarity_photo',
                        'result' => 'consider',
                        'sub_result' => 'rejected',
                        'properties' => ['score' => 0.42],
                        'breakdown' => ['image_integrity' => ['result' => 'consider']],
                    ],
                    ['id' => 'r3', 'name' => 'watchlist_standard', 'result' => 'clear'],
                    ['id' => 'r4', 'name' => 'quelque_chose_de_nouveau', 'result' => 'clear'],
                ];
            }
        };

        $checks = $adaptateur->exposerMapping(['id' => 'check-1', 'report_ids' => ['r1', 'r2', 'r3', 'r4']], 'consider');

        $types = array_column($checks, 'type');

        $this->assertSame([
            KycCheck::TYPE_DOCUMENT,
            KycCheck::TYPE_FACIAL_SIMILARITY,
            KycCheck::TYPE_WATCHLIST_AML,
            KycCheck::TYPE_UNKNOWN,
        ], $types);

        $facial = $checks[1];
        $this->assertSame(KycCheck::RESULT_CONSIDER, $facial['result']);
        $this->assertSame('rejected', $facial['sub_result']);
        $this->assertSame(0.42, $facial['confidence']);
        $this->assertArrayHasKey('breakdown', $facial);
    }

    public function test_sans_rapport_lisible_onfido_dit_quil_ne_sait_pas(): void
    {
        $adaptateur = new class extends OnfidoProvider
        {
            /** @param array<string, mixed> $body */
            public function exposerMapping(array $body, ?string $result): array
            {
                return $this->mapReports($body, $result);
            }

            protected function fetchReports(array $body): array
            {
                return [];
            }
        };

        $checks = $adaptateur->exposerMapping(['id' => 'check-1', 'report_ids' => ['r1']], 'clear');

        $this->assertCount(1, $checks);
        $this->assertSame(KycCheck::TYPE_UNKNOWN, $checks[0]['type']);
        $this->assertSame(KycCheck::RESULT_CLEAR, $checks[0]['result']);
        $this->assertSame('r1', $checks[0]['external_id']);
    }
}
