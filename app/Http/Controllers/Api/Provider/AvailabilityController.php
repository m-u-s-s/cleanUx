<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Services\Availability\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class AvailabilityController extends Controller
{
    public function __construct(protected AvailabilityService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'slots' => AvailabilitySlot::query()
                ->forProvider($user->id)
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get(),
            'exceptions' => AvailabilityException::query()
                ->forProvider($user->id)
                ->orderBy('date', 'desc')
                ->limit(200)
                ->get(),
        ]);
    }

    public function storeSlot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $slot = AvailabilitySlot::create([
            'provider_user_id' => $request->user()->id,
            'weekday' => (int) $data['weekday'],
            'start_time' => $data['start_time'] . ':00',
            'end_time' => $data['end_time'] . ':00',
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'timezone' => $data['timezone'] ?? config('availability.default_timezone'),
            'is_active' => true,
        ]);

        return response()->json(['ok' => true, 'slot' => $slot], 201);
    }

    public function updateSlot(Request $request, AvailabilitySlot $slot): JsonResponse
    {
        if ($slot->provider_user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'weekday' => ['sometimes', 'integer', 'between:0,6'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['start_time'])) {
            $data['start_time'] .= ':00';
        }
        if (isset($data['end_time'])) {
            $data['end_time'] .= ':00';
        }

        $slot->fill($data)->save();

        return response()->json(['ok' => true, 'slot' => $slot->fresh()]);
    }

    public function destroySlot(Request $request, AvailabilitySlot $slot): JsonResponse
    {
        if ($slot->provider_user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $slot->delete();
        return response()->json(['ok' => true]);
    }

    public function storeException(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'exception_type' => ['required', 'in:closed,open_override,partial'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

        if (in_array($data['exception_type'], ['open_override', 'partial'], true)) {
            if (empty($data['start_time']) || empty($data['end_time'])) {
                return response()->json([
                    'ok' => false,
                    'errors' => ['exception_type' => 'start_time et end_time requis pour ce type.'],
                ], 422);
            }
        }

        $exc = AvailabilityException::create([
            'provider_user_id' => $request->user()->id,
            'date' => $data['date'],
            'exception_type' => $data['exception_type'],
            'start_time' => isset($data['start_time']) ? $data['start_time'] . ':00' : null,
            'end_time' => isset($data['end_time']) ? $data['end_time'] . ':00' : null,
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['ok' => true, 'exception' => $exc], 201);
    }

    public function destroyException(Request $request, AvailabilityException $exception): JsonResponse
    {
        if ($exception->provider_user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $exception->delete();
        return response()->json(['ok' => true]);
    }

    public function windows(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($data['from']);
        $to = CarbonImmutable::parse($data['to']);

        $windows = $this->service->getAvailableWindows($request->user(), $from, $to);

        $serialized = array_map(fn ($w) => [
            'start' => $w['start']->toIso8601String(),
            'end' => $w['end']->toIso8601String(),
        ], $windows);

        return response()->json(['data' => $serialized]);
    }

    /**
     * iCal feed export — RFC 5545 minimal.
     * Returns text/calendar with each available window as a VEVENT (busy=FREE).
     */
    public function ical(Request $request): Response
    {
        $user = $request->user();

        $from = CarbonImmutable::now()->startOfDay();
        $to = $from->addDays((int) config('availability.max_lookahead_days', 90));

        $windows = $this->service->getAvailableWindows($user, $from, $to);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CleanUx//Availability//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($windows as $i => $w) {
            $uid = sprintf('avail-%d-%s@cleanux', $user->id, $w['start']->format('YmdHis'));
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . CarbonImmutable::now()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $w['start']->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $w['end']->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:Disponible';
            $lines[] = 'TRANSP:TRANSPARENT';  // available time
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cleanux-availability-' . $user->id . '.ics"',
        ]);
    }
}
