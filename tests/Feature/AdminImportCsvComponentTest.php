<?php

namespace Tests\Feature;

use App\Enums\OrganizationType;
use App\Livewire\Admin\ImportCsv;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** Coverage Livewire pour le composant admin d'import CSV (App\Livewire\Admin\ImportCsv). */
class AdminImportCsvComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Livewire fakes its temporary-upload disk ("tmp-for-tests") during tests and
        // routes the component's `$this->csv->store('imports')` there. The component
        // then re-reads the file via storage_path('app/'.$path), so the faked disk's
        // root must coincide with storage/app for the two paths to line up. Pointing
        // the disk at the real local root makes the upload→store→read round-trip work.
        config(['filesystems.disks.tmp-for-tests' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ]]);
    }

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-users', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    protected function csvFile(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_component_renders_with_default_type(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(ImportCsv::class)
            ->assertOk()
            ->assertSet('type', 'clients');
    }

    public function test_import_clients_creates_users_and_organization(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $content = implode("\n", [
            'name,email,password,role,organization_name,tva_number',
            'Alice Client,alice@example.com,secret123,client,,',
            'Bob Corp,bob@corp.com,secret123,entreprise,Bob Corp SARL,BE0123456789',
            ',missing-name@example.com,secret123,client,,',   // name required -> skip
            'BadRowOnlyOneColumn',                              // colonnes invalides -> skip
        ]);

        Livewire::test(ImportCsv::class)
            ->set('type', 'clients')
            ->set('csv', $this->csvFile('clients.csv', $content))
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('csv', null);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com', 'role' => 'client']);
        $this->assertDatabaseHas('users', ['email' => 'bob@corp.com', 'role' => 'entreprise']);
        $this->assertDatabaseMissing('users', ['email' => 'missing-name@example.com']);

        $org = OrganizationAccount::where('name', 'Bob Corp SARL')->first();
        $this->assertNotNull($org);
        $this->assertSame(OrganizationType::CLIENT_COMPANY->value, $org->type);

        $bob = User::where('email', 'bob@corp.com')->first();
        $this->assertSame($org->id, $bob->organization_account_id);
    }

    public function test_import_clients_rejects_duplicate_email(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        User::factory()->client()->create(['email' => 'dup@example.com']);

        $content = implode("\n", [
            'name,email,password,role',
            'Dup Client,dup@example.com,secret123,client',
        ]);

        Livewire::test(ImportCsv::class)
            ->set('type', 'clients')
            ->set('csv', $this->csvFile('clients.csv', $content))
            ->call('import')
            ->assertHasNoErrors();

        // Only the pre-existing user should remain (the row was skipped as invalid/duplicate).
        $this->assertSame(1, User::where('email', 'dup@example.com')->count());
    }

    public function test_import_rendez_vous_creates_booking_and_skips_invalid_rows(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $client = User::factory()->client()->create();
        $employe = User::factory()->employe()->create();

        $content = implode("\n", [
            'date,heure,client_id,employe_id,status',
            "2026-07-01,10:00,{$client->id},{$employe->id},confirme",          // valide
            "2026-07-02,11:00,{$employe->id},{$client->id},en_attente",         // client_id n'est pas un client -> skip
            "2026-07-03,12:00,{$client->id},{$client->id},confirme",            // employe_id n'est pas un employé -> skip
            "not-a-date,13:00,{$client->id},{$employe->id},confirme",           // date invalide -> skip
        ]);

        Livewire::test(ImportCsv::class)
            ->set('type', 'rendez_vous')
            ->set('csv', $this->csvFile('rdv.csv', $content))
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('csv', null);

        $this->assertSame(1, Booking::count());
        $this->assertDatabaseHas('bookings', [
            'client_id' => $client->id,
            'employe_id' => $employe->id,
            'status' => 'confirme',
        ]);
    }

    public function test_import_requires_a_file(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(ImportCsv::class)
            ->set('type', 'clients')
            ->call('import')
            ->assertHasErrors(['csv']);
    }

    public function test_non_admin_cannot_import(): void
    {
        $client = User::factory()->client()->create(['is_active' => true]);
        $this->actingAs($client);

        // EnforcesAdminAccess blocks a non-admin at mount/boot (before any action).
        Livewire::test(ImportCsv::class)
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }
}
