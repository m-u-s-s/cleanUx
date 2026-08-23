<?php

namespace App\Admin\Console;

use InvalidArgumentException;

/** Une colonne de liste, telle que le rendu natif la comprend. */
final class Column
{
    public const TYPE_TEXT = 'text';

    public const TYPE_NUMBER = 'number';

    public const TYPE_MONEY = 'money';

    public const TYPE_DATE = 'date';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_BADGE = 'badge';

    public const TYPE_BOOL = 'bool';

    private const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_NUMBER,
        self::TYPE_MONEY,
        self::TYPE_DATE,
        self::TYPE_DATETIME,
        self::TYPE_BADGE,
        self::TYPE_BOOL,
    ];

    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type,
    ) {}

    public static function make(string $key, string $label, string $type = self::TYPE_TEXT): self
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(
                "Type de colonne inconnu « {$type} » pour « {$key} ». Types acceptés : ".implode(', ', self::TYPES).'.',
            );
        }

        return new self($key, $label, $type);
    }

    public function key(): string
    {
        return $this->key;
    }

    /** @return array{key: string, label: string, type: string} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'type' => $this->type];
    }
}
