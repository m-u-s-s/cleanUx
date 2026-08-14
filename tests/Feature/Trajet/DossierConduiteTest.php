<?php

namespace Tests\Feature\Trajet;

use App\Models\FleetVehicle;
use App\Models\OnboardingStep;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\Question;
use App\Models\Trade;
use App\Models\User;
use App\Services\Onboarding\ProviderDocumentRequirements;
use App\Services\Onboarding\ProviderVehicleService;
use App\Services\OnboardingV2\Validators\VehicleDeclarationValidator;
use App\Support\Domain\LocationRole;
use App\Support\Domain\QuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ON NE CONDUIT PAS SANS PERMIS — et sous règles taxi, pas dans n'importe quelle voiture.
 *
 * Les exigences se DÉRIVENT des métiers déclarés, exactement comme l'assurance professionnelle le
 * fait déjà. C'est le point d'extension qui existait : `ProviderDocumentRequirements` lit
 * `trade_user` et compose la liste. Rien n'a été inventé à côté.
 *
 * LE TÉMOIN EST PARTOUT. Un prestataire d'un métier ordinaire ne doit voir AUCUNE pièce
 * supplémentaire — sans ce contrôle, on ne saurait pas distinguer « la règle s'applique bien » de
 * « la règle s'applique à tout le monde ».
 */
class DossierConduiteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function prestataire(): User
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    private function metierDeTrajet(bool $taxi = false): Trade
    {
        $trade = Trade::factory()->create(['taxi_rules' => $taxi]);

        foreach ([LocationRole::PICKUP, LocationRole::DROPOFF] as $role) {
            Question::create([
                'trade_id' => $trade->id,
                'code' => $role,
                'label' => LocationRole::label($role),
                'type' => QuestionType::LOCATION,
                'location_role' => $role,
                'is_active' => true,
            ]);
        }

        return $trade->fresh();
    }

    /** @return list<string> */
    private function typesExiges(User $user): array
    {
        return app(ProviderDocumentRequirements::class)->requiredTypesFor($user->fresh());
    }

    public function test_un_metier_de_trajet_exige_le_permis(): void
    {
        $user = $this->prestataire();
        $user->trades()->attach($this->metierDeTrajet()->id);

        $types = $this->typesExiges($user);

        $this->assertContains(ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, $types);
        $this->assertContains(ProviderOnboardingDocument::TYPE_VEHICLE_INSURANCE, $types);
        $this->assertNotContains(
            ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION,
            $types,
            'La carte grise relève des règles taxi, pas du trajet : une dépanneuse va d’un point à un autre sans être un taxi.'
        );
    }

    public function test_les_regles_taxi_ajoutent_la_carte_grise(): void
    {
        $user = $this->prestataire();
        $user->trades()->attach($this->metierDeTrajet(taxi: true)->id);

        $this->assertContains(ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION, $this->typesExiges($user));
    }

    /** LE TÉMOIN : un métier ordinaire ne voit apparaître AUCUNE pièce de conduite. */
    public function test_un_metier_ordinaire_n_exige_rien_de_plus(): void
    {
        $user = $this->prestataire();
        $user->trades()->attach(Trade::factory()->create()->id);

        $types = $this->typesExiges($user);

        $this->assertNotContains(ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, $types);
        $this->assertNotContains(ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION, $types);
        $this->assertNotContains(ProviderOnboardingDocument::TYPE_VEHICLE_INSURANCE, $types);
    }

    public function test_l_age_du_vehicule_est_calcule_depuis_la_premiere_immatriculation(): void
    {
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataire();
        $vehicules = app(ProviderVehicleService::class);

        $vehicule = $vehicules->declarer($user, [
            'plate' => '1-ABC-123',
            'registered_at' => '2023-08-14',
        ]);

        $this->assertEqualsWithDelta(3.0, (float) $vehicules->ageEnAnnees($vehicule), 0.05);
    }

    public function test_un_vehicule_trop_ancien_est_refuse_avec_son_motif(): void
    {
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataire();
        $user->trades()->attach($this->metierDeTrajet(taxi: true)->id);

        app(ProviderVehicleService::class)->declarer($user, [
            'plate' => '1-OLD-999',
            'registered_at' => '2018-01-01',
        ]);

        $dossier = app(ProviderVehicleService::class)->dossier($user->fresh());

        $this->assertFalse($dossier['conforme']);
        $this->assertStringContainsString('limite est de 4 ans', (string) $dossier['motif']);
    }

    /** LE TÉMOIN : la même voiture, récente, passe. */
    public function test_un_vehicule_recent_est_conforme(): void
    {
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataire();
        $user->trades()->attach($this->metierDeTrajet(taxi: true)->id);

        app(ProviderVehicleService::class)->declarer($user, [
            'plate' => '1-NEW-001',
            'registered_at' => '2024-06-01',
        ]);

        $this->assertTrue(app(ProviderVehicleService::class)->dossier($user->fresh())['conforme']);
    }

    public function test_le_validateur_passe_trivialement_sans_metier_concerne(): void
    {
        $user = $this->prestataire();
        $user->trades()->attach(Trade::factory()->create()->id);

        $verdict = app(VehicleDeclarationValidator::class)->validate($user->fresh(), $this->etape(), []);

        $this->assertTrue(
            $verdict->ok,
            'Une étape « déclarez votre véhicule » qui bloquerait un jardinier empêcherait des inscriptions légitimes.'
        );
    }

    public function test_le_validateur_exige_la_carte_grise_meme_quand_le_vehicule_est_conforme(): void
    {
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataire();
        $user->trades()->attach($this->metierDeTrajet(taxi: true)->id);

        app(ProviderVehicleService::class)->declarer($user, [
            'plate' => '1-NEW-002',
            'registered_at' => '2025-01-01',
        ]);

        $sansPiece = app(VehicleDeclarationValidator::class)->validate($user->fresh(), $this->etape(), []);
        $this->assertFalse($sansPiece->ok);

        ProviderOnboardingDocument::create([
            'user_id' => $user->id,
            'document_type' => ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION,
            'status' => ProviderOnboardingDocument::STATUS_PENDING,
            'file_path' => 'providers/x/registration.pdf',
        ]);

        $avecPiece = app(VehicleDeclarationValidator::class)->validate($user->fresh(), $this->etape(), []);
        $this->assertTrue($avecPiece->ok);
    }

    public function test_le_vehicule_reutilise_la_table_de_flotte(): void
    {
        $user = $this->prestataire();

        app(ProviderVehicleService::class)->declarer($user, ['plate' => '1-XYZ-777', 'brand' => 'Toyota']);

        $this->assertDatabaseHas('fleet_vehicles', [
            'plate' => '1-XYZ-777',
            'current_provider_id' => $user->id,
        ]);

        // Redéclarer ne crée pas un second véhicule : un prestataire conduit UNE voiture, et deux
        // lignes rendraient l'âge dépendant de laquelle on lit.
        app(ProviderVehicleService::class)->declarer($user, ['plate' => '1-XYZ-778']);

        $this->assertSame(1, FleetVehicle::where('current_provider_id', $user->id)->count());
    }

    public function test_la_date_de_validite_est_enfin_ecrite(): void
    {
        $user = $this->prestataire();

        $this->actingAs($user)
            ->postJson('/api/provider/onboarding/documents', [
                'document_type' => ProviderOnboardingDocument::TYPE_DRIVING_LICENSE,
                'file' => UploadedFile::fake()->create('permis.pdf', 100, 'application/pdf'),
                'expires_at' => now()->addYears(5)->toDateString(),
            ])
            ->assertCreated();

        $document = ProviderOnboardingDocument::query()
            ->where('user_id', $user->id)
            ->where('document_type', ProviderOnboardingDocument::TYPE_DRIVING_LICENSE)
            ->firstOrFail();

        $this->assertNotNull(
            $document->expires_at,
            'La colonne existait, castée, et n’était écrite par personne : `isExpired()` n’était donc jamais vrai.'
        );
    }

    private function etape(): OnboardingStep
    {
        return new OnboardingStep(['code' => 'vehicle_declare', 'metadata' => []]);
    }
}
