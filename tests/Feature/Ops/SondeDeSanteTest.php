<?php

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/** LA SONDE DE SANTÉ NE DOIT PAS RETIRER DU SERVICE UNE APPLICATION QUI FONCTIONNE. */
class SondeDeSanteTest extends TestCase
{
    use RefreshDatabase;

    /** UN ENVIRONNEMENT OÙ LES DÉPENDANCES DURES VONT BIEN. */
    private function dependancesDuresSaines(): void
    {
        config()->set('queue.default', 'database');
        Redis::shouldReceive('ping')->andReturnTrue();
    }

    public function test_une_cle_stripe_absente_ne_rend_pas_lapplication_malade(): void
    {
        $this->dependancesDuresSaines();
        config()->set('services.stripe.secret', null);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('checks.stripe', false);
    }

    public function test_une_cle_reverb_absente_ne_rend_pas_lapplication_malade(): void
    {
        $this->dependancesDuresSaines();
        config()->set('broadcasting.connections.reverb.key', null);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('checks.reverb', false);
    }

    /** L'ÉTAT RESTE PUBLIÉ, il n'est simplement plus bloquant. */
    public function test_letat_des_sondes_souples_reste_visible(): void
    {
        $this->dependancesDuresSaines();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonStructure(['status', 'checks' => ['app', 'database', 'stripe', 'reverb']]);
    }

    /** TÉMOIN — une dépendance DURE fait toujours basculer en 503. */
    public function test_temoin_une_dependance_dure_degrade_toujours(): void
    {
        $this->dependancesDuresSaines();

        // Puis on casse la seule dépendance dure qui nous intéresse : une file en `sync` veut dire
        // que rien n'est traité en arrière-plan — ni les paiements différés, ni les notifications.
        config()->set('queue.default', 'sync');

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded');
    }
}
