<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function __construct(protected AuditService $svc)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $rows = AuditEvent::query()
            ->when($request->filled('domain'), fn ($q) => $q->where('domain', $request->string('domain')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->when($request->filled('event_type'), fn ($q) => $q->where('event_type', 'like', '%' . $request->string('event_type') . '%'))
            ->when($request->filled('actor_id'), fn ($q) => $q->where('actor_id', $request->integer('actor_id')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('occurred_at', '<', $request->date('to')))
            ->when($request->boolean('pinned_only'), fn ($q) => $q->where('is_pinned', true))
            ->orderByDesc('occurred_at')
            ->limit(min((int) $request->integer('limit', 100), 500))
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function show(AuditEvent $event): JsonResponse
    {
        return response()->json(['data' => $event]);
    }

    public function pin(AuditEvent $event): JsonResponse
    {
        $row = $this->svc->pin($event);
        return response()->json(['ok' => true, 'event' => $row]);
    }

    public function unpin(AuditEvent $event): JsonResponse
    {
        $row = $this->svc->unpin($event);
        return response()->json(['ok' => true, 'event' => $row]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = (string) $request->string('format', 'csv');

        $query = AuditEvent::query()
            ->when($request->filled('domain'), fn ($q) => $q->where('domain', $request->string('domain')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->when($request->filled('from'), fn ($q) => $q->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('occurred_at', '<', $request->date('to')))
            ->orderByDesc('occurred_at');

        if ($format === 'json') {
            $filename = 'audit-events-' . now()->format('Ymd-His') . '.json';
            return response()->streamDownload(function () use ($query) {
                echo '[';
                $first = true;
                $query->chunkById(500, function ($rows) use (&$first) {
                    foreach ($rows as $r) {
                        if (! $first) {
                            echo ',';
                        }
                        echo json_encode($r);
                        $first = false;
                    }
                });
                echo ']';
            }, $filename, ['Content-Type' => 'application/json']);
        }

        $filename = 'audit-events-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($query) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['id', 'event_type', 'domain', 'severity', 'actor_label', 'subject_type', 'subject_id', 'subject_label', 'occurred_at']);
            $query->chunkById(500, function ($rows) use ($fh) {
                foreach ($rows as $r) {
                    fputcsv($fh, [
                        $r->id, $r->event_type, $r->domain, $r->severity,
                        $r->actor_label, $r->subject_type, $r->subject_id, $r->subject_label,
                        $r->occurred_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($fh);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
