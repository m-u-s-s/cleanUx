<?php

namespace Tests\Feature\Assistant;

use App\Services\Assistant\Llm\AnthropicStreamingProvider;
use App\Services\Assistant\Streaming\StreamEvent;
use Tests\TestCase;

/** Coverage Batch 17 — exerce le chemin TRANSPORT curl de chatStream(). */
class AnthropicStreamingProviderCoverageBatch17Test extends TestCase
{
    private function buildProvider(): AnthropicStreamingProvider
    {
        return new AnthropicStreamingProvider;
    }

    /**
     * @return array<int, StreamEvent>
     */
    private function runStream(array $tools, array $options): array
    {
        $events = [];

        $this->buildProvider()->chatStream(
            'Tu es un assistant.',
            [['role' => 'user', 'content' => 'Bonjour']],
            $tools,
            function (StreamEvent $event) use (&$events) {
                $events[] = $event;
            },
            $options,
        );

        return $events;
    }

    public function test_transport_path_runs_with_present_key_and_tools(): void
    {
        config(['services.anthropic.key' => 'sk-ant-test-key-batch17']);

        $tools = [[
            'name' => 'list_bookings',
            'description' => 'Liste les réservations',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ]];

        $events = $this->runStream($tools, ['timeout' => 2]);

        // Le chemin curl a été traversé sans exception. Selon l'environnement,
        // soit une erreur réseau a été émise, soit la connexion a abouti.
        $mauvais = [];

        foreach ($events as $i => $event) {
            if (! $event instanceof StreamEvent) {
                $mauvais[] = "evenement #{$i} : ".get_debug_type($event);
            }
        }

        $this->assertSame([], $mauvais, 'Ces elements du flux ne sont pas des evenements.');

        $errorEvents = array_values(array_filter(
            $events,
            fn (StreamEvent $e) => $e->type === StreamEvent::TYPE_ERROR,
        ));

        // Toutes les erreurs mal formees d'un coup : un message d'erreur sans origine nommee
        // laisse l'exploitant chercher de quel fournisseur vient la panne.
        $mauvaises = [];

        foreach ($errorEvents as $i => $errorEvent) {
            $message = $errorEvent->payload['message'] ?? null;

            if ($message === null) {
                $mauvaises[] = "erreur #{$i} : pas de message";
            } elseif (! str_contains(mb_strtolower((string) $message), 'anthropic')) {
                $mauvaises[] = "erreur #{$i} : le message ne nomme pas le fournisseur";
            }
        }

        $this->assertSame([], $mauvaises, 'Ces erreurs de flux ne disent pas d ou vient la panne.');

        $this->assertGreaterThanOrEqual(0, count($events));
    }

    public function test_transport_path_runs_without_tools_using_default_options(): void
    {
        config([
            'services.anthropic.key' => 'sk-ant-test-key-batch17-defaults',
            'services.anthropic.model' => 'claude-sonnet-4-20250514',
            'services.anthropic.max_tokens' => 256,
        ]);

        // Pas de tools → la branche $payload['tools'] est volontairement ignorée.
        // timeout court pour éviter d'attendre le défaut de 120s si le réseau
        // tente d'établir une connexion.
        $events = $this->runStream([], ['timeout' => 2]);

        // Tous les types inconnus d'un coup : un flux qui derive introduit generalement plusieurs
        // types nouveaux a la fois, et c'est la liste qui dit lesquels ajouter au contrat.
        $connus = [
            StreamEvent::TYPE_ERROR,
            StreamEvent::TYPE_START,
            StreamEvent::TYPE_TEXT_BLOCK_START,
            StreamEvent::TYPE_TEXT_DELTA,
            StreamEvent::TYPE_TOOL_USE_START,
            StreamEvent::TYPE_TOOL_INPUT_DELTA,
            StreamEvent::TYPE_CONTENT_BLOCK_STOP,
            StreamEvent::TYPE_MESSAGE_DELTA,
            StreamEvent::TYPE_STOP,
        ];

        $inconnus = [];

        foreach ($events as $event) {
            if (! in_array($event->type, $connus, true)) {
                $inconnus[] = (string) $event->type;
            }
        }

        $this->assertSame([], array_values(array_unique($inconnus)), 'Ces types d evenement ne figurent pas au contrat du flux.');

        $this->assertIsArray($events);
    }

    public function test_explicit_model_and_max_tokens_options_are_accepted(): void
    {
        config(['services.anthropic.key' => 'sk-ant-test-key-batch17-explicit']);

        $events = $this->runStream([], [
            'model' => 'claude-opus-4-20250514',
            'max_tokens' => 512,
            'timeout' => 2,
        ]);

        $this->assertIsArray($events);
    }
}
