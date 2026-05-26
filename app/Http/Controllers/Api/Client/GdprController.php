<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\RequestGdprErasureRequest;
use App\Events\Gdpr\GdprExportReady;
use App\Events\Gdpr\GdprRequestCreated;
use App\Models\GdprDataRequest;
use App\Notifications\Gdpr\GdprExportReadyNotification;
use App\Notifications\Gdpr\GdprRequestCreatedNotification;
use App\Services\Gdpr\DataErasureService;
use App\Services\Gdpr\DataExportService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @group GDPR
 * @authenticated
 */
class GdprController extends Controller
{
    /**
     * List the authenticated user's GDPR data requests (most recent first, max 50).
     *
     * @response 200 {"data": [{"id": 1, "reference": "GDPR-ABCDEFGHIJ", "type": "export", "status": "fulfilled", "requested_at": "2026-06-01T10:00:00+00:00", "fulfilled_at": "2026-06-01T10:01:30+00:00", "grace_period_ends_at": null, "expires_at": "2026-06-08T10:01:30+00:00"}]}
     */
    public function index(Request $request): JsonResponse
    {
        $items = GdprDataRequest::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(50)
            ->get([
                'id', 'reference', 'type', 'status',
                'requested_at', 'fulfilled_at', 'grace_period_ends_at', 'expires_at',
            ]);

        return response()->json(['data' => $items]);
    }

