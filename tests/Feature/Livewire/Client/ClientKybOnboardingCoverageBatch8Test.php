<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\ClientKybOnboarding;
use App\Models\BusinessDocument;
use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClientKybOnboardingCoverageBatch8Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('kyb_v2.identity_provider', 'mock');
        Config::set('kyb_v2.sanctions_provider', 'mock');
        Config::set('kyb_v2.identifier_types_by_country', [
            'FR' => ['siret', 'siren'],
            'BE' => ['kbo'],
        ]);
        Config::set('kyb_v2.sanctions_lists', ['eu', 'us_ofac']);
        Config::set('kyb_v2.auto_approve_enabled', false);
        Config::set('kyb_v2.document_types', [
            'kbis', 'certificate_incorp', 'articles', 'other',
        ]);
        Config::set('kyb_v2.allowed_mime_types', [
            'application/pdf', 'image/jpeg', 'image/png',
        ]);
        Config::set('kyb_v2.document_max_size_kb', 10240);
        Config::set('kyb_v2.document_storage_disk', 'local');
        Config::set('kyb_v2.document_path_prefix', 'kyb_documents');
    }

    public function test_mount_with_no_existing_entity_stays_on_step_one(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->assertOk()
            ->assertSet('step', 1)
            ->assertSet('entityId', null);
    }

    public function test_mount_with_unverified_entity_jumps_to_step_two(): void
    {
        $user = User::factory()->client()->create();
        $entity = $this->makeEntity($user, BusinessEntity::STATUS_PENDING);

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->assertSet('step', 2)
            ->assertSet('entityId', $entity->id);
    }

    public function test_mount_with_verified_entity_jumps_to_step_three(): void
    {
        $user = User::factory()->client()->create();
        $this->makeEntity($user, BusinessEntity::STATUS_VERIFIED);

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->assertSet('step', 3);
    }

    public function test_next_from_step1_validation_error_when_legal_name_missing(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->set('legalName', '')
            ->set('identifierValue', '0123456789')
            ->call('nextFromStep1')
            ->assertHasErrors('legalName');
    }

    public function test_next_from_step1_creates_entity_and_advances(): void
    {
        $user = User::factory()->client()->create();

        $component = Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->set('legalName', 'Clean Co SARL')
            ->set('countryCode', 'be')
            ->set('identifierType', 'kbo')
            ->set('identifierValue', '0123456789')
            ->set('vatId', 'BE0123456789')
            ->set('legalForm', 'SARL')
            ->set('addressStreet', 'Rue du Test 1')
            ->set('addressPostal', '1000')
            ->set('addressCity', 'Bruxelles')
            ->call('nextFromStep1')
            ->assertSet('step', 2)
            ->assertDispatched('toast');

        $entityId = $component->get('entityId');
        $this->assertNotNull($entityId);
        $entity = BusinessEntity::find($entityId);
        $this->assertSame('Clean Co SARL', $entity->legal_name);
        $this->assertSame('BE', $entity->country_code);
        $this->assertSame($user->id, $entity->owner_user_id);
        $this->assertIsArray($entity->registered_address);
    }

    public function test_next_from_step1_catches_service_validation_exception(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->set('legalName', 'Bad Combo SA')
            ->set('countryCode', 'FR')
            ->set('identifierType', 'kbo') // not allowed for FR -> service throws ValidationException
            ->set('identifierValue', '0123456789')
            ->call('nextFromStep1')
            ->assertSet('step', 1)
            ->assertSet('entityId', null)
            ->assertDispatched('toast');

        $this->assertSame(0, BusinessEntity::query()->count());
    }

    public function test_upload_document_requires_entity_first(): void
    {
        $user = User::factory()->client()->create();
        Storage::fake('local');

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->set('documentType', 'kbis')
            ->set('documentFile', UploadedFile::fake()->create('kbis.pdf', 100, 'application/pdf'))
            ->call('uploadDocument')
            ->assertDispatched('toast');

        $this->assertSame(0, BusinessDocument::query()->count());
    }

    public function test_upload_document_rejects_disallowed_mime(): void
    {
        $user = User::factory()->client()->create();
        $entity = $this->makeEntity($user, BusinessEntity::STATUS_PENDING);
        Storage::fake('local');

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->set('entityId', $entity->id)
            ->set('documentType', 'kbis')
            ->set('documentFile', UploadedFile::fake()->create('doc.txt', 100, 'text/plain'))
            ->call('uploadDocument')
            ->assertDispatched('toast');

        $this->assertSame(0, BusinessDocument::query()->count());
    }

    public function test_upload_document_persists_file_and_record(): void
    {
        $user = User::factory()->client()->create();
        $entity = $this->makeEntity($user, BusinessEntity::STATUS_PENDING);
        Storage::fake('local');

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->set('entityId', $entity->id)
            ->set('documentType', 'kbis')
            ->set('documentFile', UploadedFile::fake()->create('kbis.pdf', 120, 'application/pdf'))
            ->call('uploadDocument')
            ->assertSet('documentFile', null)
            ->assertDispatched('toast');

        $doc = BusinessDocument::query()->where('entity_id', $entity->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame('kbis', $doc->document_type);
        $this->assertSame(BusinessDocument::STATUS_PENDING, $doc->status);
        $this->assertSame($user->id, $doc->uploaded_by_user_id);
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_trigger_verifications_noop_without_entity(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->call('triggerVerifications')
            ->assertSet('step', 1);
    }

    public function test_trigger_verifications_ignores_entity_of_another_owner(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $entity = $this->makeEntity($other, BusinessEntity::STATUS_PENDING);

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->set('entityId', $entity->id)
            ->set('step', 2)
            ->call('triggerVerifications')
            ->assertSet('step', 2);
    }

    public function test_trigger_verifications_runs_for_owner_and_advances(): void
    {
        $user = User::factory()->client()->create();
        $entity = $this->makeEntity($user, BusinessEntity::STATUS_PENDING);

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->set('entityId', $entity->id)
            ->set('step', 2)
            ->call('triggerVerifications')
            ->assertSet('step', 3)
            ->assertDispatched('toast');

        $this->assertNotNull($entity->fresh()->risk_score);
    }

    public function test_go_to_step_within_bounds_and_ignores_out_of_bounds(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientKybOnboarding::class)
            ->call('goToStep', 3)
            ->assertSet('step', 3)
            ->call('goToStep', 9)
            ->assertSet('step', 3)
            ->call('goToStep', 0)
            ->assertSet('step', 3)
            ->call('goToStep', 1)
            ->assertSet('step', 1);
    }

    private function makeEntity(User $user, string $status): BusinessEntity
    {
        return BusinessEntity::query()->create([
            'code' => BusinessEntity::generateCode(),
            'legal_name' => 'Existing Co',
            'country_code' => 'BE',
            'identifier_type' => 'kbo',
            'identifier_value' => '0'.random_int(100000000, 999999999),
            'status' => $status,
            'owner_user_id' => $user->id,
        ]);
    }
}
