<?php

namespace Tests\Feature\WebhooksV2;

use App\Jobs\WebhooksV2\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UN CONTRAT ÉCRIT EN BASE, SANS MOTEUR POUR L'APPLIQUER.
 *
 * `WebhookDeliveryRunner` écrivait `next_retry_at`, `scopeDue()` savait le lire — et ce scope
 * n'avait AUCUN appelant. `DeliverWebhookJob` porte `$tries = 1` en renvoyant justement à ce
 * mécanisme : la politique était écrite des deux côtés et exécutée par personne.
 */
class LaReprisePossedeEnfinUnMoteurTest extends TestCase
{
    use RefreshDatabase;

    private function livraison(array $ecrasements = []): WebhookDelivery
    {
        return WebhookDelivery::query()->create(array_merge([
            'event_id' => WebhookEvent::factory()->create()->id,
            'endpoint_id' => WebhookEndpoint::factory()->create()->id,
            'status' => WebhookDelivery::STATUS_FAILED,
            'attempt' => 1,
            'max_attempts' => 6,
            'next_retry_at' => now()->subMinute(),
        ], $ecrasements));
    }

    #[Test]
    public function une_livraison_echue_repart_en_file(): void
    {
        Queue::fake();
        $livraison = $this->livraison();

        $this->artisan('webhooks:rejouer-les-livraisons')->assertExitCode(0);

        Queue::assertPushed(DeliverWebhookJob::class, fn ($job) => $job->deliveryId === $livraison->id);
    }

    /** LE TÉMOIN NÉGATIF : une reprise pas encore due attend son tour, elle n'est pas rejouée. */
    #[Test]
    public function une_reprise_future_n_est_pas_rejouee(): void
    {
        Queue::fake();
        $this->livraison(['next_retry_at' => now()->addHour()]);

        $this->artisan('webhooks:rejouer-les-livraisons')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** Une livraison terminale ne se rejoue jamais — livrée, morte ou annulée. */
    #[Test]
    public function une_livraison_terminale_reste_tranquille(): void
    {
        Queue::fake();
        $this->livraison([
            'status' => WebhookDelivery::STATUS_DELIVERED,
            'next_retry_at' => now()->subDay(),
        ]);

        $this->artisan('webhooks:rejouer-les-livraisons')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** Le scope existait et n'était appelé par personne : on vérifie qu'il l'est désormais. */
    #[Test]
    public function la_commande_est_planifiee(): void
    {
        $kernel = (string) file_get_contents(base_path('app/Console/Kernel.php'));

        $this->assertStringContainsString('webhooks:rejouer-les-livraisons', $kernel);
    }
}
