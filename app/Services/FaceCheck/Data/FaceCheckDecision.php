<?php

namespace App\Services\FaceCheck\Data;

/** La réponse à « ce prestataire peut-il travailler maintenant ? ». */
final readonly class FaceCheckDecision
{
    public const OK = 'ok';

    public const ENROLMENT_REQUIRED = 'face_enrolment_required';

    public const CHECK_REQUIRED = 'face_check_required';

    public const CHECK_PENDING = 'face_check_pending';

    public const BLOCKED = 'face_check_blocked';

    public function __construct(
        public string $code,
        public ?string $message = null,
        public ?int $checkId = null,
        public ?string $trigger = null,
    ) {}

    public static function ok(): self
    {
        return new self(self::OK);
    }

    public function allowed(): bool
    {
        return $this->code === self::OK;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter([
            'ok' => false,
            'error_code' => $this->code,
            'message' => $this->message,
            'face_check_id' => $this->checkId,
        ], static fn ($valeur) => $valeur !== null);
    }
}
