<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\FinanceInvoice;
use App\Support\Finance\ClientFinanceDocumentScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class InvoiceApiController extends Controller
{
    public function download(Request $request, int $id): Response
    {
        $invoice = ClientFinanceDocumentScope::apply(
            FinanceInvoice::query(),
            $request->user(),
        )->findOrFail($id);

        $invoice->loadMissing(['client', 'organizationAccount', 'rendezVous.serviceZone', 'rendezVous.organizationSite', 'payments']);

        $filename = 'facture-'.($invoice->invoice_number ?: $invoice->id).'.pdf';

        try {
            return Pdf::loadView('client.finance.invoice-pdf', ['invoice' => $invoice])->download($filename);
        } catch (\Throwable) {
            return response()->view('client.finance.invoice-pdf', ['invoice' => $invoice], 200);
        }
    }

    public function show(Request $request, int $id): InvoiceResource
    {
        $invoice = ClientFinanceDocumentScope::apply(
            FinanceInvoice::query()->with(['payments', 'reminders']),
            $request->user(),
        )->findOrFail($id);

        return new InvoiceResource($invoice);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ClientFinanceDocumentScope::apply(
            FinanceInvoice::query()->with(['rendezVous']),
            $request->user(),
        );

        $status = (string) $request->query('status', 'all');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('invoice_number', 'like', '%'.$search.'%');
        }

        match ((string) $request->query('sort', 'recent')) {
            'oldest' => $query->orderBy('issued_at'),
            'amount_desc' => $query->orderByDesc('total_amount'),
            'amount_asc' => $query->orderBy('total_amount'),
            default => $query->orderByDesc('issued_at'),
        };

        return InvoiceResource::collection($query->paginate(30));
    }
}
