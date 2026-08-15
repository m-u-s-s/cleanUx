<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Notifications
 *
 * @authenticated
 *
 * Phase 12 — Notifications API mobile.
 *
 * GET  /api/notifications              → liste paginée (DB notifications de Laravel)
 * GET  /api/notifications/{id}         → la fiche complète d'une notification
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

    /**
     * La fiche complète d'une notification — tout son contenu et où aller pour régler le problème.
     *
     * L'APPARTENANCE EST DANS LA REQUÊTE. Chercher par `$user->notifications()` plutôt que par
     * `DatabaseNotification::find()` : la notification d'autrui n'existe pas, le 404 est le
     * résultat de la requête et non un contrôle qu'on pourrait oublier d'écrire.
     *
     * La lecture n'est PAS posée ici : un GET ne doit pas modifier l'état. L'écran mobile appelle
     * `POST /notifications/{id}/read` à l'ouverture, ce qui rend le geste explicite et rejouable.
     */
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

    /**
     * CE QUE L'ÉCRAN AFFICHE DOIT ÊTRE CE QUE L'API ENVOIE.
     *
     * Cette méthode ne rendait que `id`, `type`, `data`, `read_at` et `created_at`. Les deux
     * applications natives, elles, affichent `item.title` et `item.body` — deux champs que le
     * serveur n'a jamais produits. Résultat constaté : la liste chargeait bien ses 18 lignes et
     * les rendait TOUTES VIDES, deux `Text` sans contenu au-dessus d'une date. « Les notifications
     * ne s'affichent pas » ne venait pas d'un appel raté mais d'un contrat jamais tenu.
     *
     * `body` n'existait d'ailleurs nulle part : la clé du payload est `message`.
     *
     * Le contenu vient de `NotificationPresenter`, la même source que le web — sans quoi la même
     * notification s'intitulerait « Rendez-vous » sur un écran et « RappelRdv » sur l'autre.
     * `type` et `data` restent en place : un client déjà déployé continue de fonctionner.
     */
    protected function serialize($notification, ?User $user = null): array
    {
        $presenter = app(NotificationPresenter::class);
        $actionUrl = $presenter->actionUrl($notification, $user);

        /*
         * LE CHEMIN, EN PLUS DE L'URL — c'est ce dont le natif a besoin.
         *
         * Les applications servent les pages web dans l'hôte WebView (`EmbeddedModule`), qui
         * attend un CHEMIN et porte la session. Leur envoyer une URL absolue les obligerait à la
         * découper elles-mêmes, deux fois, avec deux résultats possibles. Le serveur sait quelle
         * partie est un chemin de l'application : il la donne.
         *
         * ON COMPARE LES HÔTES, PAS LES PRÉFIXES DE CHAÎNE. Un `str_starts_with($url, config(
         * 'app.url'))` paraît suffire et ne l'est pas : `route()` fabrique ses URL à partir de
         * l'hôte de la REQUÊTE, ici `127.0.0.1:8000`, quand `APP_URL` vaut `localhost`. Le
         * préfixe ne correspondait jamais et le chemin repartait vide — vérifié à l'appel.
         *
         * Vide quand la cible est ailleurs (un `action_url` de payload vers un autre domaine) :
         * le natif ouvre alors le navigateur plutôt que d'embarquer une page qui n'est pas à nous.
         */
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
