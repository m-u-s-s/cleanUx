<?php

namespace Tests\Feature\I18n;

use Tests\TestCase;

class LocaleListApiTest extends TestCase
{
    public function test_endpoint_returns_locales_with_metadata(): void
    {
        $response = $this->getJson('/api/locales');

        $response->assertOk();
        $response->assertJsonStructure([
            'default',
            'fallback',
            'current',
            'locales' => [
                '*' => ['code', 'name', 'native_name', 'flag', 'priority'],
            ],
        ]);

        $codes = collect($response->json('locales'))->pluck('code')->all();
        $this->assertContains('fr', $codes);
        $this->assertContains('es', $codes);
        $this->assertContains('it', $codes);
        $this->assertContains('de', $codes);
    }
}
