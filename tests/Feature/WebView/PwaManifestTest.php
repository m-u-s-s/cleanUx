<?php

namespace Tests\Feature\WebView;

use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    public function test_manifest_is_served_and_installable(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $manifest = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($manifest);

        // Un manifeste ampute ne s'installe pas : la liste complete evite de le corriger cle
        // par cle, une execution a la fois.
        $manquantes = array_values(array_filter(
            ['name', 'short_name', 'start_url', 'display', 'icons'],
            fn (string $k) => ! array_key_exists($k, $manifest),
        ));

        $this->assertSame([], $manquantes, 'Ces cles manquent au manifeste : l application ne s installera pas.');

        $this->assertContains($manifest['display'], ['standalone', 'fullscreen', 'minimal-ui']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_service_worker_is_present(): void
    {
        $this->assertFileExists(public_path('sw.js'));
    }
}
