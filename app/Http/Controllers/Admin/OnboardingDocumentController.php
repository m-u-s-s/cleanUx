<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderOnboardingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 14.1 — Sert les documents KYC depuis le disk privé pour les admins.
 *
 * Route signée temporaire (valide 10 min) pour preview dans la modale admin.
 *
 * Sécurité :
 *   - Route signée (URL::temporarySignedRoute)
 *   - Middleware role:admin
 *   - Le fichier est sur disque privé (storage/app/private/)
 */
class OnboardingDocumentController extends Controller
{
    public function show(Request $request, ProviderOnboardingDocument $document): StreamedResponse
    {
        // Validation signature URL (Laravel le fait via middleware 'signed')
        // mais on double-check aussi le rôle admin
        $user = $request->user();
        $isAdmin = $user && method_exists($user, 'isPlatformAdmin')
                && $user->isPlatformAdmin();

        abort_unless($isAdmin, 403, 'Accès admin requis.');

        $disk = Storage::disk('private');

        abort_unless($disk->exists($document->file_path), 404, 'Fichier introuvable.');

        return $disk->response(
            $document->file_path,
            $document->file_name ?? basename($document->file_path),
            ['Content-Type' => $document->mime_type ?? 'application/octet-stream'],
        );
    }
}
