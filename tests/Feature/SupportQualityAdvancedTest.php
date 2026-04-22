<?php

namespace Tests\Feature;

use App\Livewire\Client\LitigesClient;
use App\Livewire\Employe\SignalerIncident;
use App\Models\ComplaintCase;
use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupportQualityAdvancedTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_complaint_stores_attachments_and_sla(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client);

        Livewire::test(LitigesClient::class)
            ->set('subject', 'Problème de qualité')
            ->set('description', 'Le service n\'était pas conforme.')
            ->set('priority', 'haute')
            ->set('attachmentInput', "https://example.test/preuve-1.jpg\nhttps://example.test/preuve-2.pdf")
            ->call('save')
            ->assertHasNoErrors();

        $case = ComplaintCase::firstOrFail();
        $this->assertSame('24h', $case->sla_policy);
        $this->assertCount(2, $case->attachments ?? []);
        $this->assertNotNull($case->due_at);
    }

    public function test_employee_incident_stores_attachments_and_sla(): void
    {
        $employe = User::factory()->create(['role' => 'employe']);

        $this->actingAs($employe);

        Livewire::test(SignalerIncident::class)
            ->set('title', 'Matériel cassé')
            ->set('description', 'Aspirateur inutilisable.')
            ->set('priority', 'critique')
            ->set('attachmentInput', "photo://preuve-a\nphoto://preuve-b")
            ->call('save')
            ->assertHasNoErrors();

        $incident = IncidentReport::firstOrFail();
        $this->assertSame('1h', $incident->sla_policy);
        $this->assertSame('critical', $incident->severity);
        $this->assertCount(2, $incident->attachments ?? []);
        $this->assertNotNull($incident->due_at);
    }
}
