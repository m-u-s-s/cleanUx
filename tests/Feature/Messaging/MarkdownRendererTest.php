<?php

namespace Tests\Feature\Messaging;

use App\Services\Messaging\MarkdownRenderer;
use Tests\TestCase;

class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = app(MarkdownRenderer::class);
    }

    public function test_renders_basic_markdown(): void
    {
        $html = $this->renderer->render("# Title\n\n**bold** and *italic*");

        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
    }

    public function test_strips_raw_html_input(): void
    {
        $html = $this->renderer->render('<script>alert("xss")</script>Hello');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function test_renders_lists(): void
    {
        $html = $this->renderer->render("- item 1\n- item 2\n- item 3");

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>item 1</li>', $html);
    }

    public function test_renders_inline_code(): void
    {
        $html = $this->renderer->render('Use `php artisan migrate` first');

        $this->assertStringContainsString('<code>php artisan migrate</code>', $html);
    }

    public function test_renders_code_block(): void
    {
        $html = $this->renderer->render("```\necho 'hello';\n```");

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('<code', $html);
    }

    public function test_strikethrough(): void
    {
        $html = $this->renderer->render('~~deprecated~~');
        $this->assertStringContainsString('<del>deprecated</del>', $html);
    }

    public function test_external_links_get_target_blank(): void
    {
        $html = $this->renderer->render('[Click](https://example.com)');

        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_strips_javascript_protocol(): void
    {
        $html = $this->renderer->render('[bad](javascript:alert("hi"))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_highlights_user_mentions(): void
    {
        $html = $this->renderer->render('Hey @alice and @bob');

        $this->assertStringContainsString('class="mention', $html);
        $this->assertStringContainsString('data-mention="alice"', $html);
        $this->assertStringContainsString('data-mention="bob"', $html);
    }

    public function test_special_mentions_get_amber_style(): void
    {
        $html = $this->renderer->render('@here urgent');

        $this->assertStringContainsString('mention-special', $html);
        $this->assertStringContainsString('text-amber', $html);
    }

    public function test_quoted_mentions_with_spaces(): void
    {
        $html = $this->renderer->render('Salut @"alice martin"');

        $this->assertStringContainsString('data-mention="alice martin"', $html);
    }

    public function test_plain_preview_strips_html_and_limits(): void
    {
        $preview = $this->renderer->plainPreview(
            "**Important** @alice — voici une longue notification qui devrait être tronquée à 30 chars",
            30
        );

        $this->assertStringNotContainsString('<strong>', $preview);
        $this->assertStringNotContainsString('class=', $preview);
        $this->assertLessThanOrEqual(31, strlen($preview)); // 30 + "…"
    }

    public function test_table_rendering(): void
    {
        $md = "| Col 1 | Col 2 |\n| --- | --- |\n| A | B |\n| C | D |";
        $html = $this->renderer->render($md);

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>Col 1</th>', $html);
        $this->assertStringContainsString('<td>A</td>', $html);
    }
}
