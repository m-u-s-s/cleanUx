<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 12 — Notifications API mobile.
 *
 * GET  /api/notifications              → liste paginée (DB notifications de Laravel)
 * POST /api/notifications/{id}/read    → marquer comme lue
 * POST /api/notifications/read-all     → tout marquer comme lu
 *
 * S'appuie sur le système Notification standard de Laravel + canal 'database'
 * (qui doit être dans le tableau via() de tes notifications).
 */
class ApiNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $params = $request->validate([
            'unread_only' => ['nullable', 'boolean'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'        => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($params['per_page'] ?? 20);
        $unreadOnly = filter_var($params['unread_only'] ?? false, FILTER_VALIDATE_BOOL);

        $query = $user->notifications();
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'ok'           => true,
            'data'         => collect($paginator->items())->map(fn ($n) => $this->serialize($n))->all(),
            'pagination'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json(['ok' => false, 'error' => 'Notification introuvable.'], 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok'           => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'ok'                 => true,
            'marked_as_read'     => $count,
            'unread_count'       => 0,
        ]);
    }

    protected function serialize($notification): array
    {
        return [
            'id'         => $notification->id,
            'type'       => class_basename($notification->type ?? ''),
            'data'       => $notification->data,
            'read_at'    => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
