<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\AccountingExport;
use App\Models\AccountingPeriod;
use App\Services\AccountingV2\AccountingService;
use App\Services\AccountingV2\ExportManager;
use App\Services\AccountingV2\PeriodCloser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountingV2Controller extends Controller
{
    public function __construct(
        protected AccountingService $accounting,
        protected PeriodCloser $closer,
        protected ExportManager $exports,
    ) {}

    public function listEntries(Request $request): JsonResponse
    {
        $q = AccountingEntry::query();
        if ($request->filled('year')) {
            $q->forPeriod((int) $request->integer('year'), $request->filled('month') ? (int) $request->integer('month') : null);
        }
        if ($request->filled('account_code')) {
            $q->where('account_code', $request->string('account_code'));
        }
        if ($request->filled('journal_code')) {
            $q->where('journal_code', $request->string('journal_code'));
        }
        if ($request->filled('batch_id')) {
            $q->where('batch_id', $request->string('batch_id'));
        }
        $rows = $q->orderByDesc('posting_date')->orderByDesc('id')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function accountBalance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_code' => ['required', 'string', 'max:16'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $balance = $this->accounting->balanceForAccount(
            $data['account_code'],
            $data['year'] ?? null,
            $data['month'] ?? null,
        );
        return response()->json($balance);
    }

    public function listPeriods(Request $request): JsonResponse
    {
        $rows = AccountingPeriod::query()
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit((int) $request->integer('limit', 36))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function closePeriod(Request $request, int $year, int $month): JsonResponse
    {
        try {
            $period = $this->closer->close($year, $month, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'period' => $period]);
    }

    public function reopenPeriod(Request $request, AccountingPeriod $period): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        try {
            $row = $this->closer->reopen($period, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'period' => $row]);
    }

    public function postEntries(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'max:16'],
            'lines.*.debit_cents' => ['nullable', 'integer', 'min:0'],
            'lines.*.credit_cents' => ['nullable', 'integer', 'min:0'],
            'lines.*.label' => ['required', 'string', 'max:500'],
            'journal_code' => ['nullable', 'string', 'in:VEN,ACH,BANK,OD,INV'],
            'posting_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $batchId = $this->accounting->post($data['lines'], [
                'journal_code' => $data['journal_code'] ?? 'OD',
                'posting_date' => $data['posting_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'posted_by_user_id' => $request->user()?->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'batch_id' => $batchId], 201);
    }

    public function listExports(Request $request): JsonResponse
    {
        $rows = AccountingExport::query()
            ->orderByDesc('created_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function generateExport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'format' => ['required', 'string', 'in:csv,fec,sage,quickbooks_iif'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $export = $this->exports->generate(
            $data['format'],
            $data['year'],
            $data['month'] ?? null,
            $request->user()?->id,
        );
        return response()->json(['ok' => $export->status === AccountingExport::STATUS_READY, 'export' => $export]);
    }

    public function downloadExport(AccountingExport $export): Response
    {
        if ($export->status !== AccountingExport::STATUS_READY || ! $export->file_path) {
            abort(404, 'Export non disponible.');
        }
        $disk = (string) config('accounting_v2.export_storage_disk', 'local');
        if (! Storage::disk($disk)->exists($export->file_path)) {
            abort(404);
        }
        $mime = data_get($export->metadata, 'mime', 'application/octet-stream');
        return response(
            Storage::disk($disk)->get($export->file_path),
            200,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'attachment; filename="' . basename($export->file_path) . '"',
            ],
        );
    }
}
