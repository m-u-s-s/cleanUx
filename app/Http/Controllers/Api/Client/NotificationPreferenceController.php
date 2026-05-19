<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\NotificationPreferenceAudit;
use App\Services\NotificationPreferences\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationPreferenceController extends Controller
{
    public function __construct(protected NotificationPreferenceService $svc)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'channels' => (array) config('notification_preferences.channels'),
            'categories' => (array) config('notification_preferences.categories'),
            'forced_on' => (array) config('notification_preferences.forced_on'),
            'preferences' => $this->svc->getPreferences($request->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'string', 'max:16'],
            'category' => ['required', 'string', 'max:24'],
            'is_allowed' => ['required', 'boolean'],
        ]);

        try {
            $pref = $this->svc->setPreference(
                user: $request->user(),
                channel: $data['channel'],
                category: $data['category'],
                isAllowed: (bool) $data['is_allowed'],
                source: NotificationPreference::SOURCE_USER,
                request: $request,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'preference' => $pref]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array', 'min:1', 'max:100'],
            'preferences.*.channel' => ['required', 'string', 'max:16'],
            'preferences.*.category' => ['required', 'string', 'max:24'],
            'preferences.*.is_allowed' => ['required', 'boolean'],
        ]);

        $results = $this->svc->setMany(
            user: $request->user(),
            prefs: $data['preferences'],
            request: $request,
        );

        return response()->json([
            'ok' => true,
            'updated_count' => count($results),
            'preferences' => $this->svc->getPreferences($request->user()),
        ]);
    }

    public function audit(Request $request): JsonResponse
    {
        $rows = NotificationPreferenceAudit::query()
            ->forUser($request->user()->id)
            ->orderByDesc('changed_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }
}
