<?php

namespace App\Services\Missions\OnSite;

use App\Events\Missions\MissionMediaAdded;
use App\Models\Mission;
use App\Models\MissionMedia;
use App\Models\User;
use App\Services\Missions\MissionAssignmentStatusService;
use App\Services\Missions\MissionHistoryService;
use App\Support\Media\PrivateMedia;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * L'ÉTAT DES LIEUX : ce que le prestataire a vu, quand, et où.
 *
 * Le dépôt savait déjà stocker une photo de mission — depuis le web, et seulement là. Ce service
 * en fait une pièce opposable et l'ouvre aux deux autres surfaces :
 *
 * 1. L'EMPREINTE. `sha256` est calculée sur l'octet reçu, avant écriture. Deux clichés identiques
 *    partagent leur empreinte : reprendre la photo d'une mission précédente se voit. C'est la
 *    seule chose qui distingue une preuve d'une illustration.
 * 2. LA POSITION. Elle vient du téléphone, pas du fichier : les métadonnées EXIF sont absentes des
 *    captures d'écran, retirées par la plupart des applications, et de toute façon éditables.
 *    Écrite ici, elle est celle que l'appareil déclarait à l'instant de l'appel.
 * 3. L'HEURE DÉCLARÉE ET L'HEURE REÇUE. `taken_at` est ce qu'annonce l'appareil, `created_at` ce
 *    que le serveur constate. Les garder toutes deux permet de voir un écart au lieu de choisir
 *    d'avance à qui faire confiance.
 *
 * Le disque est `private`. Une photo de l'intérieur d'un logement sur une adresse publique serait
 * lisible par quiconque devine le chemin — et ces chemins sont devinables.
 */
class MissionMediaService
{
    /**
     * Une photo, pas un film : la vidéo se filme volontiers en quatre minutes et remplit le disque
     * comme la connexion d'un chantier. Le plafond est celui d'une photo de téléphone moderne.
     */
    public const MAX_SIZE_BYTES = 12 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
    ) {}

    /**
     * Enregistre un cliché terrain et prévient qui écoute la mission.
     *
     * @param  MissionMedia::TYPE_*  $type
     *
     * @throws DomainException si le fichier n'est pas une photo recevable
     * @throws \RuntimeException si l'auteur n'est pas affecté à la mission
     */
    public function capture(
        Mission $mission,
        User $author,
        UploadedFile $file,
        string $type,
        ?float $lat = null,
        ?float $lng = null,
        ?float $accuracyM = null,
        ?Carbon $takenAt = null,
        ?string $caption = null,
        bool $clientVisible = true,
    ): MissionMedia {
        // Seul un affecté documente une mission. Sans ce contrôle, l'identifiant de mission suffit
        // à déposer une photo dans le dossier de quelqu'un d'autre — et ce dossier sert de preuve.
        $this->assignmentStatusService->assertAssignedToMission($mission, $author);

        if (! in_array($type, MissionMedia::typesTerrain(), true)) {
            throw new DomainException('Type de média terrain inconnu : '.$type);
        }

        $this->valider($file);

        $empreinte = hash_file('sha256', $file->getRealPath());

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $chemin = $file->storeAs(
            'missions/'.$mission->id.'/terrain',
            Str::ulid().'.'.$extension,
            'private',
        );

        if ($chemin === false) {
            throw new DomainException("La photo n'a pas pu être enregistrée.");
        }

        $media = MissionMedia::query()->create([
            'mission_id' => $mission->id,
            'uploaded_by_user_id' => $author->id,
            'media_type' => $type,
            'path' => $chemin,
            'sha256' => $empreinte === false ? null : $empreinte,
            'caption' => $caption,
            'taken_at' => $takenAt ?? now(),
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $accuracyM,
            'client_visible' => $clientVisible,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        event(new MissionMediaAdded($mission, $media));

        app(MissionHistoryService::class)->log(
            $mission,
            $author,
            'mission_media_'.$type,
            'État des lieux',
            $this->libelleType($type).' enregistrée sur place.',
        );

        return $media;
    }

    /**
     * Les clichés d'une mission, du plus ancien au plus récent.
     *
     * L'ordre est chronologique et non « avant puis après » : c'est celui du déroulé, et c'est lui
     * qu'on relit quand on cherche à quel moment quelque chose a changé.
     *
     * @return Collection<int, MissionMedia>
     */
    public function pourLaMission(Mission $mission, bool $clientSeulement = false): Collection
    {
        return MissionMedia::query()
            ->where('mission_id', $mission->id)
            ->whereIn('media_type', MissionMedia::typesTerrain())
            ->when($clientSeulement, fn ($q) => $q->where('client_visible', true))
            ->orderBy('taken_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Forme d'un cliché telle que les deux applications la lisent.
     *
     * L'adresse est signée et courte : trente minutes suffisent à regarder une mission, et une
     * adresse recopiée hors de l'application cesse de fonctionner avant d'avoir servi.
     *
     * @return array<string, mixed>
     */
    public function presenter(MissionMedia $media): array
    {
        return [
            'id' => $media->id,
            'type' => $media->media_type,
            'label' => $this->libelleType((string) $media->media_type),
            'caption' => $media->caption,
            'url' => PrivateMedia::url($media->path),
            'taken_at' => $media->taken_at?->toIso8601String(),
            'received_at' => $media->created_at?->toIso8601String(),
            'lat' => $media->lat !== null ? (float) $media->lat : null,
            'lng' => $media->lng !== null ? (float) $media->lng : null,
            'accuracy_m' => $media->accuracy_m,
            // Les douze premiers caractères suffisent à comparer deux clichés à l'œil sans étaler
            // une empreinte de soixante-quatre signes dans une liste sur téléphone.
            'fingerprint' => $media->sha256 !== null ? substr((string) $media->sha256, 0, 12) : null,
        ];
    }

    public function libelleType(string $type): string
    {
        return match ($type) {
            MissionMedia::TYPE_BEFORE_PHOTO => 'Photo avant',
            MissionMedia::TYPE_AFTER_PHOTO => 'Photo après',
            MissionMedia::TYPE_INCIDENT_PHOTO => 'Photo d’imprévu',
            default => 'Photo',
        };
    }

    private function valider(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new DomainException("La photo n'a pas pu être reçue.");
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new DomainException('Photo trop lourde (max '.(int) (self::MAX_SIZE_BYTES / 1024 / 1024).' Mo).');
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new DomainException('Format de photo non accepté : '.$mime);
        }
    }
}
