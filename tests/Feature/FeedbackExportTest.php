<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, RendezVous, Feedback};

class FeedbackExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_pdf_and_csv_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $employe = User::factory()->employe()->create();

        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employe->id,
            'status' => 'termine',
        ]);

        Feedback::create([
            'rendez_vous_id' => $rdv->id,
            'client_id' => $client->id,
            'note' => 5,
            'commentaire' => 'ok',
        ]);

        $this->actingAs($admin);

        $pdfResponse = $this->get('/admin/feedbacks/export');
        $pdfResponse->assertStatus(200);

        $csvResponse = $this->get('/admin/feedbacks/export-csv');
        $csvResponse->assertStatus(200);
        $csvResponse->assertHeader('Content-Type', 'text/csv');
    }
}