    /**
     * Request a personal data export (GDPR Article 20 — data portability).
     *
     * The export is generated synchronously. The response includes a signed download URL
     * valid for 7 days. A second request while one is already processing returns the existing request.
     *
     * @response 201 {"request_id": 1, "reference": "GDPR-ABCDEFGHIJ", "status": "fulfilled", "download_url": "https://cleanux.be/api/gdpr/export/download/1?signature=xxx&expires=...", "expires_at": "2026-06-08T10:01:30+00:00"}
     * @response 200 scenario="Export already in progress" {"ok": true, "request_id": 1, "reference": "GDPR-ABCDEFGHIJ", "status": "processing", "note": "Un export est déjà en cours."}
     * @response 500 {"ok": false, "error": "Export generation failed."}
     */
    public function requestExport(Request $request): JsonResponse
    {
        $user = $request->user();

        $existing = GdprDataRequest::query()
            ->where('user_id', $user->id)
            ->where('type', GdprDataRequest::TYPE_EXPORT)
            ->whereIn('status', [GdprDataRequest::STATUS_PENDING, GdprDataRequest::STATUS_PROCESSING])
            ->first();

        if ($existing) {
            return response()->json([
                'ok' => true,
                'request_id' => $existing->id,
                'reference' => $existing->reference,
                'status' => $existing->status,
                'note' => 'Un export est déjà en cours.',
            ]);
        }

        $req = GdprDataRequest::create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_PROCESSING,
            'reference' => $this->generateReference(),
            'requested_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        ActivityLogger::log('gdpr.export_requested', $req, ['user_id' => $user->id]);
        GdprRequestCreated::dispatch($req);
        $user->notify(new GdprRequestCreatedNotification($req));

        try {
            app(DataExportService::class)->execute($req);
            $req->refresh();
            GdprExportReady::dispatch($req);
            $user->notify(new GdprExportReadyNotification($req));
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'request_id' => $req->id,
            'reference' => $req->reference,
            'status' => $req->status,
            'download_url' => $this->signedDownloadUrl($req),
            'expires_at' => $req->expires_at,
        ], 201);
    }

    /**
     * Request account and data erasure (GDPR Article 17 — right to erasure).
     *
     * Schedules erasure after a grace period (default 30 days) during which the request
     * can be cancelled. Cannot be submitted if an active erasure request already exists.
     *
     * @bodyParam confirm boolean required Must be accepted (1/true) to confirm intent. Example: 1
     * @bodyParam reason string Optional reason for the erasure request (max 2000 chars). Example: Je n'utilise plus le service.
     * @response 201 {"request_id": 2, "reference": "GDPR-ZZZZZZZZZZ", "status": "awaiting_grace_period", "grace_period_ends_at": "2026-07-01T10:00:00+00:00"}
     * @response 409 {"ok": false, "error": "Une demande d'erasure est déjà active.", "request_id": 2}
     * @response 422 {"message": "The confirm field must be accepted.", "errors": {"confirm": ["The confirm field must be accepted."]}}
     */
    public function requestErasure(RequestGdprErasureRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = $request->user();

        $existing = GdprDataRequest::query()
            ->where('user_id', $user->id)
            ->where('type', GdprDataRequest::TYPE_ERASURE)
            ->whereIn('status', [
                GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
                GdprDataRequest::STATUS_AWAITING_CONFIRMATION,
                GdprDataRequest::STATUS_PROCESSING,
            ])
            ->first();

        if ($existing) {
            return response()->json([
                'ok' => false,
                'error' => 'Une demande d\'erasure est déjà active.',
                'request_id' => $existing->id,
            ], 409);
        }

        $req = app(DataErasureService::class)->schedule($user, $data['reason'] ?? null, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        GdprRequestCreated::dispatch($req);
        $user->notify(new GdprRequestCreatedNotification($req));

        return response()->json([
            'request_id' => $req->id,
            'reference' => $req->reference,
            'status' => $req->status,
            'grace_period_ends_at' => $req->grace_period_ends_at,
        ], 201);
    }

    /**
     * Cancel a pending erasure request during the grace period.
     *
     * @response 200 {"request_id": 2, "status": "cancelled"}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 422 {"ok": false, "error": "Not an erasure request."}
     */
    public function cancelErasure(Request $request, GdprDataRequest $gdprRequest): JsonResponse
    {
        abort_unless((int) $gdprRequest->user_id === (int) $request->user()->id, 403);

        if ($gdprRequest->type !== GdprDataRequest::TYPE_ERASURE) {
            return response()->json(['ok' => false, 'error' => 'Not an erasure request.'], 422);
        }

        $cancelled = app(DataErasureService::class)
            ->cancel($gdprRequest, $request->user(), 'Annulé par utilisateur via API');

        return response()->json([
            'request_id' => $cancelled->id,
            'status' => $cancelled->status,
        ]);
    }

    /**
     * Download a fulfilled personal data export archive.
     *
     * Returns the JSON export file as an attachment. The signed URL is valid for 7 days.
     *
     * @response 200 scenario="File download" {"binary": "file content as attachment"}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 404 {"message": "Export not available"}
     * @response 410 {"message": "Export expired"}
     */
    public function downloadExport(Request $request, GdprDataRequest $gdprRequest): mixed
    {
        abort_unless((int) $gdprRequest->user_id === (int) $request->user()->id, 403);

        if ($gdprRequest->type !== GdprDataRequest::TYPE_EXPORT
            || $gdprRequest->status !== GdprDataRequest::STATUS_FULFILLED
            || ! $gdprRequest->export_file_path) {
            abort(404, 'Export not available');
        }

        if ($gdprRequest->expires_at && $gdprRequest->expires_at->isPast()) {
            abort(410, 'Export expired');
        }

        $disk = (string) config('gdpr.export_disk', 'local');

        ActivityLogger::log('gdpr.export_downloaded', $gdprRequest, [
            'user_id' => $request->user()->id,
        ]);

        return Storage::disk($disk)->download(
            $gdprRequest->export_file_path,
            $gdprRequest->reference . '.json',
        );
    }

    protected function signedDownloadUrl(GdprDataRequest $request): string
    {
        return URL::temporarySignedRoute(
            'api.gdpr.download',
            $request->expires_at ?? now()->addDays(7),
            ['gdprRequest' => $request->id],
        );
    }

    protected function generateReference(): string
    {
        $prefix = (string) config('gdpr.reference_prefix', 'GDPR');
        do {
            $candidate = $prefix . '-' . strtoupper(Str::random(10));
        } while (GdprDataRequest::where('reference', $candidate)->exists());

        return $candidate;
    }
}
