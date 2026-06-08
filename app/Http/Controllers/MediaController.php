<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * M3 — authenticated streaming of mission/dispute photos held on the PRIVATE disk.
 *
 * Security:
 *   - route is behind 'auth' + 'signed' middleware (must be logged in; URL signature is
 *     time-limited and prevents path tampering/enumeration)
 *   - path is constrained to the private disk; traversal segments are rejected
 *   - replaces the old public /storage/* links that exposed identity-bearing photos to anyone
 */
class MediaController extends Controller
{
    public function show(Request $request): StreamedResponse
    {
        $path = ltrim((string) $request->query('path'), '/');

        // Defence in depth: even though the URL is signed, never allow traversal.
        abort_if($path === '' || str_contains($path, '..'), 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($path), 404, 'Fichier introuvable.');

        return $disk->response($path);
    }
}
