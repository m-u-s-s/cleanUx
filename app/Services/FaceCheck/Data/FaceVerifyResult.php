<?php

namespace App\Services\FaceCheck\Data;

/** Le verdict d'un contrôle : réussi, raté, ou pas encore rendu. */
final readonly class FaceVerifyResult
{
    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public const LIVENESS_PASS = 'pass';

    public const LIVENESS_FAIL = 'fail';

    public const LIVENESS_UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $outcome,
        public ?float $score = null,
        public string $liveness = self::LIVENESS_UNKNOWN,
        public ?string $externalCheckId = null,
        public ?string $failureReason = null,
        public array $raw = [],
    ) {}

    public function isPending(): bool
    {
        return $this->outcome === self::PENDING;
    }
}
