<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\AuditRedactionRule;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuditRedactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('audit.enabled', true);
        Config::set('audit.redaction.drop_keys', ['password', 'token', 'secret']);
        Config::set('audit.redaction.hash_keys', ['email', 'phone']);
        Config::set('audit.redaction.max_context_size_bytes', 32768);
    }

    public function test_drop_keys_are_removed_from_context(): void
    {
        $event = app(AuditService::class)->record('test.event', [
            'username' => 'alice',
            'password' => 'secret123',
            'token' => 'xyz',
        ]);

        $this->assertArrayNotHasKey('password', $event->context);
        $this->assertArrayNotHasKey('token', $event->context);
        $this->assertSame('alice', $event->context['username']);

        $this->assertContains('password', $event->context_redacted);
        $this->assertContains('token', $event->context_redacted);
    }

    public function test_hash_keys_are_replaced_with_sha256_prefix(): void
    {
        $event = app(AuditService::class)->record('test.event', [
            'email' => 'user@example.com',
            'phone' => '+32412345678',
        ]);

        $this->assertStringStartsWith('sha256:', $event->context['email']);
        $this->assertStringStartsWith('sha256:', $event->context['phone']);
        $this->assertStringNotContainsString('user@example.com', json_encode($event->context));
    }

    public function test_redaction_walks_nested_arrays(): void
    {
        $event = app(AuditService::class)->record('test.event', [
            'wrapper' => [
                'password' => 'leaked',
                'safe' => 'ok',
            ],
        ]);

        $this->assertArrayNotHasKey('password', $event->context['wrapper']);
        $this->assertSame('ok', $event->context['wrapper']['safe']);
    }

    public function test_clamp_context_size_truncates_oversized_payload(): void
    {
        Config::set('audit.redaction.max_context_size_bytes', 100);

        $event = app(AuditService::class)->record('test.event', [
            'big' => str_repeat('a', 5000),
        ]);

        $this->assertTrue((bool) ($event->context['_truncated'] ?? false));
        $this->assertGreaterThan(100, (int) $event->context['_original_size_bytes']);
    }

    public function test_db_regex_rule_redacts_pattern_matches(): void
    {
        AuditRedactionRule::create([
            'code' => 'masked_card',
            'name' => 'Card PAN',
            'pattern' => '/\b\d{13,19}\b/',
            'match_type' => AuditRedactionRule::MATCH_REGEX,
            'replacement' => '[CARD]',
            'is_active' => true,
            'priority' => 50,
        ]);

        $event = app(AuditService::class)->record('payment.failed', [
            'note' => 'card 4111111111111111 declined',
        ]);

        $this->assertStringContainsString('[CARD]', $event->context['note']);
        $this->assertStringNotContainsString('4111111111111111', $event->context['note']);
    }

    public function test_db_rule_scoped_to_other_domain_does_not_apply(): void
    {
        AuditRedactionRule::create([
            'code' => 'finance_only',
            'name' => 'Finance scope',
            'pattern' => '/secret-finance-value/',
            'match_type' => AuditRedactionRule::MATCH_REGEX,
            'replacement' => '[F]',
            'scope_domain' => 'finance',
            'is_active' => true,
            'priority' => 50,
        ]);

        $event = app(AuditService::class)->record('booking.created', [
            'note' => 'secret-finance-value should not be redacted here',
        ]);

        $this->assertStringContainsString('secret-finance-value', $event->context['note']);
    }
}
