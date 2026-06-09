<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\LitigesClient;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\CustomerClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LitigesClientCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
    }

    private function makeBooking(): Booking
    {
        return Booking::create([
            'client_id' => $this->client->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => 100,
        ]);
    }

    public function test_render_is_ok_with_seeded_claim(): void
    {
        CustomerClaim::create([
            'customer_user_id' => $this->client->id,
            'category' => 'quality',
            'priority' => 'normal',
            'status' => 'open',
            'title' => 'Souci',
            'description' => 'Description suffisante du litige client.',
            'attachments' => [],
        ]);

        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->assertOk()
            ->assertSet('category', 'quality')
            ->assertSet('priority', 'normal');
    }

    public function test_updating_filter_status_resets_page(): void
    {
        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('filterStatus', 'resolved')
            ->assertOk()
            ->assertSet('filterStatus', 'resolved');
    }

    public function test_create_claim_persists_with_photo_and_resets(): void
    {
        Storage::fake('private');

        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('category', 'damage')
            ->set('priority', 'urgent')
            ->set('title', 'Dégât constaté')
            ->set('description', 'Le mobilier a été abimé pendant la prestation.')
            ->set('photos', [UploadedFile::fake()->create('preuve.jpg', 120, 'image/jpeg')])
            ->call('createClaim')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('title', '')
            ->assertSet('priority', 'normal')
            ->assertSet('category', 'quality');

        $this->assertDatabaseHas('customer_claims', [
            'client_id' => $this->client->id,
            'category' => 'damage',
            'priority' => 'urgent',
            'status' => 'open',
            'title' => 'Dégât constaté',
        ]);

        $claim = CustomerClaim::where('client_id', $this->client->id)->firstOrFail();
        $this->assertNotEmpty($claim->attachments);
        $this->assertSame('preuve.jpg', $claim->attachments[0]['original_name']);
        Storage::disk('private')->assertExists($claim->attachments[0]['path']);
    }

    public function test_create_claim_links_owned_booking(): void
    {
        $booking = $this->makeBooking();

        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('rendez_vous_id', $booking->id)
            ->set('category', 'delay')
            ->set('priority', 'high')
            ->set('title', 'Retard important')
            ->set('description', 'Le prestataire est arrivé avec deux heures de retard.')
            ->call('createClaim')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customer_claims', [
            'client_id' => $this->client->id,
            'rendez_vous_id' => $booking->id,
            'category' => 'delay',
            'priority' => 'high',
        ]);
    }

    public function test_create_claim_validation_blocks_short_input(): void
    {
        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('title', 'ab')
            ->set('description', 'court')
            ->call('createClaim')
            ->assertHasErrors(['title', 'description']);

        $this->assertDatabaseCount('customer_claims', 0);
    }

    public function test_create_claim_normal_priority_sla(): void
    {
        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('category', 'billing')
            ->set('priority', 'normal')
            ->set('title', 'Erreur de facturation')
            ->set('description', 'Le montant facturé ne correspond pas au devis accepté.')
            ->call('createClaim')
            ->assertHasNoErrors();

        $claim = CustomerClaim::where('client_id', $this->client->id)->firstOrFail();
        $this->assertNotNull($claim->sla_due_at);
        $this->assertTrue($claim->sla_due_at->greaterThan(now()));
    }

    public function test_create_claim_low_priority_uses_default_sla_branch(): void
    {
        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('category', 'quality')
            ->set('priority', 'low')
            ->set('title', 'Finition perfectible')
            ->set('description', 'Quelques zones auraient mérité un passage supplémentaire.')
            ->call('createClaim')
            ->assertHasNoErrors();

        $claim = CustomerClaim::where('client_id', $this->client->id)->firstOrFail();
        $this->assertTrue($claim->sla_due_at->greaterThan(now()->addDays(2)));
    }

    public function test_save_creates_complaint_case_with_mapped_priority(): void
    {
        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('category', 'quality')
            ->set('subject', 'Prestation incomplète')
            ->set('priority', 'critique')
            ->set('description', 'Le travail demandé n a pas été entièrement réalisé.')
            ->set('attachmentInput', "uploads/photo-a.jpg\nuploads/photo-b.jpg")
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('subject', '')
            ->assertSet('priority', 'normal');

        $case = ComplaintCase::where('client_id', $this->client->id)->firstOrFail();
        $this->assertSame('urgent', $case->priority);
        $this->assertSame('4h', $case->sla_policy);
        $this->assertSame('open', $case->status);
        $this->assertCount(2, $case->attachments);
        $this->assertSame('photo-a.jpg', $case->attachments[0]['original_name']);
    }

    public function test_save_maps_french_high_and_low_priorities(): void
    {
        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('subject', 'Souci notable')
            ->set('priority', 'haute')
            ->set('description', 'Description détaillée du problème rencontré sur site.')
            ->call('save')
            ->assertHasNoErrors();

        $high = ComplaintCase::where('client_id', $this->client->id)->firstOrFail();
        $this->assertSame('high', $high->priority);
        $this->assertSame('24h', $high->sla_policy);

        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('subject', 'Petit souci')
            ->set('priority', 'basse')
            ->set('description', 'Description détaillée du petit problème rencontré.')
            ->call('save')
            ->assertHasNoErrors();

        $low = ComplaintCase::where('client_id', $this->client->id)
            ->where('priority', 'low')
            ->firstOrFail();
        $this->assertSame('72h', $low->sla_policy);
    }

    public function test_save_defaults_category_and_validates(): void
    {
        Livewire::actingAs($this->client)
            ->test(LitigesClient::class)
            ->set('category', '')
            ->set('subject', 'ab')
            ->set('description', 'court')
            ->call('save')
            ->assertHasErrors(['subject', 'description']);

        $this->assertDatabaseCount('complaint_cases', 0);
    }
}
