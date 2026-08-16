<?php

namespace App\Services\FaceCheck\Data;

use App\Models\User;

/**
 * Enrôler un visage de référence.
 *
 * Le contenu binaire circule en mémoire et n'est JAMAIS écrit en clair sur le disque : c'est
 * `FaceImageStore` qui le chiffre avant de le poser. Un selfie pèse quelques centaines de
 * kilo-octets — le garder en mémoire le temps d'un appel est sans conséquence, et bien plus sûr
 * qu'un fichier temporaire que personne ne pense à effacer.
 */
final readonly class FaceEnrollRequest
{
    public function __construct(
        public User $user,
        public string $imageContents,
        public string $mimeType = 'image/jpeg',
        public ?string $externalApplicantId = null,
    ) {}
}
