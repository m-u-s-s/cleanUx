<?php

namespace Tests\Feature\EmailV2;

use App\Models\EmailMessage;
use App\Models\EmailTemplate;
use App\Services\EmailV2\Contracts\EmailProviderContract;
use App\Services\EmailV2\EmailService;
use App\Services\EmailV2\Providers\MockEmailProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('email_v2.enabled', true);
        Config::set('email_v2.provider', 'mock');
        Config::set('email_v2.allowed_categories', ['transactional', 'marketing', 'notification', 'system']);
        Config::set('email_v2.from_default', ['email' => 'noreply@test.com', 'name' => 'CleanUx Test']);
        Config::set('email_v2.rate_limit_per_recipient_per_hour', 0);
        Config::set('email_v2.rate_limit_per_recipient_per_day', 0);
        Config::set('email_v2.check_opt_outs', false);

        $this->app->bind(EmailProviderContract::class, MockEmailProvider::class);
    }

    public function test_send_persists_message_and_marks_sent(): void
    {
        $msg = app(EmailService::class)->send([
            'to_email' => 'test@example.com',
            'subject' => 'Hello',
            'body_html' => '<p>Hello world</p>',
        ]);

        $this->assertInstanceOf(EmailMessage::class, $msg);
        $this->assertSame(EmailMessage::STATUS_SENT, $msg->status);
        $this->assertNotNull($msg->provider_message_id);
        $this->assertSame(1, $msg->attempts);
    }

    public function test_send_validates_to_email(): void
    {
        $this->expectException(ValidationException::class);
        app(EmailService::class)->send([
            'to_email' => 'invalid-not-an-email',
            'subject' => 'X',
        ]);
    }

    public function test_send_requires_subject(): void
    {
        $this->expectException(ValidationException::class);
        app(EmailService::class)->send([
            'to_email' => 'test@example.com',
            'subject' => '',
        ]);
    }

    public function test_idempotency_returns_existing_message(): void
    {
        $a = app(EmailService::class)->send([
            'to_email' => 'test@example.com',
            'subject' => 'Idempotent',
            'idempotency_key' => 'unique-key-1',
        ]);
        $b = app(EmailService::class)->send([
            'to_email' => 'test@example.com',
            'subject' => 'Idempotent (re-send attempt)',
            'idempotency_key' => 'unique-key-1',
        ]);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, EmailMessage::query()->count());
    }

    public function test_mock_provider_force_fail_marks_failed(): void
    {
        $msg = app(EmailService::class)->send([
            'to_email' => 'fail@example.com',
            'subject' => 'Test',
        ]);
        $this->assertSame(EmailMessage::STATUS_FAILED, $msg->status);
        $this->assertSame('mock_forced_failure', $msg->last_error);
    }

    public function test_rate_limit_hour_blocks_excess(): void
    {
        Config::set('email_v2.rate_limit_per_recipient_per_hour', 2);

        $svc = app(EmailService::class);
        $svc->send(['to_email' => 'limit@test.com', 'subject' => '1']);
        $svc->send(['to_email' => 'limit@test.com', 'subject' => '2']);
        $third = $svc->send(['to_email' => 'limit@test.com', 'subject' => '3']);

        $this->assertNull($third);
        $this->assertSame(2, EmailMessage::query()->forRecipient('limit@test.com')->count());
    }

    public function test_render_from_template_substitutes_variables(): void
    {
        EmailTemplate::query()->create([
            'code' => 'welcome',
            'name' => 'Welcome',
            'category' => 'transactional',
            'subject_pattern' => 'Bienvenue {{name}} !',
            'body_html_pattern' => '<p>Bonjour {{name}}, votre code est {{code}}.</p>',
            'required_variables' => ['name', 'code'],
            'is_active' => true,
        ]);

        $rendered = app(EmailService::class)->renderFromTemplate('welcome', [
            'name' => 'Alice',
            'code' => 'X42',
        ]);

        $this->assertSame('Bienvenue Alice !', $rendered['subject']);
        $this->assertStringContainsString('<p>Bonjour Alice, votre code est X42.</p>', $rendered['body_html']);
    }

    public function test_render_from_template_ignores_unknown_variables(): void
    {
        EmailTemplate::query()->create([
            'code' => 'restricted',
            'name' => 'R',
            'subject_pattern' => 'Hi {{name}}',
            'body_html_pattern' => '<p>{{name}} - {{evil}}</p>',
            'required_variables' => ['name'],
            'is_active' => true,
        ]);

        $rendered = app(EmailService::class)->renderFromTemplate('restricted', [
            'name' => 'A',
            'evil' => '<script>alert(1)</script>',
        ]);

        // {{evil}} doit rester non substitué car pas dans la whitelist
        $this->assertStringContainsString('{{evil}}', $rendered['body_html']);
        $this->assertStringNotContainsString('<script>', $rendered['body_html']);
    }

    public function test_module_disabled_returns_null(): void
    {
        Config::set('email_v2.enabled', false);
        $result = app(EmailService::class)->send([
            'to_email' => 'test@example.com',
            'subject' => 'X',
        ]);
        $this->assertNull($result);
    }
}
