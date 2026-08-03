<?php

namespace App\Admin\Console;

use Closure;
use InvalidArgumentException;

/**
 * Une action métier exposée sur une ligne.
 *
 * LA CLOSURE NE TRAVERSE JAMAIS LE JSON. Le mobile reçoit une clé et un libellé ; l'exécution
 * reste ici, où vivent les services qui portent la règle. C'est ce qui garde le moteur honnête :
 * un descripteur DÉLÈGUE, il ne réimplémente pas.
 *
 * UNE ACTION DESTRUCTIVE EXIGE UN TEXTE DE CONFIRMATION. Une boîte de dialogue muette se valide
 * sans qu'on sache ce qu'on détruit — autant ne pas en afficher.
 */
final class Action
{
    private bool $destructive = false;

    private ?string $confirm = null;

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

    /** @return array{key: string, label: string, destructive: bool, confirm: string|null} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'destructive' => $this->destructive,
            'confirm' => $this->confirm,
        ];
    }
}
