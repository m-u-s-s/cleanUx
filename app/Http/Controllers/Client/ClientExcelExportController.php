<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Client\Exports\ClientBookingExcelExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 6.1 — Endpoint d'export Excel multi-onglets.
 */
class ClientExcelExportController extends Controller
{
    public function __construct(
        protected ClientBookingExcelExporter $exporter,
    ) {}

    public function bookings(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'from'                  => ['nullable', 'date'],
            'to'                    => ['nullable', 'date', 'after_or_equal:from'],
            'site_ids'              => ['nullable', 'array'],
            'site_ids.*'            => ['integer'],
            'statuses'              => ['nullable', 'array'],
            'statuses.*'            => ['string', 'max:32'],
        ]);

        return $this->exporter->export($request->user(), $filters);
    }
}
