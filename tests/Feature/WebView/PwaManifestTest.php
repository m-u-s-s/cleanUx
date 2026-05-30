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

        foreach (['name', 'short_name', 'start_url', 'display', 'icons'] as $key) {
            $this->assertArrayHasKey($key, $manifest, "manifest missing '{$key}'");
        }

        $this->assertContains($manifest['display'], ['standalone', 'fullscreen', 'minimal-ui']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_service_worker_is_present(): void
    {
        $this->assertFileExists(public_path('sw.js'));
    }
}
