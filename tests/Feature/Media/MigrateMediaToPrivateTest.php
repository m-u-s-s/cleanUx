<?php

namespace Tests\Feature\Media;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M3 — the backfill moves existing mission/dispute media from public to private, idempotently,
 * preserving the relative path.
 */
class MigrateMediaToPrivateTest extends TestCase
{
    public function test_moves_media_from_public_to_private_preserving_path(): void
    {
        Storage::fake('public');
        Storage::fake('private');

        Storage::disk('public')->put('claims/a.jpg', 'A');
        Storage::disk('public')->put('rendezvous/photos-avant/b.jpg', 'B');
        Storage::disk('public')->put('reports/mission-1.pdf', 'PDF');
        // A non-target file must be left alone.
        Storage::disk('public')->put('avatars/keep.jpg', 'KEEP');

        $this->artisan('media:migrate-to-private')->assertSuccessful();

        // Les trois fichiers verifies ensemble : une migration de media qui rate en rate
        // generalement plusieurs, et chacun reste alors PUBLIQUEMENT accessible.
        $ecarts = [];

        foreach (['claims/a.jpg', 'rendezvous/photos-avant/b.jpg', 'reports/mission-1.pdf'] as $p) {
            if (! Storage::disk('private')->exists($p)) {
                $ecarts[] = "{$p} : absent du disque prive";
            }

            if (Storage::disk('public')->exists($p)) {
                $ecarts[] = "{$p} : TOUJOURS accessible publiquement";
            }
        }

        $this->assertSame([], $ecarts, 'Ces medias n ont pas ete deplaces vers le disque prive.');

        // Avatar (public by design) untouched.
        $this->assertTrue(Storage::disk('public')->exists('avatars/keep.jpg'));

        // Idempotent: a second run moves nothing and still succeeds.
        $this->artisan('media:migrate-to-private')->assertSuccessful();
        $this->assertTrue(Storage::disk('private')->exists('claims/a.jpg'));
    }
}
