<?php

namespace App\Services\Assistant\Llm;

/**
 * Contrat pour les fournisseurs LLM (Anthropic, OpenAI, ...).
 *
 * L'objectif : pouvoir switcher de provider (failover, cost-optimization,
 * tests A/B) sans changer l'AssistantWidget.
 */
interface LlmProvider
{
    /**
     * Identifiant court : 'anthropic', 'openai', 'mock'.
     */
    public function name(): string;

    /**
     * Envoie une requête avec :
     *   - $systemPrompt   : prompt de base contextualisé par rôle
     *   - $messages       : historique [{role: user|assistant, content: ...}]
     *   - $tools          : array de définitions de tools (format Anthropic)
     *
     * @return LlmResponse
     */
    public function chat(
        string $systemPrompt,
        array $messages,
        array $tools = [],
        array $options = []
    ): LlmResponse;
}
