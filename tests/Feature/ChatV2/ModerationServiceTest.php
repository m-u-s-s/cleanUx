<?php

namespace Tests\Feature\ChatV2;

use App\Services\ChatV2\ModerationService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ModerationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('chat_v2.moderation.pii_redaction_enabled', true);
        Config::set('chat_v2.moderation.toxic_block_enabled', true);
        // Ordre : motifs spécifiques avant phone (qui mangerait l'IBAN sinon)
        Config::set('chat_v2.moderation.pii_patterns', [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'iban' => '/\b[A-Z]{2}[0-9]{2}\s?(?:[A-Z0-9]{4}\s?){3,7}[A-Z0-9]{1,4}\b/',
            'phone' => '/\b(?:\+?[0-9]{1,3}[\s.-]?)?\(?[0-9]{2,4}\)?[\s.-]?[0-9]{2,4}[\s.-]?[0-9]{2,4}(?:[\s.-]?[0-9]{2,4})?\b/',
        ]);
        Config::set('chat_v2.moderation.toxic_words', ['idiot', 'connard']);
    }

    public function test_clean_message_passes_unchanged(): void
    {
        $r = app(ModerationService::class)->scan('Bonjour, je passe à 14h pour le nettoyage');
        $this->assertTrue($r->isClean());
        $this->assertNull($r->reason);
        $this->assertSame('Bonjour, je passe à 14h pour le nettoyage', $r->redactedBody);
    }

    public function test_email_pii_is_redacted_and_flagged(): void
    {
        $r = app(ModerationService::class)->scan('Contactez-moi à jean@example.com pour confirmer');
        $this->assertTrue($r->isFlagged());
        $this->assertStringContainsString('[REDACTED:email]', $r->redactedBody);
        $this->assertStringContainsString('email', (string) $r->reason);
        $this->assertNotNull($r->originalHash);
    }

    public function test_iban_pii_is_redacted(): void
    {
        $r = app(ModerationService::class)->scan('Virement vers BE68 5390 0754 7034 SVP');
        $this->assertTrue($r->isFlagged());
        $this->assertStringContainsString('[REDACTED:iban]', $r->redactedBody);
    }

    public function test_toxic_word_blocks_message(): void
    {
        $r = app(ModerationService::class)->scan('Tu es un idiot fini');
        $this->assertTrue($r->isBlocked());
        $this->assertStringStartsWith('toxic_word:', (string) $r->reason);
    }

    public function test_toxic_takes_precedence_over_pii(): void
    {
        $r = app(ModerationService::class)->scan('idiot, mon email est test@test.com');
        $this->assertTrue($r->isBlocked());
        // body NOT redacted when blocked (preserved for audit/admin review)
        $this->assertStringContainsString('test@test.com', $r->redactedBody);
    }

    public function test_pii_redaction_disabled_returns_clean(): void
    {
        Config::set('chat_v2.moderation.pii_redaction_enabled', false);
        $r = app(ModerationService::class)->scan('Email me at hello@example.com');
        $this->assertTrue($r->isClean());
        $this->assertStringContainsString('hello@example.com', $r->redactedBody);
    }
}
