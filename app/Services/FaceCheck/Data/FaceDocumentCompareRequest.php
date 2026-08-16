<?php

namespace App\Services\FaceCheck\Data;

use App\Models\User;

final readonly class FaceDocumentCompareRequest
{
    public function __construct(
        public User $user,
        public string $referenceContents,
        public string $documentContents,
        public string $documentMimeType = 'image/jpeg',
        public ?string $externalApplicantId = null,
    ) {}
}
