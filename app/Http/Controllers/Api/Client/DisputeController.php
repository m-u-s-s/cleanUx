<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Services\Disputes\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function __construct(protected DisputeService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $items = ComplaintCase::query()
            ->where('client_id', $request->user()->id)
            ->latest('last_activity_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $items->map(fn (ComplaintCase $c) => [
                'id' => $c->id,
                'reference' => $c->reference,
                'subject' => $c->subject,
                'category' => $c->category,
                'priority' => $c->priority,
                'severity' => $c->severity,
                'status' => $c->status,
                'is_overdue' => $c->is_overdue,
                'sla_policy' => $c->sla_policy,
                'created_at' => $c->created_at,
                'last_activity_at' => $c->last_activity_at,
            ]),
        ]);
    }

    public function show(Request $request, ComplaintCase $dispute): JsonResponse
    {
        abort_unless((int) $dispute->client_id === (int) $request->user()->id, 403);

        $dispute->load([
            'events' => fn ($q) => $q->visibleTo(DisputeEvent::ROLE_CLIENT)->orderBy('created_at'),
            'events.author:id,name',
            'resolutions',
        ]);

        return response()->json([
            'id' => $dispute->id,
            'reference' => $dispute->reference,
            'subject' => $dispute->subject,
            'description' => $dispute->description,
            'status' => $dispute->status,
            'priority' => $dispute->priority,
            'category' => $dispute->category,
            'events' => $dispute->events->map(fn (DisputeEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'author_role' => $e->author_role,
                'author_name' => $e->author?->name,
                'body' => $e->body,
                'created_at' => $e->created_at,
            ]),
            'resolutions' => $dispute->resolutions->map(fn ($r) => [
                'type' => $r->resolution_type,
                'amount' => $r->amount !== null ? (float) $r->amount : null,
                'currency' => $r->currency,
                'explanation' => $r->explanation,
                'status' => $r->status,
                'applied_at' => $r->applied_at,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'category' => ['required', 'in:quality,no_show,payment,damage,safety,communication,other'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'booking_id' => ['nullable', 'integer'],
        ]);

        $case = $this->service->open($request->user(), $data);

        return response()->json([
            'id' => $case->id,
            'reference' => $case->reference,
            'status' => $case->status,
            'sla_policy' => $case->sla_policy,
        ], 201);
    }

    public function message(Request $request, ComplaintCase $dispute): JsonResponse
    {
        abort_unless((int) $dispute->client_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $event = $this->service->addMessage(
            $dispute,
            $request->user(),
            DisputeEvent::ROLE_CLIENT,
            $data['body'],
        );

        return response()->json([
            'event_id' => $event->id,
            'status' => $dispute->fresh()->status,
        ], 201);
    }
}
