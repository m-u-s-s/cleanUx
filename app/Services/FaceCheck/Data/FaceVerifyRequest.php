<?php

namespace App\Services\FaceCheck\Data;

use App\Models\User;

final readonly class FaceVerifyRequest
{
    public function __construct(
        public User $user,
        public string $probeContents,
        public ?string $referenceContents = null,
        public string $mimeType = 'image/jpeg',
        public ?string $externalFaceId = null,
        public ?string $externalApplicantId = null,
    ) {}
}
