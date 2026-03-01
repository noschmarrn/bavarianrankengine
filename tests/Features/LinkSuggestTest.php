<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\LinkSuggest;

class LinkSuggestTest extends TestCase {

	// -------------------------------------------------------------------------
	// tokenize tests
	// -------------------------------------------------------------------------

	public function test_tokenize_strips_html_and_lowercases(): void {
		$result = LinkSuggest::tokenize( '<p>Hello <strong>World</strong></p>' );
		$this->assertContains( 'hello', $result );
		$this->assertContains( 'world', $result );
		// Must not contain any HTML tags.
		foreach ( $result as $token ) {
			$this->assertStringNotContainsString( '<', $token );
		}
	}

	public function test_tokenize_removes_stop_words_en(): void {
		$result = LinkSuggest::tokenize( 'The quick brown fox jumps over the lazy dog', 'en' );
		$this->assertNotContains( 'the', $result );
		$this->assertNotContains( 'over', $result );
		// Content words should remain.
		$this->assertContains( 'quick', $result );
		$this->assertContains( 'brown', $result );
		$this->assertContains( 'fox', $result );
		$this->assertContains( 'jumps', $result );
		$this->assertContains( 'lazy', $result );
		$this->assertContains( 'dog', $result );
	}

	public function test_tokenize_removes_stop_words_de(): void {
		$result = LinkSuggest::tokenize( 'Die schnelle Katze springt über den Zaun', 'de' );
		$this->assertNotContains( 'die', $result );
		$this->assertNotContains( 'den', $result );
		$this->assertNotContains( 'über', $result );
		// Content words should remain.
		$this->assertContains( 'schnelle', $result );
		$this->assertContains( 'katze', $result );
		$this->assertContains( 'springt', $result );
		$this->assertContains( 'zaun', $result );
	}

	public function test_tokenize_returns_empty_for_blank_input(): void {
		$this->assertSame( [], LinkSuggest::tokenize( '' ) );
		$this->assertSame( [], LinkSuggest::tokenize( '   ' ) );
		$this->assertSame( [], LinkSuggest::tokenize( '<p></p>' ) );
	}

	// -------------------------------------------------------------------------
	// score_candidate tests
	// -------------------------------------------------------------------------

	public function test_score_returns_zero_when_no_overlap(): void {
		$content   = [ 'php', 'wordpress', 'plugin' ];
		$candidate = [
			'title_tokens' => [ 'javascript', 'react', 'frontend' ],
			'tag_tokens'   => [ 'js', 'nodejs' ],
			'cat_tokens'   => [ 'web', 'development' ],
		];
		$this->assertSame( 0.0, LinkSuggest::score_candidate( $content, $candidate ) );
	}

	public function test_score_title_overlap_weights_higher_than_tags(): void {
		// One title match should score higher than one tag match alone.
		$content = [ 'wordpress', 'plugin', 'seo' ];

		$candidate_title = [
			'title_tokens' => [ 'wordpress', 'guide' ],
			'tag_tokens'   => [ 'javascript', 'react' ],
			'cat_tokens'   => [],
		];
		$candidate_tag = [
			'title_tokens' => [ 'javascript', 'react' ],
			'tag_tokens'   => [ 'wordpress', 'tutorial' ],
			'cat_tokens'   => [],
		];

		$score_title = LinkSuggest::score_candidate( $content, $candidate_title );
		$score_tag   = LinkSuggest::score_candidate( $content, $candidate_tag );

		$this->assertGreaterThan( $score_tag, $score_title );
	}

	public function test_score_partial_title_overlap(): void {
		$content   = [ 'wordpress', 'plugin', 'seo', 'optimization' ];
		$candidate = [
			'title_tokens'   => [ 'wordpress', 'seo', 'guide', 'tips' ],
			'tag_tokens'     => [],
			'excerpt_tokens' => [],
			'cat_tokens'     => [],
		];
		// 2 shared out of 4 title tokens → title_overlap = 2/4 = 0.5 → score = 0.5 * 3.0 = 1.5
		$score = LinkSuggest::score_candidate( $content, $candidate );
		$this->assertEqualsWithDelta( 1.5, $score, 0.0001 );
	}

	public function test_score_excerpt_weights_between_tags_and_categories(): void {
		$content = [ 'donau', 'fluss', 'bayern' ];

		// Candidate with only excerpt overlap.
		$candidate_excerpt = [
			'title_tokens'   => [ 'deggendorf' ],
			'tag_tokens'     => [],
			'excerpt_tokens' => [ 'donau', 'niederbayern' ], // 1 shared / 2 = 0.5 → 0.5 * 1.5 = 0.75
			'cat_tokens'     => [],
		];
		// Candidate with only category overlap.
		$candidate_cat = [
			'title_tokens'   => [ 'deggendorf' ],
			'tag_tokens'     => [],
			'excerpt_tokens' => [],
			'cat_tokens'     => [ 'donau', 'fluss' ], // 2 shared / 2 = 1.0 → 1.0 * 1.0 = 1.0
		];

		$score_excerpt = LinkSuggest::score_candidate( $content, $candidate_excerpt );
		$score_cat     = LinkSuggest::score_candidate( $content, $candidate_cat );

		// Excerpt (×1.5) beats category (×1.0) for equal proportional overlap.
		$this->assertGreaterThan( 0.0, $score_excerpt );
		$this->assertEqualsWithDelta( 0.75, $score_excerpt, 0.0001 );
		$this->assertEqualsWithDelta( 1.0, $score_cat, 0.0001 );
	}

