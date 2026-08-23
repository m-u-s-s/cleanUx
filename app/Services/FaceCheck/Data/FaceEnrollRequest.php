<?php

namespace App\Services\FaceCheck\Data;

use App\Models\User;

/** Enrôler un visage de référence. */
final readonly class FaceEnrollRequest
{
    public function __construct(
        public User $user,
        public string $imageContents,
        public string $mimeType = 'image/jpeg',
        public ?string $externalApplicantId = null,
    ) {}
}
