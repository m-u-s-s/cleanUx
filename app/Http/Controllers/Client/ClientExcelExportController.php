<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\Exports\ClientBookingExcelExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 6.1 — Endpoint d'export Excel multi-onglets.
 */
class ClientExcelExportController extends Controller
{
    public function __construct(
        protected ClientBookingExcelExporter $exporter,
    ) {}

    public function bookings(Request $request): StreamedResponse|RedirectResponse
    {
        // `phpoffice/phpspreadsheet` n'est pas une dépendance de ce projet :
        // l'exporteur a été écrit contre une bibliothèque absente, si bien que
        // le lien d'export a toujours répondu 500. Tant qu'elle n'est pas
        // installée, on renvoie l'utilisateur d'où il vient avec une phrase
        // qu'il comprend, plutôt qu'une page d'erreur.
        if (! class_exists(Spreadsheet::class)) {
            return back()->with('error', __("L'export Excel n'est pas disponible sur cette instance."));
        }

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => ['integer'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'max:32'],
        ]);

        return $this->exporter->export($request->user(), $filters);
    }
}