	// -------------------------------------------------------------------------
	// apply_boost tests
	// -------------------------------------------------------------------------

	public function test_boost_multiplies_score(): void {
		$this->assertEqualsWithDelta( 4.5, LinkSuggest::apply_boost( 1.5, 3.0 ), 0.0001 );
		$this->assertEqualsWithDelta( 2.0, LinkSuggest::apply_boost( 1.0, 2.0 ), 0.0001 );
	}

	public function test_boost_does_not_create_relevance_from_zero(): void {
		// 0 * anything = 0, so a boost cannot make a non-relevant result appear.
		$this->assertSame( 0.0, LinkSuggest::apply_boost( 0.0, 5.0 ) );
		$this->assertSame( 0.0, LinkSuggest::apply_boost( 0.0, 100.0 ) );
	}

	// -------------------------------------------------------------------------
	// find_best_phrase tests
	// -------------------------------------------------------------------------

	public function test_find_phrase_returns_matching_ngram(): void {
		$content     = 'WordPress SEO plugins help you optimize your site for search engines.';
		// Simulates combined topic tokens (title + tags + cats) of the target post.
		$topicTokens = [ 'wordpress', 'seo', 'plugins' ];
		$phrase      = LinkSuggest::find_best_phrase( $content, $topicTokens );
		$this->assertNotSame( '', $phrase );
		// The phrase should appear in the original content (case-insensitive).
		$this->assertStringContainsStringIgnoringCase( $phrase, $content );
	}

	public function test_find_phrase_via_tag_token_without_title_overlap(): void {
		// Simulates: article about "Donau" links to "Deggendorf" post.
		// "Deggendorf" never appears in the content, but the tag token "donau" does.
		// Combined topic tokens = title_tokens + tag_tokens: ['deggendorf', 'donau', 'niederbayern'].
		$content     = 'Die Donau ist einer der längsten Flüsse Europas und fließt durch Bayern.';
		$topicTokens = [ 'deggendorf', 'donau', 'niederbayern' ]; // title + tags of target
		$phrase      = LinkSuggest::find_best_phrase( $content, $topicTokens );
		// Should find an anchor containing "Donau" — the shared topic word.
		$this->assertNotSame( '', $phrase );
		$this->assertStringContainsStringIgnoringCase( $phrase, $content );
		$this->assertStringContainsStringIgnoringCase( 'donau', $phrase );
	}

	public function test_find_phrase_skips_existing_links(): void {
		$content = 'Visit <a href="/x">WordPress SEO</a> for plugins and performance tips';
		$title   = [ 'wordpress', 'seo' ];
		$phrase  = LinkSuggest::find_best_phrase( $content, $title );
		// All occurrences of the matching phrase are already linked — expect empty
		$this->assertSame( '', $phrase );
	}

	public function test_find_phrase_returns_empty_when_no_match(): void {
		$content     = 'Completely unrelated text about cooking and recipes.';
		$topicTokens = [ 'quantum', 'physics', 'relativity' ];
		$phrase      = LinkSuggest::find_best_phrase( $content, $topicTokens );
		$this->assertSame( '', $phrase );
	}

	public function test_tokenize_filters_short_multibyte_words(): void {
		// "öl" is 2 characters (4 bytes in UTF-8) — should be filtered (length <= 2)
		$tokens = LinkSuggest::tokenize( 'Das Öl ist teuer', 'de' );
		$this->assertNotContains( 'öl', $tokens );
		// Longer words should remain
		$this->assertContains( 'teuer', $tokens );
	}

	// -------------------------------------------------------------------------
	// filter_excluded tests
	// -------------------------------------------------------------------------

	public function test_filter_excluded_removes_matching_ids(): void {
		$candidates = [
			[ 'post_id' => 1, 'title' => 'Alpha' ],
			[ 'post_id' => 2, 'title' => 'Beta' ],
			[ 'post_id' => 3, 'title' => 'Gamma' ],
			[ 'post_id' => 4, 'title' => 'Delta' ],
		];
		$excluded = [ 2, 4 ];
		$result   = LinkSuggest::filter_excluded( $candidates, $excluded );

		$this->assertCount( 2, $result );
		// Result must be re-indexed.
		$this->assertArrayHasKey( 0, $result );
		$this->assertArrayHasKey( 1, $result );
		// IDs 2 and 4 must be gone.
		$ids = array_column( $result, 'post_id' );
		$this->assertNotContains( 2, $ids );
		$this->assertNotContains( 4, $ids );
		$this->assertContains( 1, $ids );
		$this->assertContains( 3, $ids );
	}
}
