<?php

namespace App\Admin\Console;

use Closure;
use Throwable;

/** Une tuile chiffrée d'un rapport d'administration. */
final class ReportTile
{
    public const TONE_NEUTRAL = 'neutral';

    public const TONE_SUCCESS = 'success';

    public const TONE_WARNING = 'warning';

    public const TONE_DANGER = 'danger';

    /** @param Closure(): (int|float|string) $mesure */
    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly Closure $mesure,
        private readonly string $format,
        private readonly ?string $hint,
        private readonly ?Closure $tone,
    ) {}

    /**
     * @param  Closure(): (int|float|string)  $mesure
     * @param  Closure(int|float|string): string|null  $tone
     */
    public static function make(
        string $key,
        string $label,
        Closure $mesure,
        string $format = 'number',
        ?string $hint = null,
        ?Closure $tone = null,
    ): self {
        return new self($key, $label, $mesure, $format, $hint, $tone);
    }

    /**
     * Mesure la tuile. Une erreur rend une tuile non mesurable, jamais une exception.
     *
     * @return array{key: string, label: string, value: int|float|string, format: string, hint: string|null, tone: string, available: bool}
     */
    public function toArray(): array
    {
        try {
            $valeur = ($this->mesure)();
            $ton = $this->tone !== null ? ($this->tone)($valeur) : self::TONE_NEUTRAL;

            return [
                'key' => $this->key,
                'label' => $this->label,
                'value' => $valeur,
                'format' => $this->format,
                'hint' => $this->hint,
                'tone' => $ton,
                'available' => true,
            ];
        } catch (Throwable) {
            return [
                'key' => $this->key,
                'label' => $this->label,
                'value' => 0,
                'format' => $this->format,
                'hint' => $this->hint,
                'tone' => self::TONE_NEUTRAL,
                'available' => false,
            ];
        }
    }
}
