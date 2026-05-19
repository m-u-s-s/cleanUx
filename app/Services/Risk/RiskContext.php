<?php

namespace App\Services\Risk;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * DTO passé à chaque RiskRuleInterface::score().
 *
 * Immutable container avec toutes les infos utiles pour scorer un événement.
 * Volontairement permissif : chaque règle pioche les champs qui l'intéressent.
 */
class RiskContext
{
    public function __construct(
        public readonly string $contextType,    // booking_create | payment_attempt | login | signup
        public readonly ?User $user = null,
        public readonly ?Model $subject = null,
        public readonly ?Request $request = null,
        public readonly array $extra = [],       // payload libre (amount, currency, card_fingerprint, etc.)
    ) {}

    public function ipAddress(): ?string
    {
        return $this->request?->ip();
    }

    public function userAgent(): ?string
    {
        return $this->request?->userAgent();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->extra[$key] ?? $default;
    }
}
