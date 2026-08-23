<?php

namespace App\Services\Missions\OnSite;

use App\Models\Mission;
use App\Models\MissionChecklistItem;
use App\Models\MissionMedia;
use App\Models\User;
use App\Services\Missions\MissionAssignmentStatusService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/** LE GUIDE PAS-À-PAS DU MÉTIER (F6). */
class MissionGuidedChecklistService
{
    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
        protected MissionMediaService $mediaService,
    ) {}

    /** Cette mission a-t-elle une checklist réellement ordonnée ? */
    public function estGuidee(Mission $mission): bool
    {
        $items = $this->items($mission);

        return $items->isNotEmpty()
            && $items->every(fn (MissionChecklistItem $item) => $item->sort_order !== null);
    }

    /**
     * L'ÉTAPE EN COURS — une seule, jamais la liste.
     *
     * @return array<string, mixed>|null `null` quand tout est fait
     */
    public function etapeCourante(Mission $mission): ?array
    {
        $item = $this->items($mission)
            ->first(fn (MissionChecklistItem $item) => $item->status !== 'done');

        if (! $item) {
            return null;
        }

        $total = $this->items($mission)->count();
        $faites = $this->items($mission)->where('status', 'done')->count();

        return [
            'id' => $item->id,
            'label' => $item->label ?: $item->title,
            // La consigne fait la différence entre « Sols » et « Aspirer puis laver, produit neutre
            // sur parquet ».
            'guidance' => $item->guidance,
            'requires_photo' => (bool) $item->requires_photo,
            'is_required' => (bool) $item->is_required,
            'position' => $faites + 1,
            'total' => $total,
            'photo_taken' => $item->mission_media_id !== null,
        ];
    }

    /**
     * VALIDER L'ÉTAPE EN COURS — et seulement elle.
     *
     * @throws DomainException
     */
    public function validerEtape(
        Mission $mission,
        User $prestataire,
        int $itemId,
        ?UploadedFile $photo = null,
        ?float $lat = null,
        ?float $lng = null,
    ): MissionChecklistItem {
        $this->assignmentStatusService->assertAssignedToMission($mission, $prestataire);

        $courante = $this->etapeCourante($mission);

        if (! $courante) {
            throw new DomainException('Toutes les étapes sont déjà faites.');
        }

        // ON NE VALIDE QUE L'ÉTAPE EN COURS.
        if ($courante['id'] !== $itemId) {
            throw new DomainException('Terminez d’abord l’étape en cours.');
        }

        $item = $this->items($mission)->firstWhere('id', $itemId);

        if (! $item) {
            throw new DomainException('Étape introuvable.');
        }

        $media = null;

        if ($item->requires_photo) {
            if (! $photo) {
                // La preuve est exigée AVANT de cocher, pas rappelée après : une étape validée sans
                // sa photo ne se rattrape plus, le lieu a changé.
                throw new DomainException('Cette étape demande une photo.');
            }

            $media = $this->mediaService->capture(
                $mission,
                $prestataire,
                $photo,
                MissionMedia::TYPE_AFTER_PHOTO,
                $lat,
                $lng,
                caption: (string) ($item->label ?: $item->title),
            );
        }

        $item->forceFill([
            'status' => 'done',
            'completed_by_user_id' => $prestataire->id,
            'completed_at' => now(),
            // La photo de l'étape si on vient d'en prendre une, sinon celle déjà attachée.
            'mission_media_id' => $media !== null ? $media->id : $item->mission_media_id,
        ])->save();

        return $item->fresh();
    }

    /**
     * Les étapes, dans l'ordre du métier.
     *
     * @return Collection<int, MissionChecklistItem>
     */
    protected function items(Mission $mission): Collection
    {
        return MissionChecklistItem::query()
            ->whereIn(
                'mission_checklist_id',
                $mission->checklists()->select('id'),
            )
            ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
