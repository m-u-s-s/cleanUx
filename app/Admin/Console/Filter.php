<?php

namespace App\Admin\Console;

/** Un filtre exposé par un descripteur. */
final class Filter
{
    public const TYPE_SEARCH = 'search';

    public const TYPE_SELECT = 'select';

    public const TYPE_DATE_RANGE = 'date_range';

    public const TYPE_BOOL = 'bool';

    /** @param list<array{value: string, label: string}> $options */
    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type,
        private readonly array $options = [],
    ) {}

    public static function search(string $key, string $label): self
    {
        return new self($key, $label, self::TYPE_SEARCH);
    }

    /** @param list<array{value: string, label: string}> $options */
    public static function select(string $key, string $label, array $options): self
    {
        return new self($key, $label, self::TYPE_SELECT, $options);
    }

    public static function dateRange(string $key, string $label): self
    {
        return new self($key, $label, self::TYPE_DATE_RANGE);
    }

    public static function bool(string $key, string $label): self
    {
        return new self($key, $label, self::TYPE_BOOL);
    }

    public function key(): string
    {
        return $this->key;
    }

    /** @return array{key: string, label: string, type: string, options: list<array{value: string, label: string}>} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->options,
        ];
    }
}
