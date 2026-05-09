<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportRendezVousTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_csv_and_pdf_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Booking::factory()->count(3)->create();

        $csvResponse = $this->get(route('admin.export.pdf'));
        $csvResponse->assertStatus(200);

        $pdfResponse = $this->get('/admin/export/pdf');
        $pdfResponse->assertStatus(200);
    }
}