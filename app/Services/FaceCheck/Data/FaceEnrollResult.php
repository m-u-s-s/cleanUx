<?php

namespace App\Services\FaceCheck\Data;

final readonly class FaceEnrollResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $externalFaceId = null,
        public ?string $externalApplicantId = null,
        public array $raw = [],
    ) {}
}
