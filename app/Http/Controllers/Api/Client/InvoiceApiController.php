<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\FinanceInvoice;
use App\Support\Finance\ClientFinanceDocumentScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceApiController extends Controller
{
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
