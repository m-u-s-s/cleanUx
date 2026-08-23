<?php

namespace App\Admin\Console;

use Closure;
use InvalidArgumentException;

/** Une action métier exposée sur une ligne. LA CLOSURE NE TRAVERSE JAMAIS LE JSON. */
final class Action
{
    private bool $destructive = false;

    private ?string $confirm = null;

    /** @var list<Field> */
    private array $fields = [];

    private function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly Closure $handler,
    ) {}

    public static function make(string $key, string $label, Closure $handler): self
    {
        return new self($key, $label, $handler);
    }

    public function destructive(string $confirm): self
    {
        if (trim($confirm) === '') {
            throw new InvalidArgumentException(
                "L'action destructive « {$this->key} » doit dire ce qu'elle détruit.",
            );
        }

        $this->destructive = true;
        $this->confirm = $confirm;

        return $this;
    }

    /**
     * Les valeurs que l'action exige avant de s'exécuter.
     *
     * @param  list<Field>  $fields
     */
    public function requires(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /** @return list<Field> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function handler(): Closure
    {
        return $this->handler;
    }

    public function isDestructive(): bool
    {
        return $this->destructive;
    }

    /**
     * @return array{key: string, label: string, destructive: bool, confirm: string|null, fields: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'destructive' => $this->destructive,
            'confirm' => $this->confirm,
            // La closure ne traverse jamais le JSON ; les champs exigés, si — le mobile doit
            // pouvoir dessiner la feuille de saisie sans connaître le domaine.
            'fields' => array_map(fn (Field $field) => $field->toArray(), $this->fields),
        ];
    }
}
