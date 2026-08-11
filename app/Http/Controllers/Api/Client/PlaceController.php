<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientPlace;
use App\Services\Client\ClientPlaceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LE CARNET DE LIEUX, SUR LE TÉLÉPHONE (E2).
 *
 * C'est SUR PLACE qu'on note ce qu'il faut savoir d'un lieu : le digicode qu'on vient de composer,
 * l'étage, le fait que la clé est chez la voisine du deuxième. Renvoyer cette saisie à un ordinateur
 * revient à ne jamais la faire — et à redonner l'information oralement au prestataire suivant.
 *
 * LE SCOPING FAIT PARTIE DE LA REQUÊTE. Un lieu porte l'adresse, l'étage et le code d'alarme du
 * domicile de quelqu'un : un identifiant forgé ne doit jamais en charger un autre, et la différence
 * entre un 403 et un 404 dirait déjà qu'il existe.
 */
class PlaceController extends Controller
{
    public function index(): JsonResponse
    {
        $lieux = app(ClientPlaceService::class)->pour(Auth::user());

        return response()->json([
            'data' => $lieux->map(fn (ClientPlace $lieu) => $this->presenter($lieu))->all(),
            'meta' => ['maximum' => ClientPlaceService::MAXIMUM_LIEUX],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);

        try {
            $lieu = app(ClientPlaceService::class)->enregistrer(Auth::user(), $this->attributs($donnees));
        } catch (DomainException $e) {
            // « Votre carnet contient déjà 25 lieux » est une réponse, pas une panne.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->presenter($lieu)], 201);
    }

    public function update(Request $request, int $placeId): JsonResponse
    {
        $donnees = $this->valider($request);
        $lieu = $this->lieuDuClient($placeId);

        $lieu = app(ClientPlaceService::class)->modifier($lieu, $this->attributs($donnees));

        return response()->json(['data' => $this->presenter($lieu)]);
    }

    public function setDefault(int $placeId): JsonResponse
    {
        $lieu = app(ClientPlaceService::class)->definirParDefaut($this->lieuDuClient($placeId));

        return response()->json(['data' => $this->presenter($lieu)]);
    }

    /** Archiver, jamais supprimer : les interventions passées portent ce lieu. */
    public function destroy(int $placeId): JsonResponse
    {
        $lieu = app(ClientPlaceService::class)->archiver($this->lieuDuClient($placeId));

        return response()->json(['data' => ['id' => $lieu->id, 'archived' => true]]);
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'floor' => ['nullable', 'string', 'max:60'],
            'access_instructions' => ['nullable', 'string', 'max:1000'],
            'alarm_code_required' => ['nullable', 'boolean'],
            'preferences' => ['nullable', 'array'],
            'preferences.products' => ['nullable', 'string', 'max:255'],
            'preferences.allergies' => ['nullable', 'string', 'max:255'],
            'preferences.pets' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function attributs(array $donnees): array
    {
        // `service_zone_id` n'est PAS accepté du client : c'est le service qui le résout depuis le
        // code postal. Le laisser venir du téléphone permettrait de s'attribuer la grille tarifaire
        // d'une autre zone.
        unset($donnees['service_zone_id']);

        return $donnees;
    }

    private function lieuDuClient(int $placeId): ClientPlace
    {
        $lieu = app(ClientPlaceService::class)->lieuDuClient(Auth::user(), $placeId);

        abort_if($lieu === null, 404);

        return $lieu;
    }

    /** @return array<string, mixed> */
    private function presenter(ClientPlace $lieu): array
    {
        return [
            'id' => $lieu->id,
            'label' => $lieu->label,
            'address' => $lieu->address,
            'city' => $lieu->city,
            'postal_code' => $lieu->postal_code,
            'floor' => $lieu->floor,
            'access_instructions' => $lieu->access_instructions,
            'alarm_code_required' => $lieu->alarm_code_required,
            'preferences' => $lieu->preferencesLisibles(),
            'is_default' => $lieu->is_default,
        ];
    }
}
