<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Services\Disputes\DisputeService;
use App\Support\Disputes\PreuvesDeLitige;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Provider — Disputes
 *
 * @authenticated
 */
class ProviderDisputeController extends Controller
{
    public function __construct(protected DisputeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $items = ComplaintCase::query()
            ->where('provider_user_id', $request->user()->id)
            ->latest('last_activity_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $items]);
    }

    /**
     * Le dossier et son fil, tels que le PRESTATAIRE a le droit de les voir.
     *
     * Sans lui, l'application ne pouvait que lister : repondre demandait d'ecrire a l'aveugle.
     * `visibleTo(ROLE_PROVIDER)` filtre A LA REQUETE — une note interne du support ne remonte
     * donc jamais, ni son texte ni ses pieces jointes.
     */
    public function show(Request $request, ComplaintCase $dispute): JsonResponse
    {
        abort_unless((int) $dispute->provider_user_id === (int) $request->user()->id, 403);

        $dispute->load([
            'events' => fn ($q) => $q->visibleTo(DisputeEvent::ROLE_PROVIDER)->orderBy('created_at'),
            'events.author:id,name',
        ]);

        return response()->json(['data' => $dispute]);
    }

    public function respond(Request $request, ComplaintCase $dispute): JsonResponse
    {
        abort_unless((int) $dispute->provider_user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ] + PreuvesDeLitige::regles('attachments'));

        $event = $this->service->addMessage(
            $dispute,
            $request->user(),
            DisputeEvent::ROLE_PROVIDER,
            $data['body'],
            DisputeEvent::VISIBILITY_ALL,
            PreuvesDeLitige::stocker($request->file('attachments') ?? []),
        );

        return response()->json(['event_id' => $event->id], 201);
    }
}
