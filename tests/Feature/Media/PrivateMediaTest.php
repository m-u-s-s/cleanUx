<?php

namespace Tests\Feature\Media;

use App\Models\User;
use App\Support\Media\PrivateMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** M3 — mission/dispute photos are served from the private disk through an authenticated, signed route, never via a public /storage link. */
class PrivateMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_signed_request_streams_the_file(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('missions/photos-avant/x.jpg', 'BINARY');

        $url = PrivateMedia::url('missions/photos-avant/x.jpg');
        $this->assertNotNull($url);

        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertOk();
    }

    public function test_unsigned_request_is_forbidden(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('claims/y.jpg', 'BINARY');

        // Hit the route without a valid signature.
        $this->actingAs(User::factory()->create())
            ->get(route('media.private.show', ['path' => 'claims/y.jpg']))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('claims/z.jpg', 'BINARY');

        $url = PrivateMedia::url('claims/z.jpg');
        $this->get($url)->assertRedirect(); // auth middleware
    }

    public function test_missing_file_returns_404(): void
    {
        Storage::fake('private');

        $url = PrivateMedia::url('missions/photos-avant/nope.jpg');
        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertNotFound();
    }

    public function test_rendered_photo_surface_emits_signed_url_not_public_storage(): void
    {
        // The dispute claim-attachments partial must render image links through the signed
        // private-media route, never the public /storage/ symlink.
        $claim = (object) [
            'attachments' => [
                ['path' => 'claims/proof.jpg', 'original_name' => 'proof.jpg'],
            ],
        ];

        $html = view('livewire.client.litiges.claim-attachments', ['claim' => $claim])->render();

        $this->assertStringContainsString('media/private', $html);
        $this->assertStringContainsString('signature=', $html);
        $this->assertStringNotContainsString('/storage/claims', $html);
    }

    public function test_path_traversal_is_rejected(): void
    {
        Storage::fake('private');

        // Build a signed URL whose path contains traversal — controller must still 404.
        $url = PrivateMedia::url('../../.env');
        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertNotFound();
    }
}
