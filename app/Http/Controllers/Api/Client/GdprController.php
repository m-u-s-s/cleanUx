<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
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

class GdprController extends Controller
{
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

    public function requestErasure(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['required', 'accepted'],
        ]);

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
