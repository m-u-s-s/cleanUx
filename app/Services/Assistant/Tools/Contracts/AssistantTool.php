<?php

namespace App\Services\Assistant\Tools\Contracts;

use App\Models\User;

/** Contrat pour tous les "tools" exposés à l'assistant LLM via function calling. */
interface AssistantTool
{
    /** Identifiant snake_case du tool — utilisé par le LLM dans tool_use. */
    public function name(): string;

    /** Description courte (1-2 phrases) destinée au LLM. */
    public function description(): string;

    /** JSON Schema des arguments d'entrée. */
    public function inputSchema(): array;

    /** Vérifie que l'utilisateur a le droit d'invoquer ce tool dans son contexte. */
    public function authorize(User $user): bool;

    /** Si true, le tool s'exécute directement. */
    public function executesImmediately(): bool;

    /** Exécute le tool avec les arguments validés. */
    public function execute(User $user, array $input): array;
}
