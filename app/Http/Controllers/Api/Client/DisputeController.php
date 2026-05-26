<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\StoreDisputeRequest;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Services\Disputes\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Disputes
 * @authenticated
 */
class DisputeController extends Controller
{
    public function __construct(protected DisputeService $service)
    {
    }

    /**
     * List the authenticated client's disputes (most recent first, max 50).
     *
     * @response 200 {"data": [{"id": 1, "reference": "DSP-001", "subject": "Prestataire absent", "category": "no_show", "priority": "high", "severity": "medium", "status": "open", "is_overdue": false, "sla_policy": "standard", "created_at": "2026-06-01T10:00:00+00:00", "last_activity_at": "2026-06-02T09:00:00+00:00"}]}
     */
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

    /**
     * Get detailed information for a single dispute including events and resolutions.
     *
     * @response 200 {"id": 1, "reference": "DSP-001", "subject": "Prestataire absent", "description": "Le prestataire n'est pas venu.", "status": "open", "priority": "high", "category": "no_show", "events": [{"id": 1, "type": "comment", "author_role": "client", "author_name": "Alice Dupont", "body": "Aucune nouvelle du prestataire.", "created_at": "2026-06-01T10:05:00+00:00"}], "resolutions": []}
     * @response 403 {"message": "This action is unauthorized."}
     */
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

    /**
     * Open a new dispute case.
     *
     * @bodyParam subject string required Brief subject (3-120 chars). Example: Prestataire absent
     * @bodyParam description string required Full description of the issue (10-2000 chars). Example: Le prestataire n'est pas venu à l'heure prévue et n'a pas répondu.
     * @bodyParam category string required One of: quality, no_show, payment, damage, safety, communication, other. Example: no_show
     * @bodyParam priority string Priority level: low, normal, high, urgent (default normal). Example: high
     * @bodyParam severity string Severity level: low, medium, high, critical (default medium). Example: medium
     * @bodyParam booking_id integer ID of the related booking (optional). Example: 42
     * @response 201 {"id": 1, "reference": "DSP-001", "status": "open", "sla_policy": "standard"}
     * @response 422 {"message": "The category field is required.", "errors": {"category": ["The category field is required."]}}
     */
    public function store(StoreDisputeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $case = $this->service->open($request->user(), $data);

        return response()->json([
            'id' => $case->id,
            'reference' => $case->reference,
            'status' => $case->status,
            'sla_policy' => $case->sla_policy,
        ], 201);
    }

    /**
     * Add a client message to an existing dispute.
     *
     * @bodyParam body string required Message text (1-2000 chars). Example: J'attends toujours une réponse.
     * @response 201 {"event_id": 5, "status": "open"}
     * @response 403 {"message": "This action is unauthorized."}
     */
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
