<?php

namespace Tests\Feature;

use App\Models\RendezVous;
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

        RendezVous::factory()->count(3)->create();

        $csvResponse = $this->get('/admin/export/csv');
        $csvResponse->assertStatus(200);

        $pdfResponse = $this->get('/admin/export/pdf');
        $pdfResponse->assertStatus(200);
    }
}