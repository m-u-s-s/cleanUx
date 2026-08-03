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
     * POURQUOI CECI EXISTE PLUTÔT QUE QUATRE ÉCRANS SUR-MESURE. Tous les refus de la plateforme —
     * litige, KYC, KYB, approbation d'entreprise — demandent un motif écrit, et le moteur ne
     * savait pas demander une valeur avant d'agir. Écrire un écran par file aurait produit quatre
     * fois la même feuille de saisie, avec quatre fois l'occasion d'oublier la validation.
     * L'action DÉCLARE ce dont elle a besoin, et le moteur le demande.
     *
     * Les règles restent côté serveur, comme pour les formulaires : le mobile reçoit le type et
     * le caractère obligatoire, pas de quoi croire qu'il peut valider seul.
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
