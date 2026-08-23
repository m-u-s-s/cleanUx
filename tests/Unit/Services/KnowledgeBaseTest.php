<?php

namespace Tests\Unit\Services;

use App\Services\Assistant\KnowledgeBase;
use Tests\TestCase;

/** 7.11 — KnowledgeBase substring search. */
class KnowledgeBaseTest extends TestCase
{
    private KnowledgeBase $kb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kb = new KnowledgeBase;
    }

    public function test_search_finds_annulation_article(): void
    {
        $result = $this->kb->search('annulation');
        $this->assertStringContainsString('Annulation', $result);
        $this->assertStringContainsString('Base de connaissances', $result);
    }

    public function test_search_is_case_insensitive(): void
    {
        $lower = $this->kb->search('paiement');
        $upper = $this->kb->search('PAIEMENT');
        $this->assertSame($lower, $upper);
    }

    public function test_search_returns_empty_string_when_no_match(): void
    {
        $result = $this->kb->search('zorgblorp-unicorn-xyzzy');
        $this->assertSame('', $result);
    }

    public function test_search_returns_at_most_three_articles(): void
    {
        // A very short query that could match many titles/tags
        $result = $this->kb->search('a');
        $lines = array_filter(
            explode("\n", $result),
            fn (string $l) => str_starts_with(trim($l), '- ')
        );
        $this->assertLessThanOrEqual(3, count($lines));
    }

    public function test_fidelite_article_found_by_tag(): void
    {
        $result = $this->kb->search('points');
        $this->assertStringContainsString('Fidelite', $result);
    }

    public function test_litige_article_found_by_content(): void
    {
        $result = $this->kb->search('probleme');
        $this->assertStringContainsString('Litige', $result);
    }

    public function test_zones_article_found(): void
    {
        $result = $this->kb->search('belgique');
        $this->assertStringContainsString('Zones', $result);
    }

    public function test_matching_articles_returns_collection(): void
    {
        $articles = $this->kb->matchingArticles('rgpd');
        $this->assertGreaterThan(0, $articles->count());
    }

    public function test_matching_articles_is_empty_for_no_match(): void
    {
        $articles = $this->kb->matchingArticles('zzzzz-no-match-xyzzy');
        $this->assertTrue($articles->isEmpty());
    }

    public function test_search_result_starts_with_prefix_when_found(): void
    {
        $result = $this->kb->search('paiement');
        $this->assertStringStartsWith('Base de connaissances:', $result);
    }
}
