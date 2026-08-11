<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\MissionChecklistItem;
use App\Models\MissionEvent;
use App\Models\MissionMedia;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\MissionTrackingService;
use App\Services\Missions\OnSite\MissionMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MissionFieldActionController extends Controller
{
    public function offlineSync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'actions' => ['required', 'array'],
            'actions.*.mission_id' => ['required', 'integer', 'exists:missions,id'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.payload' => ['nullable', 'array'],
            'actions.*.created_at' => ['nullable', 'string'],
        ]);

        foreach ($data['actions'] as $action) {
            $mission = Mission::findOrFail($action['mission_id']);

            $this->authorize('update', $mission);

            MissionEvent::create([
                'mission_id' => $mission->id,
                'user_id' => Auth::id(),
                'event_type' => 'offline_'.$action['type'],
                'title' => 'Action offline synchronisée',
                'description' => 'Action terrain enregistrée hors connexion puis synchronisée.',
                'payload' => $action['payload'] ?? [],
                'happened_at' => $action['created_at'] ?? now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'synced' => count($data['actions']),
        ]);
    }

    public function toggleChecklistItem(
        Request $request,
        MissionChecklistItem $item
    ): JsonResponse {
        $mission = $item->checklist->mission;

        $this->authorize('update', $mission);

        $data = $request->validate([
            'status' => ['required', 'in:pending,done'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $item->notes,
            'completed_by_user_id' => $data['status'] === 'done' ? Auth::id() : null,
            'completed_at' => $data['status'] === 'done' ? now() : null,
        ]);

        $checklist = $item->checklist()->with('items')->first();

        $total = max(1, $checklist->items->count());
        $done = $checklist->items->where('status', 'done')->count();

        $checklist->update([
            'completion_rate' => round(($done / $total) * 100, 2),
            'status' => $done === $total ? 'completed' : 'in_progress',
        ]);

        return response()->json([
            'ok' => true,
            'completion_rate' => $checklist->completion_rate,
            'checklist_status' => $checklist->status,
        ]);
    }

    public function start(Request $request, Mission $mission, MissionLifecycleService $service): JsonResponse
    {
        $this->authorize('update', $mission);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'photos_avant.*' => ['nullable', 'image', 'max:4096'],
        ]);

        // Le code est validé AVANT d'enregistrer quoi que ce soit — voir storePhotos().
        $mission = $service->validateStartCode(
            $mission,
            Auth::user(),
            $data['code'],
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null
        );

        return response()->json([
            'ok' => true,
            'status' => $mission->status,
            'photos_stored' => $this->storePhotos($request, $mission, 'photos_avant', MissionMedia::TYPE_BEFORE_PHOTO, $data),
        ]);
    }

    public function enRoute(Request $request, Mission $mission, MissionLifecycleService $service): JsonResponse
    {
        $this->authorize('update', $mission);

        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
        ]);

        $mission = $service->setEnRoute(
            $mission,
            Auth::user()
        );

        $trackingSession = app(MissionTrackingService::class)
            ->startToClientTracking(
                $mission,
                Auth::user(),
                (float) $data['lat'],
                (float) $data['lng']
            );

        return response()->json([
            'ok' => true,
            'status' => $mission->status,
            'tracking_session_id' => $trackingSession->id,
        ]);
    }

    public function arrived(Request $request, Mission $mission, MissionLifecycleService $service): JsonResponse
    {
        $this->authorize('update', $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $mission = $service->setArrived(
            $mission,
            Auth::user(),
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null
        );

        return response()->json([
            'ok' => true,
            'status' => $mission->status,
        ]);
    }

    public function finish(Request $request, Mission $mission, MissionLifecycleService $service): JsonResponse
    {
        $this->authorize('update', $mission);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'photos_apres.*' => ['nullable', 'image', 'max:4096'],
        ]);

        // Clôturer encaisse : la position est exigée ici comme sur mobile. La vue relève déjà le
        // navigateur avant d'appeler — elle se contentait jusqu'ici de continuer sans, ce qui
        // laissait la preuve facultative alors que c'est elle qui rend le code inutilisable à
        // distance.
        //
        // Les photos sont enregistrées APRÈS — voir storePhotos().
        $mission = $service->validateEndCode(
            $mission,
            Auth::user(),
            $data['code'],
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
            isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
            false,
            requirePosition: true,
        );

        return response()->json([
            'ok' => true,
            'status' => $mission->status,
            'photos_stored' => $this->storePhotos($request, $mission, 'photos_apres', MissionMedia::TYPE_AFTER_PHOTO, $data),
        ]);
    }

    /**
     * Enregistre les photos du terrain, UNE FOIS la transition acquise.
     *
     * L'ordre est le correctif. Les photos étaient stockées avant la validation du code : chaque
     * tentative refusée — mauvais code, et depuis peu position trop lointaine — laissait donc ses
     * fichiers et ses lignes en base sur une mission qui n'avait pas bougé. Trois essais, trois
     * jeux de photos identiques ; et le prestataire qui réessaie est précisément celui dont on
     * vient de refuser la tentative.
     *
     * L'échec d'une photo ne remet PAS la transition en cause : elle est acquise, et pour la
     * clôture le paiement est déjà encaissé. Répondre en erreur à ce stade inviterait à rejouer
     * une clôture qui a réussi — le prestataire se heurterait alors à un code déjà consommé, sans
     * comprendre. Le compte renvoyé dit ce qui a réellement été gardé.
     *
     * Le type vient de {@see MissionMedia} et non d'une chaîne écrite ici. Ce contrôleur posait
     * `before`/`after` quand tout le reste de l'application lit `before_photo`/`after_photo` : les
     * photos prises sur place étaient donc invisibles pour le client, absentes du rapport PDF et
     * comptées à zéro par le score qualité. Chaque moitié était cohérente avec elle-même, ce qui
     * est exactement pourquoi l'écart n'a rien déclenché.
     *
     * @param  MissionMedia::TYPE_*  $mediaType
     * @param  array<string, mixed>  $data
     * @return int Nombre de photos effectivement enregistrées.
     */
    private function storePhotos(
        Request $request,
        Mission $mission,
        string $field,
        string $mediaType,
        array $data,
    ): int {
        if (! $request->hasFile($field)) {
            return 0;
        }

        $isBefore = $mediaType === MissionMedia::TYPE_BEFORE_PHOTO;
        $caption = $isBefore ? 'Photo avant mission' : 'Photo après mission';
        $stored = 0;

        foreach ($request->file($field) as $photo) {
            try {
                /*
                 * Le service, et non une écriture directe : c'est lui qui calcule l'empreinte du
                 * fichier, diffuse au client et inscrit le geste dans l'historique de la mission.
                 * Une photo déposée par ce chemin valait sinon moins qu'une photo déposée depuis le
                 * téléphone — et c'est celle-là qu'on aurait produite en cas de contestation.
                 */
                app(MissionMediaService::class)->capture(
                    $mission,
                    Auth::user(),
                    $photo,
                    $mediaType,
                    isset($data['lat']) ? (float) $data['lat'] : null,
                    isset($data['lng']) ? (float) $data['lng'] : null,
                    caption: $caption,
                );
                $stored++;
            } catch (\Throwable $e) {
                Log::warning('Photo de mission non enregistrée après une transition réussie.', [
                    'mission_id' => $mission->id,
                    'media_type' => $mediaType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stored;
    }
}
