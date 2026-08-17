<?php

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * LA SONDE DE SANTÉ NE DOIT PAS RETIRER DU SERVICE UNE APPLICATION QUI FONCTIONNE.
 *
 * `/api/health` déclarait `stripe` et `reverb` à `null` avec le commentaire « soft-fail (not
 * counted as degraded) », puis les blocs `try` leur ASSIGNAIENT un booléen quelques lignes plus
 * bas. Au moment du filtre `$v !== null`, plus une seule n'était nulle : une clé Stripe absente
 * produisait `false`, comptait comme dépendance dure, et la route répondait 503.
 *
 * CE QUE ÇA VAUT EN EXPLOITATION. Un répartiteur de charge qui lit cette route retire alors du
 * service une application parfaitement capable de servir des pages — elle ne peut simplement pas
 * encaisser. Ce n'est pas la même chose, et c'est précisément ce que « souple » voulait dire.
 *
 * Sur ce dépôt le cas n'est pas théorique : aucun secret n'est configuré, et la clé Stripe est un
 * gabarit.
 */
class SondeDeSanteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * UN ENVIRONNEMENT OÙ LES DÉPENDANCES DURES VONT BIEN.
     *
     * La suite tourne avec `QUEUE_CONNECTION=sync` et sans serveur Redis : les deux sondes DURES
     * sont donc fausses par construction, et toute mesure des sondes SOUPLES serait noyée dedans.
     * Sans ce montage, le test mesurerait la configuration de la suite, pas le comportement de la
     * sonde — et c'est exactement ce qu'il a fait à sa première exécution.
     */
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

    /**
     * L'ÉTAT RESTE PUBLIÉ, il n'est simplement plus bloquant.
     *
     * Masquer la sonde plutôt que la déclasser priverait la supervision de l'information : « on ne
     * peut pas encaisser » doit se voir, sans pour autant couper le trafic.
     */
    public function test_letat_des_sondes_souples_reste_visible(): void
    {
        $this->dependancesDuresSaines();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonStructure(['status', 'checks' => ['app', 'database', 'stripe', 'reverb']]);
    }

    /**
     * TÉMOIN — une dépendance DURE fait toujours basculer en 503.
     *
     * Sans lui, les tests ci-dessus passeraient au vert sur une sonde qui répondrait « healthy »
     * quoi qu'il arrive : elle ne mesurerait plus rien, et personne ne s'en apercevrait avant la
     * panne suivante.
     */
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
