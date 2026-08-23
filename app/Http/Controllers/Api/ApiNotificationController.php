<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 12 — Notifications API mobile.
 *
 * @group Notifications
 *
 * @authenticated
 */
class ApiNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $params = $request->validate([
            'unread_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($params['per_page'] ?? 20);
        $unreadOnly = filter_var($params['unread_only'] ?? false, FILTER_VALIDATE_BOOL);

        $query = $user->notifications();
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => collect($paginator->items())->map(fn ($n) => $this->serialize($n, $user))->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
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
            'ok' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'marked_as_read' => $count,
            'unread_count' => 0,
        ]);
    }

    /** La fiche complète d'une notification — tout son contenu et où aller pour régler le problème. */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user?->notifications()->whereKey($id)->first();

        if (! $notification) {
            return response()->json(['ok' => false, 'error' => 'Notification introuvable.'], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $this->serialize($notification, $user),
        ]);
    }

    /** CE QUE L'ÉCRAN AFFICHE DOIT ÊTRE CE QUE L'API ENVOIE. */
    protected function serialize($notification, ?User $user = null): array
    {
        $presenter = app(NotificationPresenter::class);
        $actionUrl = $presenter->actionUrl($notification, $user);

        // LE CHEMIN, EN PLUS DE L'URL — c'est ce dont le natif a besoin.
        $hoteCible = parse_url($actionUrl, PHP_URL_HOST);
        $hotesConnus = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            request()->getHost(),
        ]);

        $chemin = ($hoteCible === null || in_array($hoteCible, $hotesConnus, true))
            ? (string) parse_url($actionUrl, PHP_URL_PATH)
            : '';

        return [
            'id' => $notification->id,
            'type' => class_basename($notification->type ?? ''),
            'type_key' => $presenter->typeKey($notification),
            'label' => $presenter->label($notification),
            'title' => $presenter->title($notification),
            'body' => $presenter->message($notification),
            'severity' => $presenter->severity($notification),
            'context' => $presenter->context($notification),
            'action_url' => $actionUrl,
            'action_path' => $chemin,
            'action_label' => $presenter->actionLabel($notification, $user),
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
