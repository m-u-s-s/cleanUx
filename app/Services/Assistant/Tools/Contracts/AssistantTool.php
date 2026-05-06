<?php

namespace App\Services\Assistant\Tools\Contracts;

use App\Models\User;

/**
 * Contrat pour tous les "tools" exposés à l'assistant LLM via function calling.
 *
 * Implémenter ce contrat pour ajouter une nouvelle action que le modèle
 * peut décider d'invoquer (ex: créer un booking, lister les sites, etc.).
 *
 * Format JSON Schema : voir https://docs.anthropic.com/en/docs/build-with-claude/tool-use
 */
interface AssistantTool
{
    /**
     * Identifiant snake_case du tool — utilisé par le LLM dans tool_use.
     * Ex: "create_booking", "list_my_sites".
     */
    public function name(): string;

    /**
     * Description courte (1-2 phrases) destinée au LLM.
     * Doit être assez précise pour que le modèle sache QUAND l'utiliser.
     */
    public function description(): string;

    /**
     * JSON Schema des arguments d'entrée.
     * Format Anthropic :
     *   ['type' => 'object', 'properties' => [...], 'required' => [...]]
     */
    public function inputSchema(): array;

    /**
     * Vérifie que l'utilisateur a le droit d'invoquer ce tool dans son contexte.
     * Retourne true/false. Le moteur d'orchestration refusera si false.
     */
    public function authorize(User $user): bool;

    /**
     * Si true, le tool s'exécute directement.
     * Si false, on crée d'abord un AssistantAction status=pending_confirmation
     * et l'utilisateur doit cliquer "Confirmer" dans l'UI avant exécution.
     *
     * Convention : tout ce qui crée/modifie/annule des données métier doit
     * retourner false. Les "lectures" peuvent retourner true.
     */
    public function executesImmediately(): bool;

    /**
     * Exécute le tool avec les arguments validés.
     * Retourne un payload sérialisable JSON qui sera renvoyé au LLM.
     */
    public function execute(User $user, array $input): array;
}
