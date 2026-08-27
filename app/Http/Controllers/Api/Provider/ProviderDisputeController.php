<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Services\Disputes\DisputeService;
use App\Support\Disputes\PreuvesDeLitige;
use App\Support\Media\PrivateMedia;
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

        $charge = $dispute->toArray();
        $charge['attachments'] = $this->avecLiens($dispute->attachments);

        /** @var list<array<string, mixed>> $evenements */
        $evenements = $charge['events'] ?? [];

        $charge['events'] = array_map(function (array $evenement): array {
            $evenement['attachments'] = $this->avecLiens($evenement['attachments'] ?? []);

            return $evenement;
        }, $evenements);

        return response()->json(['data' => $charge]);
    }

    /**
     * Chaque piece gagne le lien qu'un APPAREIL peut ouvrir.
     *
     * Le lien web exige une session en plus de la signature : mesure faite, une balise `Image`
     * d'un telephone recoit `302 -> /login`. On sert donc le lien qui porte sa preuve, et on ne
     * le sert qu'ici — dans une reponse deja authentifiee, a qui a le droit de voir ce dossier.
     *
     * @param  mixed  $pieces
     * @return list<array<string, mixed>>
     */
    private function avecLiens($pieces): array
    {
        return collect(is_array($pieces) ? $pieces : [])
            ->filter(fn ($piece) => is_array($piece) && ! empty($piece['path']))
            ->map(fn (array $piece) => $piece + ['url' => PrivateMedia::urlPourAppareil($piece['path'])])
            ->values()
            ->all();
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
