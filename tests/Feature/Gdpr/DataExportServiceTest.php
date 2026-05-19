<?php

namespace Tests\Feature\Gdpr;

use App\Models\Booking;
use App\Models\GdprDataRequest;
use App\Models\User;
use App\Services\Gdpr\DataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataExportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_collect_for_returns_profile_and_bookings(): void
    {
        $user = User::factory()->client()->create(['name' => 'Alice Export']);

        Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'confirme',
            'devis_estime' => 50,
            'adresse' => 'Rue test',
            'ville' => 'Bruxelles',
            'code_postal' => '1000',
        ]);

        $data = app(DataExportService::class)->collectFor($user->fresh());

        $this->assertArrayHasKey('export_metadata', $data);
        $this->assertSame($user->id, $data['profile']['id']);
        $this->assertSame('Alice Export', $data['profile']['name']);
        $this->assertCount(1, $data['bookings']);
        $this->assertContains('15', $data['export_metadata']['rgpd_articles']);
    }

    public function test_execute_writes_json_file_and_marks_fulfilled(): void
    {
        $user = User::factory()->client()->create();

        $request = GdprDataRequest::create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_PROCESSING,
            'reference' => 'GDPR-TEST00001',
            'requested_at' => now(),
        ]);

        app(DataExportService::class)->execute($request);

        $request->refresh();
        $this->assertSame(GdprDataRequest::STATUS_FULFILLED, $request->status);
        $this->assertNotNull($request->export_file_path);
        $this->assertNotNull($request->expires_at);
        $this->assertSame('json', $request->export_format);

        Storage::disk('local')->assertExists($request->export_file_path);

        $content = Storage::disk('local')->get($request->export_file_path);
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('profile', $decoded);
    }

    public function test_execute_rejects_wrong_request_type(): void
    {
        $user = User::factory()->client()->create();
        $request = GdprDataRequest::create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_PROCESSING,
            'reference' => 'GDPR-WRONG001',
            'requested_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(DataExportService::class)->execute($request);
    }
}
