<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Services\Disputes\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderDisputeController extends Controller
{
    public function __construct(protected DisputeService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $items = ComplaintCase::query()
            ->where('provider_user_id', $request->user()->id)
            ->latest('last_activity_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $items]);
    }

    public function respond(Request $request, ComplaintCase $dispute): JsonResponse
    {
        abort_unless((int) $dispute->provider_user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $event = $this->service->addMessage(
            $dispute,
            $request->user(),
            DisputeEvent::ROLE_PROVIDER,
            $data['body'],
        );

        return response()->json(['event_id' => $event->id], 201);
    }
}
