<?php

namespace Tests\Unit;

use App\Models\Parametre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametreModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_valeur_returns_default_when_key_does_not_exist(): void
    {
        $this->assertSame('fallback', Parametre::getValeur('missing.key', 'fallback'));
    }

    public function test_set_valeur_creates_and_updates_parameter(): void
    {
        Parametre::setValeur('booking.default_duration', '90');
        $this->assertSame('90', Parametre::getValeur('booking.default_duration'));

        Parametre::setValeur('booking.default_duration', '120');
        $this->assertSame('120', Parametre::getValeur('booking.default_duration'));

        $this->assertDatabaseHas('parametres', [
            'cle' => 'booking.default_duration',
            'valeur' => '120',
        ]);
    }
}
