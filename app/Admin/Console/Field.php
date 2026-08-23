<?php

namespace App\Admin\Console;

use InvalidArgumentException;

/** Un champ de formulaire, et les règles qui le valident. LES RÈGLES NE SONT PAS PUBLIÉES. */
final class Field
{
    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_EMAIL = 'email';

    public const TYPE_PHONE = 'phone';

    public const TYPE_NUMBER = 'number';

    public const TYPE_MONEY = 'money';

    public const TYPE_DATE = 'date';

    public const TYPE_SELECT = 'select';

    public const TYPE_BOOL = 'bool';

    private const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_EMAIL,
        self::TYPE_PHONE,
        self::TYPE_NUMBER,
        self::TYPE_MONEY,
        self::TYPE_DATE,
        self::TYPE_SELECT,
        self::TYPE_BOOL,
    ];

    /** @var list<string> */
    private array $rules = [];

    /** @param list<array{value: string, label: string}> $options */
    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type,
        private readonly array $options = [],
    ) {}

    public static function make(string $key, string $label, string $type = self::TYPE_TEXT): self
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(
                "Type de champ inconnu « {$type} » pour « {$key} ». Types acceptés : ".implode(', ', self::TYPES).'.',
            );
        }

        return new self($key, $label, $type);
    }

    /** @param list<array{value: string, label: string}> $options */
    public static function select(string $key, string $label, array $options): self
    {
        return new self($key, $label, self::TYPE_SELECT, $options);
    }

    /** @param list<string> $rules */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return $this->rules;
    }

    /** @return array{key: string, label: string, type: string, required: bool, options: list<array{value: string, label: string}>} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required' => in_array('required', $this->rules, true),
            'options' => $this->options,
        ];
    }
}
