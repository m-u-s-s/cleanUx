<?php

namespace Database\Seeders;

use App\Models\WebhookEndpoint;
use App\Models\WebhookSubscription;
use Illuminate\Database\Seeder;

class WebhookEndpointsSeeder extends Seeder
{
    /**
     * Pas d'endpoint live par défaut — c'est un module B2B custom configuré par admin.
     * Cette seeder crée un endpoint "demo" (suspended par défaut) pour donner un exemple
     * d'intégration aux admins, sans risque d'envoyer du trafic en prod.
     */
    public function run(): void
    {
        if (WebhookEndpoint::query()->exists()) {
            return;
        }

        $ep = WebhookEndpoint::query()->create([
            'code' => WebhookEndpoint::generateCode(),
            'name' => 'Demo B2B endpoint (suspended)',
            'description' => 'Example endpoint — désactiver suspension et configurer URL pour activer.',
            'url' => 'https://example.com/cleanux/webhooks',
            'secret' => WebhookEndpoint::generateSecret(),
            'is_active' => true,
            'is_suspended' => true,
            'suspension_reason' => 'Demo endpoint — modifier URL puis désactiver suspension',
            'max_attempts' => 6,
            'timeout_seconds' => 15,
        ]);

        $defaultEvents = [
            'booking.created',
            'booking.completed',
            'booking.cancelled',
            'payment.succeeded',
            'payment.refunded',
        ];

        foreach ($defaultEvents as $code) {
            WebhookSubscription::query()->create([
                'endpoint_id' => $ep->id,
                'event_code' => $code,
                'is_active' => true,
            ]);
        }
    }
}
