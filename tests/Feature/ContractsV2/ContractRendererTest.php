<?php

namespace Tests\Feature\ContractsV2;

use App\Models\ContractTemplate;
use App\Models\User;
use App\Services\ContractsV2\ContractRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ContractRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('contracts_v2.allowed_variables', [
            'name', 'email', 'date', 'version', 'company', 'app_name',
        ]);
        Config::set('contracts_v2.placeholder_pattern', '/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/');
    }

    protected function makeTemplate(string $body, ?array $localeOverrides = null): ContractTemplate
    {
        return ContractTemplate::create([
            'code' => 'test_' . uniqid(),
            'name' => 'Test',
            'type' => ContractTemplate::TYPE_TOS,
            'role' => ContractTemplate::ROLE_CLIENT,
            'version' => '2026-05-v1',
            'body_markdown' => $body,
            'body_locale_overrides' => $localeOverrides,
            'is_active' => true,
        ]);
    }

    public function test_substitutes_allowed_placeholders(): void
    {
        $user = User::factory()->client()->create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $template = $this->makeTemplate('Hello **{{name}}** ({{email}})');

        $html = app(ContractRenderer::class)->renderBody($template, $user, []);

        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('alice@test.com', $html);
        $this->assertStringContainsString('<strong>', $html);  // bold rendering
    }

    public function test_leaves_unknown_placeholders_intact(): void
    {
        $user = User::factory()->client()->create();
        $template = $this->makeTemplate('Hello {{name}}, your secret is {{secret_admin_password}}.');

        $html = app(ContractRenderer::class)->renderBody($template, $user, []);

        $this->assertStringContainsString('{{secret_admin_password}}', $html);
    }

    public function test_drops_extra_variables_not_in_whitelist(): void
    {
        $user = User::factory()->client()->create(['name' => 'Bob']);
        $template = $this->makeTemplate('Hello {{name}} of {{company}}.');

        $html = app(ContractRenderer::class)->renderBody($template, $user, [
            'company' => 'Acme Corp',
            'arbitrary_drop' => 'should not appear',
        ]);

        $this->assertStringContainsString('Acme Corp', $html);
        $this->assertStringNotContainsString('should not appear', $html);
    }

    public function test_locale_override(): void
    {
        $user = User::factory()->client()->create(['name' => 'Charlie']);
        $template = $this->makeTemplate('English version: {{name}}', [
            'fr' => 'Version française : {{name}}',
        ]);

        $en = app(ContractRenderer::class)->renderBody($template, $user, [], 'en');
        $fr = app(ContractRenderer::class)->renderBody($template, $user, [], 'fr');

        $this->assertStringContainsString('English version', $en);
        $this->assertStringContainsString('Version française', $fr);
    }

    public function test_markdown_titles_and_lists(): void
    {
        $user = User::factory()->client()->create();
        $template = $this->makeTemplate("# Title 1\n## Sub\n- item 1\n- item 2\nParagraph text");

        $html = app(ContractRenderer::class)->renderBody($template, $user, []);

        $this->assertStringContainsString('<h1>Title 1</h1>', $html);
        $this->assertStringContainsString('<h2>Sub</h2>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>item 1</li>', $html);
    }

    public function test_signable_hash_deterministic(): void
    {
        $renderer = app(ContractRenderer::class);
        $a = $renderer->buildSignableHash('body html', 'Alice', '2026-05-19T10:00:00+00:00');
        $b = $renderer->buildSignableHash('body html', 'Alice', '2026-05-19T10:00:00+00:00');
        $c = $renderer->buildSignableHash('body html', 'Bob', '2026-05-19T10:00:00+00:00');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertSame(64, strlen($a));  // sha256 hex
    }
}
