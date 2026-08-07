<?php

namespace Tests\Feature\Email;

use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\User;
use App\Services\Email\ProductEmailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEmailTemplatesCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    public function test_definitions_returns_all_known_keys(): void
    {
        $definitions = ProductEmailTemplates::definitions();

        $this->assertIsArray($definitions);
        $this->assertArrayHasKey('booking_confirmed', $definitions);
        $this->assertArrayHasKey('booking_reminder', $definitions);
        $this->assertArrayHasKey('feedback_request', $definitions);
        $this->assertArrayHasKey('finance_reminder', $definitions);
        $this->assertArrayHasKey('new_booking_admin', $definitions);
        $this->assertArrayHasKey('status_update', $definitions);
        $this->assertSame('Rendez-vous confirmé', $definitions['booking_confirmed']);
    }

    public function test_sample_recipient_is_a_client_user(): void
    {
        $recipient = ProductEmailTemplates::sampleRecipient();

        $this->assertInstanceOf(User::class, $recipient);
        $this->assertSame('Client Démo', $recipient->name);
        $this->assertSame('client@example.test', $recipient->email);
        $this->assertSame(User::ROLE_CLIENT, $recipient->role);
    }

    public function test_sample_rendez_vous_carries_client_and_employe_relations(): void
    {
        $rdv = ProductEmailTemplates::sampleRendezVous();

        $this->assertInstanceOf(Booking::class, $rdv);
        $this->assertSame('Bruxelles', $rdv->ville);
        $this->assertInstanceOf(User::class, $rdv->client);
        $this->assertInstanceOf(User::class, $rdv->employe);
        $this->assertSame(User::ROLE_EMPLOYE, $rdv->employe->role);
    }

    public function test_sample_invoice_holds_demo_amounts(): void
    {
        $invoice = ProductEmailTemplates::sampleInvoice();

        $this->assertInstanceOf(FinanceInvoice::class, $invoice);
        $this->assertSame('FAC-DEMO-001', $invoice->invoice_number);
        $this->assertNotNull($invoice->due_at);
    }

    /**
     * @dataProvider knownKeysProvider
     */
    public function test_payload_returns_consistent_template_for_known_keys(string $key): void
    {
        $payload = ProductEmailTemplates::payload($key);

        $this->assertSame($key, $payload['template_key']);
        $this->assertArrayHasKey('subject', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('intro', $payload);
        $this->assertArrayHasKey('details', $payload);
        $this->assertArrayHasKey('tone', $payload);
        $this->assertIsArray($payload['details']);
    }

    public static function knownKeysProvider(): array
    {
        return [
            'booking_confirmed' => ['booking_confirmed'],
            'booking_reminder' => ['booking_reminder'],
            'feedback_request' => ['feedback_request'],
            'finance_reminder' => ['finance_reminder'],
            'new_booking_admin' => ['new_booking_admin'],
            'status_update' => ['status_update'],
        ];
    }

    public function test_booking_confirmed_payload_has_success_tone_and_action(): void
    {
        $payload = ProductEmailTemplates::payload('booking_confirmed');

        $this->assertSame('success', $payload['tone']);
        $this->assertSame('Voir mon espace client', $payload['action_text']);
        $this->assertStringContainsString('/dashboard/client', $payload['action_url']);
        $this->assertCount(3, $payload['details']);
    }

    public function test_finance_reminder_payload_formats_money_and_due_date(): void
    {
        $payload = ProductEmailTemplates::payload('finance_reminder');

        $this->assertSame('danger', $payload['tone']);
        $values = array_column($payload['details'], 'value', 'label');
        $this->assertSame('FAC-DEMO-001', $values['Facture']);
        $this->assertStringContainsString('€', $values['Montant total']);
        $this->assertStringContainsString('€', $values['Reste à payer']);
        $this->assertNotEmpty($values['Échéance']);
    }

    public function test_new_booking_admin_payload_uses_client_name(): void
    {
        $payload = ProductEmailTemplates::payload('new_booking_admin');

        $values = array_column($payload['details'], 'value', 'label');
        $this->assertSame('Client Démo', $values['Client']);
        $this->assertSame('Normale', $values['Priorité']);
    }

    public function test_feedback_request_payload_links_to_booking(): void
    {
        $payload = ProductEmailTemplates::payload('feedback_request');

        $this->assertSame('warning', $payload['tone']);
        $this->assertStringContainsString('/feedback/ajouter', $payload['action_url']);
    }

    public function test_status_update_payload_has_success_tone(): void
    {
        $payload = ProductEmailTemplates::payload('status_update');

        $this->assertSame('success', $payload['tone']);
        $this->assertStringContainsString('/dashboard/client/rendez-vous', $payload['action_url']);
    }

    public function test_booking_reminder_payload_has_info_tone(): void
    {
        $payload = ProductEmailTemplates::payload('booking_reminder');

        $this->assertSame('info', $payload['tone']);
        $this->assertCount(3, $payload['details']);
    }

    public function test_payload_falls_back_to_generic_for_unknown_key(): void
    {
        $payload = ProductEmailTemplates::payload('totally_unknown_key');

        $this->assertSame('generic', $payload['template_key']);
        $this->assertSame('Notification Brio', $payload['subject']);
        $this->assertSame('info', $payload['tone']);
        $this->assertSame([], $payload['details']);
    }
}
