# Internal Link Suggestions Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add an editor meta box that suggests relevant internal links (phrase → target post) while a blogger writes, with manual approve + multi-select apply.

**Architecture:** Server-side AJAX matching in `LinkSuggest.php` (pure tokenize/score methods + WP candidate pool); JS `link-suggest.js` handles trigger, UI, preview modal, and editor apply via official APIs. Settings live on their own admin page (`bre-link-suggest`). AI is an optional quality upgrade in the AJAX handler.

**Tech Stack:** PHP 8.1, PHPUnit (existing bootstrap), WordPress admin-ajax.php, wp.data / tinyMCE APIs, vanilla JS + jQuery (already loaded in editor)

---

## Task 1: Core matching logic + tests

Pure PHP — no WordPress dependencies. All methods static so tests need no mocks.

**Files:**
- Create: `includes/Features/LinkSuggest.php`
- Create: `tests/Features/LinkSuggestTest.php`

---

**Step 1: Write the failing tests**

```php
<?php
// tests/Features/LinkSuggestTest.php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\LinkSuggest;

class LinkSuggestTest extends TestCase {

    // --- tokenize ---

    public function test_tokenize_strips_html_and_lowercases(): void {
        $tokens = LinkSuggest::tokenize( '<p>Bavarian Alps Hiking</p>' );
        $this->assertContains( 'bavarian', $tokens );
        $this->assertContains( 'alps', $tokens );
        $this->assertContains( 'hiking', $tokens );
    }

    public function test_tokenize_removes_stop_words_en(): void {
        $tokens = LinkSuggest::tokenize( 'the best hiking in the Alps', 'en' );
        $this->assertNotContains( 'the', $tokens );
        $this->assertNotContains( 'in', $tokens );
        $this->assertContains( 'hiking', $tokens );
        $this->assertContains( 'alps', $tokens );
    }

    public function test_tokenize_removes_stop_words_de(): void {
        $tokens = LinkSuggest::tokenize( 'Die besten Wanderwege in den Alpen', 'de' );
        $this->assertNotContains( 'die', $tokens );
        $this->assertNotContains( 'in', $tokens );
        $this->assertNotContains( 'den', $tokens );
        $this->assertContains( 'wanderwege', $tokens );
        $this->assertContains( 'alpen', $tokens );
    }

    public function test_tokenize_returns_empty_for_blank_input(): void {
        $this->assertSame( [], LinkSuggest::tokenize( '' ) );
    }

    // --- scoreCandidate ---

    public function test_score_returns_zero_when_no_overlap(): void {
        $content    = [ 'hiking', 'mountain', 'trail' ];
        $candidate  = [ 'title_tokens' => [ 'recipes', 'pasta', 'cooking' ], 'tag_tokens' => [], 'cat_tokens' => [] ];
        $this->assertSame( 0.0, LinkSuggest::scoreCandidate( $content, $candidate ) );
    }

    public function test_score_title_overlap_weights_higher_than_tags(): void {
        $content   = [ 'hiking', 'mountain' ];
        $title_hit = [ 'title_tokens' => [ 'hiking', 'mountain' ], 'tag_tokens' => [],         'cat_tokens' => [] ];
        $tag_hit   = [ 'title_tokens' => [ 'cooking' ],            'tag_tokens' => [ 'hiking' ], 'cat_tokens' => [] ];
        $this->assertGreaterThan(
            LinkSuggest::scoreCandidate( $content, $tag_hit ),
            LinkSuggest::scoreCandidate( $content, $title_hit )
        );
    }

    public function test_score_partial_title_overlap(): void {
        $content   = [ 'bavarian', 'alps', 'weather', 'mountain' ];
        $candidate = [ 'title_tokens' => [ 'bavarian', 'alps', 'guide' ], 'tag_tokens' => [], 'cat_tokens' => [] ];
        // 2 of 3 title tokens match → title_overlap = 2/3
        $score = LinkSuggest::scoreCandidate( $content, $candidate );
        $this->assertGreaterThan( 0.0, $score );
    }

    // --- applyBoost ---

    public function test_boost_multiplies_score(): void {
        $this->assertEqualsWithDelta( 4.0, LinkSuggest::applyBoost( 2.0, 2.0 ), 0.001 );
    }

    public function test_boost_does_not_create_relevance_from_zero(): void {
        $this->assertSame( 0.0, LinkSuggest::applyBoost( 0.0, 5.0 ) );
    }

    // --- findBestPhrase ---

    public function test_find_phrase_returns_matching_ngram(): void {
        $content = 'We love hiking in the Bavarian Alps every summer';
        $title   = [ 'bavarian', 'alps' ];
        $phrase  = LinkSuggest::findBestPhrase( $content, $title );
        $this->assertStringContainsStringIgnoringCase( 'Bavarian Alps', $phrase );
    }

    public function test_find_phrase_skips_existing_links(): void {
        $content = 'Visit <a href="/x">Bavarian Alps</a> for hiking';
        $title   = [ 'bavarian', 'alps' ];
        $phrase  = LinkSuggest::findBestPhrase( $content, $title );
        // Already linked — should return empty
        $this->assertSame( '', $phrase );
    }

    public function test_find_phrase_returns_empty_when_no_match(): void {
        $content = 'Pasta carbonara recipe with eggs and cheese';
        $title   = [ 'hiking', 'mountain' ];
        $phrase  = LinkSuggest::findBestPhrase( $content, $title );
        $this->assertSame( '', $phrase );
    }

    // --- filterExcluded ---

    public function test_filter_excluded_removes_matching_ids(): void {
        $candidates = [
            [ 'post_id' => 1, 'score' => 1.0 ],
            [ 'post_id' => 2, 'score' => 0.8 ],
            [ 'post_id' => 3, 'score' => 0.5 ],
        ];
        $result = LinkSuggest::filterExcluded( $candidates, [ 2 ] );
        $ids    = array_column( $result, 'post_id' );
        $this->assertNotContains( 2, $ids );
        $this->assertContains( 1, $ids );
        $this->assertContains( 3, $ids );
    }
}
```

**Step 2: Run — expect FAIL (class does not exist)**

```bash
cd /var/www/plugins/bre/bre-dev
php composer.phar exec phpunit -- tests/Features/LinkSuggestTest.php
```

Expected: `Error: Class "BavarianRankEngine\Features\LinkSuggest" not found`

---

**Step 3: Implement `LinkSuggest.php` — pure static methods only**

```php
<?php
namespace BavarianRankEngine\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkSuggest {

	private const STOP_EN = [
		'a','an','the','and','or','but','in','on','at','to','for','of','with',
		'by','from','is','are','was','were','be','been','has','have','had',
		'do','does','did','will','would','could','should','may','might','that',
		'this','these','those','it','its','as','up','out','so','if','about',
		'into','than','then','when','where','which','who','not','no','can','he',
		'she','we','you','they','their','our','your','his','her','my','its',
	];

	private const STOP_DE = [
		'der','die','das','ein','eine','und','oder','aber','in','an','auf','zu',
		'für','von','mit','bei','aus','nach','über','unter','vor','hinter',
		'ist','sind','war','waren','sein','haben','hat','hatte','hatten','wird',
		'werden','wurde','wurden','ich','du','er','sie','es','wir','ihr','sie',
		'den','dem','des','einer','einem','eines','nicht','auch','noch','schon',
		'so','wie','da','dann','wenn','als','um','durch','am','im','beim',
	];

	/**
	 * Strip HTML, lowercase, tokenise, remove stop words.
	 *
	 * @return string[]
	 */
	public static function tokenize( string $text, string $lang = 'en' ): array {
		if ( $text === '' ) {
			return [];
		}
		$plain  = wp_strip_all_tags( $text );
		$plain  = strtolower( $plain );
		$words  = preg_split( '/\W+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
		$stops  = $lang === 'de' ? self::STOP_DE : self::STOP_EN;
		return array_values( array_filter( $words, fn( $w ) => strlen( $w ) > 2 && ! in_array( $w, $stops, true ) ) );
	}

	/**
	 * Score a single candidate against content tokens.
	 * Score = (title_overlap × 3) + (tag_overlap × 2) + (cat_overlap × 1)
	 * title_overlap = shared / title_token_count
	 */
	public static function scoreCandidate( array $contentTokens, array $candidate ): float {
		$titleTokens = $candidate['title_tokens'] ?? [];
		$tagTokens   = $candidate['tag_tokens']   ?? [];
		$catTokens   = $candidate['cat_tokens']   ?? [];

		$titleOverlap = self::overlap( $contentTokens, $titleTokens );
		$tagOverlap   = self::overlap( $contentTokens, $tagTokens );
		$catOverlap   = self::overlap( $contentTokens, $catTokens );

		return ( $titleOverlap * 3.0 ) + ( $tagOverlap * 2.0 ) + ( $catOverlap * 1.0 );
	}

	/** Shared tokens / candidate token count (0–1). Returns 0.0 when candidate empty. */
	private static function overlap( array $content, array $candidate ): float {
		if ( empty( $candidate ) ) {
			return 0.0;
		}
		$shared = count( array_intersect( $content, $candidate ) );
		return $shared / count( $candidate );
	}

	/** Multiply score by boost — boost never creates relevance from zero. */
	public static function applyBoost( float $score, float $boost ): float {
		return $score * $boost;
	}

	/**
	 * Find the best N-gram (2–6 words) in $rawContent that overlaps with $titleTokens.
	 * Skips phrases already wrapped in <a> tags.
	 * Returns the original-case phrase or '' when none found.
	 */
	public static function findBestPhrase( string $rawContent, array $titleTokens, int $minLen = 2, int $maxLen = 6 ): string {
		if ( empty( $titleTokens ) ) {
			return '';
		}

		// Strip existing links from search space (keep surrounding text, remove <a>…</a>)
		$noLinks = preg_replace( '/<a[^>]*>.*?<\/a>/is', ' ', $rawContent );
		$plain   = wp_strip_all_tags( $noLinks );

		// Split into word-tokens preserving original case
		preg_match_all( '/\b[\wäöüÄÖÜß]+\b/u', $plain, $m );
		$words = $m[0];
		$count = count( $words );

		$best      = '';
		$bestScore = 0;

		for ( $start = 0; $start < $count; $start++ ) {
			for ( $len = $minLen; $len <= min( $maxLen, $count - $start ); $len++ ) {
				$gram       = array_slice( $words, $start, $len );
				$gramLower  = array_map( 'strtolower', $gram );
				$shared     = count( array_intersect( $gramLower, $titleTokens ) );
				if ( $shared === 0 ) {
					continue;
				}
				// Prefer longer phrases with higher overlap ratio
				$score = $shared / $len + $len * 0.1;
				if ( $score > $bestScore ) {
					$bestScore = $score;
					$best      = implode( ' ', $gram );
				}
			}
		}

		// Verify the phrase actually exists in the original HTML outside an <a>
		if ( $best !== '' && ! self::phraseExistsOutsideLinks( $rawContent, $best ) ) {
			return '';
		}

		return $best;
	}

	/** Check that a phrase occurs in content outside any <a> tag. */
	private static function phraseExistsOutsideLinks( string $html, string $phrase ): bool {
		$noLinks = preg_replace( '/<a[^>]*>.*?<\/a>/is', '', $html );
		$plain   = wp_strip_all_tags( $noLinks );
		return stripos( $plain, $phrase ) !== false;
	}

	/** Remove candidates whose post_id is in $excludedIds. */
	public static function filterExcluded( array $candidates, array $excludedIds ): array {
		return array_values(
			array_filter( $candidates, fn( $c ) => ! in_array( $c['post_id'], $excludedIds, true ) )
		);
	}
}
```

**Step 4: Run tests — expect PASS**

```bash
php composer.phar exec phpunit -- tests/Features/LinkSuggestTest.php
```

Expected: All tests pass.

**Step 5: Commit**

```bash
git add includes/Features/LinkSuggest.php tests/Features/LinkSuggestTest.php
git commit -m "feat: LinkSuggest core matching — tokenize, score, boost, phrase finder"
```

---

## Task 2: Candidate pool + AJAX handler

WP-dependent methods added to `LinkSuggest.php`. Tests use the existing bootstrap stubs.

**Files:**
- Modify: `includes/Features/LinkSuggest.php` (add WP methods)
- Create: `tests/Features/LinkSuggestPoolTest.php`

---

**Step 1: Write failing tests**

```php
<?php
// tests/Features/LinkSuggestPoolTest.php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\LinkSuggest;

class LinkSuggestPoolTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['bre_transients'] = [];
    }

    public function test_get_settings_returns_defaults(): void {
        $settings = LinkSuggest::getSettings();
        $this->assertSame( 'manual', $settings['trigger'] );
        $this->assertSame( 2, $settings['interval_min'] );
        $this->assertSame( [], $settings['excluded_posts'] );
        $this->assertSame( [], $settings['boosted_posts'] );
        $this->assertSame( 20, $settings['ai_candidates'] );
        $this->assertSame( 400, $settings['ai_max_tokens'] );
    }

    public function test_get_settings_merges_saved_values(): void {
        $GLOBALS['bre_test_options']['bre_link_suggest_settings'] = [
            'trigger'     => 'save',
            'interval_min' => 5,
        ];
        $settings = LinkSuggest::getSettings();
        $this->assertSame( 'save', $settings['trigger'] );
        $this->assertSame( 5, $settings['interval_min'] );
        // Defaults still present for untouched keys
        $this->assertSame( 20, $settings['ai_candidates'] );
    }

    public function test_build_boost_map_returns_id_factor_map(): void {
        $boosted = [
            [ 'id' => 10, 'boost' => 2.0 ],
            [ 'id' => 20, 'boost' => 1.5 ],
        ];
        $map = LinkSuggest::buildBoostMap( $boosted );
        $this->assertSame( 2.0, $map[10] );
        $this->assertSame( 1.5, $map[20] );
    }
}
```

**Step 2: Run — expect FAIL**

```bash
php composer.phar exec phpunit -- tests/Features/LinkSuggestPoolTest.php
```

**Step 3: Add WP-dependent methods to `LinkSuggest.php`**

Add after the closing brace of `filterExcluded`, before the class closing brace:

```php
	public const OPTION_KEY = 'bre_link_suggest_settings';

	/** Returns settings merged with defaults. */
	public static function getSettings(): array {
		$defaults = [
			'trigger'        => 'manual',
			'interval_min'   => 2,
			'excluded_posts' => [],
			'boosted_posts'  => [],
			'ai_candidates'  => 20,
			'ai_max_tokens'  => 400,
		];
		$saved = get_option( self::OPTION_KEY, [] );
		$saved = is_array( $saved ) ? $saved : [];
		return array_merge( $defaults, $saved );
	}

	/** Convert boosted_posts array to [post_id => factor] map. */
	public static function buildBoostMap( array $boostedPosts ): array {
		$map = [];
		foreach ( $boostedPosts as $entry ) {
			$id    = (int) ( $entry['id']    ?? 0 );
			$boost = (float) ( $entry['boost'] ?? 1.0 );
			if ( $id > 0 ) {
				$map[ $id ] = max( 1.0, $boost );
			}
		}
		return $map;
	}

	/**
	 * Registers AJAX action + meta box.
	 * Called from Core::register_hooks().
	 */
	public function register(): void {
		add_action( 'wp_ajax_bre_link_suggestions', [ $this, 'ajax_suggest' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'save_post', [ $this, 'invalidate_cache' ] );
	}

	public function invalidate_cache(): void {
		delete_transient( 'bre_link_candidate_pool' );
	}

	public function add_meta_box(): void {
		$post_types = \BavarianRankEngine\Admin\SettingsPage::getSettings()['meta_post_types'] ?? [ 'post', 'page' ];
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'bre_link_suggest',
				__( 'Internal Link Suggestions (BRE)', 'bavarian-rank-engine' ),
				[ $this, 'render_meta_box' ],
				$pt,
				'normal',
				'default'
			);
		}
	}

	public function render_meta_box( \WP_Post $post ): void {
		include BRE_DIR . 'includes/Admin/views/link-suggest-box.php';
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$settings = self::getSettings();
		$lang     = str_starts_with( get_locale(), 'de_' ) ? 'de' : 'en';
		wp_enqueue_script( 'bre-link-suggest', BRE_URL . 'assets/link-suggest.js', [ 'jquery' ], BRE_VERSION, true );
		global $post;
		wp_localize_script(
			'bre-link-suggest',
			'breLinkSuggest',
			[
				'nonce'       => wp_create_nonce( 'bre_admin' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'postId'      => $post ? (int) $post->ID : 0,
				'triggerMode' => $settings['trigger'],
				'intervalMs'  => max( 1, (int) $settings['interval_min'] ) * 60000,
				'lang'        => $lang,
				'i18n'        => [
					'title'        => __( 'Internal Link Suggestions (BRE)', 'bavarian-rank-engine' ),
					'analyse'      => __( 'Analyse', 'bavarian-rank-engine' ),
					'loading'      => __( 'Analysing…', 'bavarian-rank-engine' ),
					'noResults'    => __( 'No suggestions found.', 'bavarian-rank-engine' ),
					'applyBtn'     => __( 'Apply (%d links)', 'bavarian-rank-engine' ),
					'selectAll'    => __( 'All', 'bavarian-rank-engine' ),
					'selectNone'   => __( 'None', 'bavarian-rank-engine' ),
					'preview'      => __( 'Preview', 'bavarian-rank-engine' ),
					'confirm'      => __( 'Confirm', 'bavarian-rank-engine' ),
					'cancel'       => __( 'Cancel', 'bavarian-rank-engine' ),
					'applied'      => __( 'Applied — %d links set ✓', 'bavarian-rank-engine' ),
					'boosted'      => __( 'Prioritised', 'bavarian-rank-engine' ),
					'openPost'     => __( 'Open post', 'bavarian-rank-engine' ),
					'networkError' => __( 'Network error', 'bavarian-rank-engine' ),
				],
			]
		);
	}

	/**
	 * AJAX handler: receives post_id + post_content, returns suggestions JSON.
	 */
	public function ajax_suggest(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$content = wp_kses_post( wp_unslash( $_POST['post_content'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $post_id || ! $content ) {
			wp_send_json_success( [] );
		}

		$settings    = self::getSettings();
		$lang        = str_starts_with( get_locale(), 'de_' ) ? 'de' : 'en';
		$contentToks = self::tokenize( $content, $lang );

		if ( empty( $contentToks ) ) {
			wp_send_json_success( [] );
		}

		$pool      = $this->getCandidatePool( $post_id );
		$excluded  = array_map( 'intval', $settings['excluded_posts'] );
		$pool      = self::filterExcluded( $pool, $excluded );
		$boostMap  = self::buildBoostMap( $settings['boosted_posts'] );

		// Score + boost
		foreach ( $pool as &$candidate ) {
			$score               = self::scoreCandidate( $contentToks, $candidate );
			$boost               = $boostMap[ $candidate['post_id'] ] ?? 1.0;
			$candidate['score']  = self::applyBoost( $score, $boost );
			$candidate['boosted'] = isset( $boostMap[ $candidate['post_id'] ] );
		}
		unset( $candidate );

		// Filter zero-score, sort, take top 20
		$pool = array_filter( $pool, fn( $c ) => $c['score'] > 0.0 );
		usort( $pool, fn( $a, $b ) => $b['score'] <=> $a['score'] );
		$pool = array_slice( $pool, 0, 20 );

		// Find best anchor phrase for each candidate
		$suggestions = [];
		foreach ( $pool as $candidate ) {
			$phrase = self::findBestPhrase( $content, $candidate['title_tokens'] );
			if ( $phrase === '' ) {
				continue;
			}
			$suggestions[] = [
				'phrase'     => $phrase,
				'post_id'    => $candidate['post_id'],
				'post_title' => $candidate['post_title'],
				'url'        => $candidate['url'],
				'score'      => round( $candidate['score'], 3 ),
				'boosted'    => $candidate['boosted'],
			];
			if ( count( $suggestions ) >= 10 ) {
				break;
			}
		}

		wp_send_json_success( $suggestions );
	}

	/**
	 * Build candidate pool from DB, cached 1 hour.
	 *
	 * @return array [post_id, post_title, url, title_tokens, tag_tokens, cat_tokens][]
	 */
	private function getCandidatePool( int $excludePostId ): array {
		$cached = get_transient( 'bre_link_candidate_pool' );
		if ( $cached !== false ) {
			return array_values( array_filter( $cached, fn( $c ) => $c['post_id'] !== $excludePostId ) );
		}

		global $wpdb;
		$lang  = str_starts_with( get_locale(), 'de_' ) ? 'de' : 'en';
		$posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT ID, post_title FROM {$wpdb->posts}
			 WHERE post_status = 'publish'
			   AND post_type IN ('post','page')
			 ORDER BY post_date DESC
			 LIMIT 500"
		);

		$pool = [];
		foreach ( $posts as $post ) {
			$tags = wp_get_post_terms( (int) $post->ID, 'post_tag', [ 'fields' => 'names' ] );
			$cats = wp_get_post_terms( (int) $post->ID, 'category', [ 'fields' => 'names' ] );

			$tagStr = is_array( $tags ) ? implode( ' ', $tags ) : '';
			$catStr = is_array( $cats ) ? implode( ' ', $cats ) : '';

			$pool[] = [
				'post_id'      => (int) $post->ID,
				'post_title'   => $post->post_title,
				'url'          => get_permalink( (int) $post->ID ),
				'title_tokens' => self::tokenize( $post->post_title, $lang ),
				'tag_tokens'   => self::tokenize( $tagStr, $lang ),
				'cat_tokens'   => self::tokenize( $catStr, $lang ),
			];
		}

		set_transient( 'bre_link_candidate_pool', $pool, HOUR_IN_SECONDS );

		return array_values( array_filter( $pool, fn( $c ) => $c['post_id'] !== $excludePostId ) );
	}
```

> **Note:** The class closing `}` is already at end of file from Task 1 — paste these methods before it.

**Step 4: Run both test files — expect all PASS**

```bash
php composer.phar exec phpunit -- tests/Features/LinkSuggestTest.php tests/Features/LinkSuggestPoolTest.php
```

**Step 5: Commit**

```bash
git add includes/Features/LinkSuggest.php tests/Features/LinkSuggestPoolTest.php
git commit -m "feat: LinkSuggest WP methods — settings, candidate pool, AJAX handler"
```

---

## Task 3: Settings page

**Files:**
- Create: `includes/Admin/LinkSuggestPage.php`
- Create: `includes/Admin/views/link-suggest-settings.php`

---

**Step 1: Create `LinkSuggestPage.php`**

```php
<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\Features\LinkSuggest;

class LinkSuggestPage {

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_settings(): void {
		register_setting(
			'bre_link_suggest',
			LinkSuggest::OPTION_KEY,
			[ 'sanitize_callback' => [ $this, 'sanitize' ] ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'bavarian-rank_page_bre-link-suggest' ) {
			return;
		}
		wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', [], BRE_VERSION );
		wp_enqueue_script( 'bre-admin', BRE_URL . 'assets/admin.js', [ 'jquery' ], BRE_VERSION, true );
		wp_localize_script(
			'bre-admin',
			'breAdmin',
			[
				'nonce'   => wp_create_nonce( 'bre_admin' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			]
		);
	}

	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : [];
		$clean = [];

		$allowed_triggers    = [ 'manual', 'save', 'interval' ];
		$clean['trigger']    = in_array( $input['trigger'] ?? '', $allowed_triggers, true )
								? $input['trigger'] : 'manual';
		$clean['interval_min'] = max( 1, min( 60, (int) ( $input['interval_min'] ?? 2 ) ) );
		$clean['ai_candidates'] = max( 1, min( 50, (int) ( $input['ai_candidates'] ?? 20 ) ) );
		$clean['ai_max_tokens'] = max( 100, min( 2000, (int) ( $input['ai_max_tokens'] ?? 400 ) ) );

		// Excluded posts: array of ints
		$clean['excluded_posts'] = array_values(
			array_map( 'intval', (array) ( $input['excluded_posts'] ?? [] ) )
		);

		// Boosted posts: [{id, boost}]
		$clean['boosted_posts'] = [];
		foreach ( (array) ( $input['boosted_posts'] ?? [] ) as $entry ) {
			$id    = (int) ( $entry['id']    ?? 0 );
			$boost = (float) ( $entry['boost'] ?? 1.5 );
			if ( $id > 0 ) {
				$clean['boosted_posts'][] = [
					'id'    => $id,
					'boost' => max( 1.0, min( 10.0, $boost ) ),
				];
			}
		}

		return $clean;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = LinkSuggest::getSettings();
		$has_ai   = ! empty( \BavarianRankEngine\Admin\SettingsPage::getSettings()['ai_enabled'] )
					&& ! empty( \BavarianRankEngine\Admin\SettingsPage::getSettings()['api_keys'][
						\BavarianRankEngine\Admin\SettingsPage::getSettings()['provider']
					] );
		include BRE_DIR . 'includes/Admin/views/link-suggest-settings.php';
	}
}
```

**Step 2: Create the view `link-suggest-settings.php`**

```php
<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'Link Suggestions', 'bavarian-rank-engine' ); ?></h1>

	<?php settings_errors( 'bre_link_suggest' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'bre_link_suggest' ); ?>

		<h2><?php esc_html_e( 'Analysis Trigger', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'When to analyse', 'bavarian-rank-engine' ); ?></th>
				<td>
					<label>
						<input type="radio" name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[trigger]"
							value="manual" <?php checked( $settings['trigger'], 'manual' ); ?>>
						<?php esc_html_e( 'Manual only (button)', 'bavarian-rank-engine' ); ?>
					</label><br>
					<label>
						<input type="radio" name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[trigger]"
							value="save" <?php checked( $settings['trigger'], 'save' ); ?>>
						<?php esc_html_e( 'On post save', 'bavarian-rank-engine' ); ?>
					</label><br>
					<label>
						<input type="radio" name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[trigger]"
							value="interval" <?php checked( $settings['trigger'], 'interval' ); ?>>
						<?php esc_html_e( 'Every', 'bavarian-rank-engine' ); ?>
						<input type="number" min="1" max="60"
							name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[interval_min]"
							value="<?php echo esc_attr( $settings['interval_min'] ); ?>"
							style="width:55px;">
						<?php esc_html_e( 'minutes', 'bavarian-rank-engine' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Exclude Posts / Pages', 'bavarian-rank-engine' ); ?></h2>
		<p class="description"><?php esc_html_e( 'These posts will never appear as link suggestions (e.g. Imprint, Contact, Terms).', 'bavarian-rank-engine' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Excluded', 'bavarian-rank-engine' ); ?></th>
				<td>
					<div id="bre-ls-excluded-list">
						<?php foreach ( $settings['excluded_posts'] as $pid ) :
							$title = get_the_title( $pid );
							if ( ! $title ) continue;
							?>
						<span class="bre-ls-tag" data-id="<?php echo esc_attr( $pid ); ?>">
							<?php echo esc_html( $title ); ?>
							<input type="hidden" name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[excluded_posts][]" value="<?php echo esc_attr( $pid ); ?>">
							<button type="button" class="bre-ls-remove" aria-label="<?php esc_attr_e( 'Remove', 'bavarian-rank-engine' ); ?>">✕</button>
						</span>
						<?php endforeach; ?>
					</div>
					<input type="search" id="bre-ls-exclude-search" placeholder="<?php esc_attr_e( 'Search posts…', 'bavarian-rank-engine' ); ?>" style="width:300px;margin-top:6px;">
					<div id="bre-ls-exclude-results" style="display:none;border:1px solid #ddd;background:#fff;max-height:200px;overflow-y:auto;width:300px;position:absolute;z-index:100;"></div>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Prioritise Posts / Pages', 'bavarian-rank-engine' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Boosted posts rank higher when thematically relevant. A boost of 1.0 = no change.', 'bavarian-rank-engine' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Boosted', 'bavarian-rank-engine' ); ?></th>
				<td>
					<div id="bre-ls-boosted-list">
						<?php foreach ( $settings['boosted_posts'] as $idx => $entry ) :
							$title = get_the_title( $entry['id'] );
							if ( ! $title ) continue;
							?>
						<div class="bre-ls-boost-row" style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
							<span>&#9733; <?php echo esc_html( $title ); ?></span>
							<input type="hidden" name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[boosted_posts][<?php echo (int) $idx; ?>][id]" value="<?php echo esc_attr( $entry['id'] ); ?>">
							<label><?php esc_html_e( 'Boost:', 'bavarian-rank-engine' ); ?>
								<input type="number" step="0.1" min="1" max="10"
									name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[boosted_posts][<?php echo (int) $idx; ?>][boost]"
									value="<?php echo esc_attr( $entry['boost'] ); ?>"
									style="width:60px;">
							</label>
							<button type="button" class="button bre-ls-remove"><?php esc_html_e( 'Remove', 'bavarian-rank-engine' ); ?></button>
						</div>
						<?php endforeach; ?>
					</div>
					<input type="search" id="bre-ls-boost-search" placeholder="<?php esc_attr_e( 'Search posts…', 'bavarian-rank-engine' ); ?>" style="width:300px;margin-top:6px;">
					<div id="bre-ls-boost-results" style="display:none;border:1px solid #ddd;background:#fff;max-height:200px;overflow-y:auto;width:300px;position:absolute;z-index:100;"></div>
				</td>
			</tr>
		</table>

		<?php if ( $has_ai ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
		<h2><?php esc_html_e( 'AI Options (optional)', 'bavarian-rank-engine' ); ?></h2>
		<p class="description"><?php esc_html_e( 'AI is connected — these settings control how many candidates are sent for semantic analysis.', 'bavarian-rank-engine' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Candidates to AI', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="number" min="1" max="50"
						name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[ai_candidates]"
						value="<?php echo esc_attr( $settings['ai_candidates'] ); ?>"
						style="width:70px;">
					<p class="description"><?php esc_html_e( 'How many pre-scored candidates are passed to the AI (max 50).', 'bavarian-rank-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Max output tokens', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="number" min="100" max="2000"
						name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[ai_max_tokens]"
						value="<?php echo esc_attr( $settings['ai_max_tokens'] ); ?>"
						style="width:70px;">
				</td>
			</tr>
		</table>
		<?php endif; ?>

		<?php submit_button( __( 'Save Settings', 'bavarian-rank-engine' ) ); ?>
	</form>

	<p class="bre-footer">
		Bavarian Rank Engine <?php echo esc_html( BRE_VERSION ); ?> &mdash;
		<?php esc_html_e( 'developed with', 'bavarian-rank-engine' ); ?> ♥
		<a href="https://donau2space.de" target="_blank" rel="noopener">Donau2Space.de</a>
	</p>
</div>
```

**Step 3: Commit**

```bash
git add includes/Admin/LinkSuggestPage.php includes/Admin/views/link-suggest-settings.php
git commit -m "feat: LinkSuggestPage settings UI (trigger, exclude, boost, AI options)"
```

---

## Task 4: Editor meta box view

The meta box container. JS populates it dynamically.

**Files:**
- Create: `includes/Admin/views/link-suggest-box.php`

---

**Step 1: Create the view**

```php
<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div id="bre-link-suggest-box">
	<div id="bre-ls-state-idle" style="display:flex;align-items:center;justify-content:space-between;">
		<span style="color:#888;font-size:12px;" id="bre-ls-status">
			<?php esc_html_e( 'Click Analyse to find internal link opportunities.', 'bavarian-rank-engine' ); ?>
		</span>
		<button type="button" id="bre-ls-analyse" class="button">
			<?php esc_html_e( 'Analyse', 'bavarian-rank-engine' ); ?>
		</button>
	</div>

	<div id="bre-ls-results" style="display:none;margin-top:10px;">
		<div id="bre-ls-list"></div>
		<div id="bre-ls-actions" style="display:none;margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
			<button type="button" id="bre-ls-select-all" class="button button-small">
				<?php esc_html_e( 'All', 'bavarian-rank-engine' ); ?>
			</button>
			<button type="button" id="bre-ls-select-none" class="button button-small">
				<?php esc_html_e( 'None', 'bavarian-rank-engine' ); ?>
			</button>
			<button type="button" id="bre-ls-apply" class="button button-primary" style="margin-left:auto;" disabled>
				<?php esc_html_e( 'Apply (0 links)', 'bavarian-rank-engine' ); ?>
			</button>
		</div>
	</div>

	<div id="bre-ls-applied" style="display:none;color:#46b450;margin-top:8px;font-size:12px;"></div>
</div>
```

**Step 2: Commit**

```bash
git add includes/Admin/views/link-suggest-box.php
git commit -m "feat: link-suggest editor meta box HTML skeleton"
```

---

## Task 5: JavaScript — trigger, UI, apply

**Files:**
- Create: `assets/link-suggest.js`

---

**Step 1: Create `link-suggest.js`**

```js
/* global jQuery, wp, breLinkSuggest, tinyMCE */
( function ( $ ) {
    'use strict';

    if ( typeof breLinkSuggest === 'undefined' ) { return; }

    var cfg         = breLinkSuggest;
    var i18n        = cfg.i18n;
    var suggestions = [];
    var isRunning   = false;

    /* ── Helpers ─────────────────────────────────────────── */

    function getContent() {
        if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
            try {
                return wp.data.select( 'core/editor' ).getEditedPostContent();
            } catch ( e ) { /* fall through */ }
        }
        if ( typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden() ) {
            return tinyMCE.activeEditor.getContent();
        }
        return $( '#content' ).val() || '';
    }

    /* ── Core: request suggestions ───────────────────────── */

    function triggerAnalysis() {
        if ( isRunning ) { return; }
        var content = getContent();
        if ( ! content ) { return; }

        isRunning = true;
        $( '#bre-ls-status' ).text( i18n.loading );
        $( '#bre-ls-results' ).hide();
        $( '#bre-ls-applied' ).hide();

        $.post( cfg.ajaxUrl, {
            action:       'bre_link_suggestions',
            nonce:        cfg.nonce,
            post_id:      cfg.postId,
            post_content: content,
        } )
        .done( function ( res ) {
            if ( res && res.success ) {
                suggestions = res.data || [];
                renderSuggestions();
            } else {
                $( '#bre-ls-status' ).text( i18n.networkError );
            }
        } )
        .fail( function () {
            $( '#bre-ls-status' ).text( i18n.networkError );
        } )
        .always( function () {
            isRunning = false;
        } );
    }

    /* ── Render suggestion list ──────────────────────────── */

    function renderSuggestions() {
        var $list    = $( '#bre-ls-list' ).empty();
        var $results = $( '#bre-ls-results' );
        var $actions = $( '#bre-ls-actions' );
        var $status  = $( '#bre-ls-status' );

        if ( ! suggestions.length ) {
            $status.text( i18n.noResults );
            $results.hide();
            return;
        }

        $status.text( '' );

        suggestions.forEach( function ( s, idx ) {
            var $row = $( '<div class="bre-ls-row" style="display:flex;align-items:flex-start;gap:8px;padding:6px 0;border-bottom:1px solid #f0f0f0;">' );
            var $cb  = $( '<input type="checkbox" class="bre-ls-cb">' ).data( 'idx', idx );
            var $info = $( '<div style="flex:1;font-size:12px;">' );
            var badge  = s.boosted ? ' <span style="color:#f0a500;font-size:10px;" title="' + esc( i18n.boosted ) + '">&#9733;</span>' : '';
            $info.html(
                '<strong>' + esc( '\u201c' + s.phrase + '\u201d' ) + '</strong>' + badge +
                '<br><span style="color:#555;">\u2192 ' + esc( s.post_title ) + '</span>'
            );
            var $open = $( '<a href="' + esc( s.url ) + '" target="_blank" rel="noopener" style="font-size:11px;white-space:nowrap;" title="' + esc( i18n.openPost ) + '">[&#8599;]</a>' );
            $row.append( $cb, $info, $open );
            $list.append( $row );
        } );

        $results.show();
        $actions.css( 'display', 'flex' );
        updateApplyButton();
    }

    function esc( str ) {
        return $( '<div>' ).text( str ).html();
    }

    function updateApplyButton() {
        var count = $( '.bre-ls-cb:checked' ).length;
        var label = i18n.applyBtn.replace( '%d', count );
        $( '#bre-ls-apply' ).text( label ).prop( 'disabled', count === 0 );
    }

    /* ── Apply selected ──────────────────────────────────── */

    function applySelected() {
        var selected = [];
        $( '.bre-ls-cb:checked' ).each( function () {
            var idx = $( this ).data( 'idx' );
            if ( suggestions[ idx ] ) {
                selected.push( suggestions[ idx ] );
            }
        } );
        if ( ! selected.length ) { return; }

        // Build preview
        var lines = selected.map( function ( s ) {
            return '\u201c' + s.phrase + '\u201d  \u2192  ' + s.post_title;
        } ).join( '\n' );

        // eslint-disable-next-line no-alert
        if ( ! window.confirm( i18n.preview + ':\n\n' + lines + '\n\n' + i18n.confirm + '?' ) ) {
            return;
        }

        var content = getContent();
        var applied = 0;
        selected.forEach( function ( s ) {
            var result = insertLink( content, s.phrase, s.url );
            if ( result !== content ) {
                content = result;
                applied++;
            }
        } );

        if ( applied ) {
            setContent( content );
            $( '#bre-ls-results' ).hide();
            $( '#bre-ls-applied' ).text( i18n.applied.replace( '%d', applied ) ).show();
            suggestions = [];
        }
    }

    /**
     * Replace first occurrence of phrase (outside <a>) with a link.
     */
    function insertLink( html, phrase, url ) {
        // Build regex: phrase not preceded by <a href or inside existing link
        var escaped = phrase.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
        var re      = new RegExp( '(?<!["\'>])(' + escaped + ')(?![^<]*</a>)', 'i' );
        var replaced = false;
        return html.replace( re, function ( match ) {
            if ( replaced ) { return match; }
            replaced = true;
            return '<a href="' + url + '">' + match + '</a>';
        } );
    }

    function setContent( html ) {
        // Gutenberg
        if ( window.wp && wp.data && wp.data.dispatch ) {
            try {
                var blocks = wp.blocks.parse( html );
                wp.data.dispatch( 'core/block-editor' ).resetBlocks( blocks );
                return;
            } catch ( e ) { /* fall through to classic */ }
        }
        // Classic / TinyMCE
        if ( typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden() ) {
            tinyMCE.activeEditor.setContent( html );
            return;
        }
        $( '#content' ).val( html );
    }

    /* ── Event bindings ──────────────────────────────────── */

    $( document ).on( 'click', '#bre-ls-analyse',     triggerAnalysis );
    $( document ).on( 'click', '#bre-ls-apply',       applySelected );
    $( document ).on( 'click', '#bre-ls-select-all',  function () { $( '.bre-ls-cb' ).prop( 'checked', true );  updateApplyButton(); } );
    $( document ).on( 'click', '#bre-ls-select-none', function () { $( '.bre-ls-cb' ).prop( 'checked', false ); updateApplyButton(); } );
    $( document ).on( 'change', '.bre-ls-cb',         updateApplyButton );

    /* ── Trigger mode ────────────────────────────────────── */

    if ( cfg.triggerMode === 'interval' && cfg.intervalMs > 0 ) {
        setInterval( triggerAnalysis, cfg.intervalMs );
    }

    if ( cfg.triggerMode === 'save' ) {
        // Gutenberg
        if ( window.wp && wp.data ) {
            var wasSaving = false;
            wp.data.subscribe( function () {
                var isSaving = wp.data.select( 'core/editor' ) &&
                               wp.data.select( 'core/editor' ).isSavingPost();
                if ( ! wasSaving && isSaving ) {
                    triggerAnalysis();
                }
                wasSaving = isSaving;
            } );
        }
        // Classic
        $( document ).on( 'click', '#publish, #save-post', function () {
            setTimeout( triggerAnalysis, 500 );
        } );
    }

    /* ── Settings page: post search for exclude/boost ────── */

    function initPostSearch( $input, $results, onSelect ) {
        var timer;
        $input.on( 'input', function () {
            clearTimeout( timer );
            var q = $input.val().trim();
            if ( q.length < 2 ) { $results.hide(); return; }
            timer = setTimeout( function () {
                $.getJSON(
                    cfg.ajaxUrl.replace( 'admin-ajax.php', '' ) + 'wp-json/wp/v2/search',
                    { search: q, type: 'post', subtype: 'any', per_page: 10, _fields: 'id,title,url' }
                ).done( function ( items ) {
                    $results.empty().show();
                    if ( ! items.length ) {
                        $results.append( '<div style="padding:6px;">No results</div>' );
                        return;
                    }
                    items.forEach( function ( item ) {
                        $( '<div style="padding:6px;cursor:pointer;" class="bre-ls-result-item">' )
                            .text( item.title.rendered || item.title )
                            .data( 'item', item )
                            .on( 'click', function () {
                                onSelect( item );
                                $results.hide();
                                $input.val( '' );
                            } )
                            .appendTo( $results );
                    } );
                } );
            }, 300 );
        } );
        $( document ).on( 'click', function ( e ) {
            if ( ! $input.is( e.target ) ) { $results.hide(); }
        } );
    }

    // Exclude search
    if ( $( '#bre-ls-exclude-search' ).length ) {
        initPostSearch(
            $( '#bre-ls-exclude-search' ),
            $( '#bre-ls-exclude-results' ),
            function ( item ) {
                var id    = item.id;
                var title = item.title.rendered || item.title;
                var optKey = $( '#bre-ls-excluded-list' ).closest( 'form' ).find( 'input[name*="excluded_posts"]' ).first().attr( 'name' ) || '';
                // Derive field name from existing hidden inputs or hardcode
                var fieldName = 'bre_link_suggest_settings[excluded_posts][]';
                $( '#bre-ls-excluded-list' ).append(
                    $( '<span class="bre-ls-tag" style="display:inline-flex;align-items:center;gap:4px;background:#e0e0e0;padding:2px 8px;border-radius:3px;margin:2px;">' )
                        .data( 'id', id )
                        .append(
                            $( '<span>' ).text( title ),
                            $( '<input type="hidden">' ).attr( 'name', fieldName ).val( id ),
                            $( '<button type="button" class="bre-ls-remove" style="background:none;border:none;cursor:pointer;color:#555;">✕</button>' )
                        )
                );
            }
        );
    }

    // Boost search
    if ( $( '#bre-ls-boost-search' ).length ) {
        initPostSearch(
            $( '#bre-ls-boost-search' ),
            $( '#bre-ls-boost-results' ),
            function ( item ) {
                var id    = item.id;
                var title = item.title.rendered || item.title;
                var idx   = $( '.bre-ls-boost-row' ).length;
                var base  = 'bre_link_suggest_settings[boosted_posts][' + idx + ']';
                $( '#bre-ls-boosted-list' ).append(
                    $( '<div class="bre-ls-boost-row" style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">' ).append(
                        $( '<span>' ).text( '\u2605 ' + title ),
                        $( '<input type="hidden">' ).attr( 'name', base + '[id]' ).val( id ),
                        $( '<label>' ).append(
                            'Boost: ',
                            $( '<input type="number" step="0.1" min="1" max="10" style="width:60px;">' )
                                .attr( 'name', base + '[boost]' ).val( '1.5' )
                        ),
                        $( '<button type="button" class="button bre-ls-remove">Remove</button>' )
                    )
                );
            }
        );
    }

    // Remove tag / boost row
    $( document ).on( 'click', '.bre-ls-remove', function () {
        $( this ).closest( '.bre-ls-tag, .bre-ls-boost-row' ).remove();
    } );

} )( jQuery );
```

**Step 2: Commit**

```bash
git add assets/link-suggest.js
git commit -m "feat: link-suggest.js — trigger, suggestion UI, apply, settings post search"
```

---

## Task 6: Wire into Core + AdminMenu

**Files:**
- Modify: `includes/Core.php`
- Modify: `includes/Admin/AdminMenu.php`

---

**Step 1: Add to `Core.php`**

In `load_dependencies()`, after the `LinkAnalysis` require line:

```php
require_once BRE_DIR . 'includes/Features/LinkSuggest.php';
require_once BRE_DIR . 'includes/Admin/LinkSuggestPage.php';
```

In `register_hooks()`, after `( new Admin\LinkAnalysis() )->register();`:

```php
( new Features\LinkSuggest() )->register();

if ( is_admin() ) {
    // already inside is_admin block — add:
    ( new Admin\LinkSuggestPage() )->register();
}
```

> **Note:** The `is_admin()` block already exists. Add `LinkSuggestPage` inside it alongside the other page registrations. Add `LinkSuggest` registration outside the block (it registers front-facing hooks via `add_meta_box` which needs `is_admin` too — move inside the block).

Correct final form in `register_hooks()`:

```php
( new Features\MetaGenerator() )->register();
( new Features\SchemaEnhancer() )->register();
( new Features\LlmsTxt() )->register();
( new Features\RobotsTxt() )->register();
( new Features\CrawlerLog() )->register();
( new Features\GeoBlock() )->register();

if ( is_admin() ) {
    $menu = new Admin\AdminMenu();
    $menu->register();
    ( new Admin\ProviderPage() )->register();
    ( new Admin\MetaPage() )->register();
    ( new Admin\BulkPage() )->register();
    ( new Admin\TxtPage() )->register();
    ( new Admin\MetaEditorBox() )->register();
    ( new Admin\SeoWidget() )->register();
    ( new Admin\LinkAnalysis() )->register();
    ( new Features\LinkSuggest() )->register();      // ← NEW
    ( new Admin\LinkSuggestPage() )->register();     // ← NEW
    ( new Admin\GeoPage() )->register();
    ( new Admin\GeoEditorBox() )->register();
    ( new Admin\SchemaMetaBox() )->register();
    ( new Admin\SchemaPage() )->register();
}
```

**Step 2: Add menu entry to `AdminMenu.php`**

In `add_menus()`, after the `bre-bulk` submenu block and before `bre-geo`:

```php
add_submenu_page(
    'bavarian-rank',
    __( 'Link Suggestions', 'bavarian-rank-engine' ),
    __( 'Link Suggestions', 'bavarian-rank-engine' ),
    'manage_options',
    'bre-link-suggest',
    array( new LinkSuggestPage(), 'render' )
);
```

Add the `use` at the top if not already using FQCN:
The file uses `new BulkPage()` without namespace prefix because it's inside `namespace BavarianRankEngine\Admin` — `LinkSuggestPage` is the same namespace, so `new LinkSuggestPage()` works directly.

**Step 3: Run full test suite**

```bash
php composer.phar exec phpunit
```

Expected: all tests pass (new tests + existing).

**Step 4: Commit**

```bash
git add includes/Core.php includes/Admin/AdminMenu.php
git commit -m "feat: register LinkSuggest + LinkSuggestPage in Core and AdminMenu"
```

---

## Task 7: Localization

Add all new strings to both `.po` files and the `.pot` template.

**Files:**
- Modify: `languages/bavarian-rank-engine.pot`
- Modify: `languages/bavarian-rank-engine-de_DE.po`
- Modify: `languages/bavarian-rank-engine-en_US.po`

---

**Step 1: Add strings to `.pot`**

Append to `bavarian-rank-engine.pot`:

```
#: includes/Admin/AdminMenu.php
msgid "Link Suggestions"
msgstr ""

#: includes/Admin/views/link-suggest-settings.php
msgid "Analysis Trigger"
msgstr ""

msgid "When to analyse"
msgstr ""

msgid "Manual only (button)"
msgstr ""

msgid "On post save"
msgstr ""

msgid "Every"
msgstr ""

msgid "minutes"
msgstr ""

msgid "Exclude Posts / Pages"
msgstr ""

msgid "These posts will never appear as link suggestions (e.g. Imprint, Contact, Terms)."
msgstr ""

msgid "Excluded"
msgstr ""

msgid "Search posts…"
msgstr ""

msgid "Remove"
msgstr ""

msgid "Prioritise Posts / Pages"
msgstr ""

msgid "Boosted posts rank higher when thematically relevant. A boost of 1.0 = no change."
msgstr ""

msgid "Boosted"
msgstr ""

msgid "Boost:"
msgstr ""

msgid "AI Options (optional)"
msgstr ""

msgid "AI is connected — these settings control how many candidates are sent for semantic analysis."
msgstr ""

msgid "Candidates to AI"
msgstr ""

msgid "How many pre-scored candidates are passed to the AI (max 50)."
msgstr ""

msgid "Max output tokens"
msgstr ""

#: includes/Admin/views/link-suggest-box.php
msgid "Click Analyse to find internal link opportunities."
msgstr ""

msgid "Analyse"
msgstr ""

msgid "All"
msgstr ""

msgid "None"
msgstr ""

msgid "Apply (0 links)"
msgstr ""

#: includes/Features/LinkSuggest.php
msgid "Internal Link Suggestions (BRE)"
msgstr ""

msgid "Analysing…"
msgstr ""

msgid "No suggestions found."
msgstr ""

msgid "Apply (%d links)"
msgstr ""

msgid "Preview"
msgstr ""

msgid "Confirm"
msgstr ""

msgid "Cancel"
msgstr ""

msgid "Applied — %d links set ✓"
msgstr ""

msgid "Prioritised"
msgstr ""

msgid "Open post"
msgstr ""
```

**Step 2: Add German translations to `de_DE.po`**

Append the same msgid blocks with German msgstr values:

```
msgid "Link Suggestions"
msgstr "Link-Vorschläge"

msgid "Analysis Trigger"
msgstr "Analyse-Auslöser"

msgid "When to analyse"
msgstr "Wann analysieren"

msgid "Manual only (button)"
msgstr "Nur manuell (Button)"

msgid "On post save"
msgstr "Beim Speichern"

msgid "Every"
msgstr "Alle"

msgid "minutes"
msgstr "Minuten"

msgid "Exclude Posts / Pages"
msgstr "Beiträge / Seiten ausschließen"

msgid "These posts will never appear as link suggestions (e.g. Imprint, Contact, Terms)."
msgstr "Diese Beiträge erscheinen nie als Link-Vorschläge (z.B. Impressum, Kontakt, AGB)."

msgid "Excluded"
msgstr "Ausgeschlossen"

msgid "Search posts…"
msgstr "Beitrag suchen…"

msgid "Remove"
msgstr "Entfernen"

msgid "Prioritise Posts / Pages"
msgstr "Beiträge / Seiten priorisieren"

msgid "Boosted posts rank higher when thematically relevant. A boost of 1.0 = no change."
msgstr "Priorisierte Beiträge werden höher gerankt wenn thematisch passend. Boost 1.0 = keine Änderung."

msgid "Boosted"
msgstr "Priorisiert"

msgid "Boost:"
msgstr "Boost:"

msgid "AI Options (optional)"
msgstr "KI-Optionen (optional)"

msgid "AI is connected — these settings control how many candidates are sent for semantic analysis."
msgstr "KI ist verbunden — diese Einstellungen steuern wie viele Kandidaten zur semantischen Analyse gesendet werden."

msgid "Candidates to AI"
msgstr "Kandidaten an KI"

msgid "How many pre-scored candidates are passed to the AI (max 50)."
msgstr "Wie viele vorgewertete Kandidaten an die KI übergeben werden (max. 50)."

msgid "Max output tokens"
msgstr "Max. Output-Tokens"

msgid "Click Analyse to find internal link opportunities."
msgstr "Auf Analysieren klicken um interne Link-Möglichkeiten zu finden."

msgid "Analyse"
msgstr "Analysieren"

msgid "All"
msgstr "Alle"

msgid "None"
msgstr "Keine"

msgid "Apply (0 links)"
msgstr "Übernehmen (0 Links)"

msgid "Internal Link Suggestions (BRE)"
msgstr "Interne Link-Vorschläge (BRE)"

msgid "Analysing…"
msgstr "Analysiere…"

msgid "No suggestions found."
msgstr "Keine Vorschläge gefunden."

msgid "Apply (%d links)"
msgstr "Übernehmen (%d Links)"

msgid "Preview"
msgstr "Vorschau"

msgid "Confirm"
msgstr "Bestätigen"

msgid "Cancel"
msgstr "Abbrechen"

msgid "Applied — %d links set ✓"
msgstr "Angewendet — %d Links gesetzt ✓"

msgid "Prioritised"
msgstr "Priorisiert"

msgid "Open post"
msgstr "Beitrag öffnen"
```

**Step 3: Compile `.mo` file**

```bash
msgfmt /var/www/plugins/bre/bre-dev/languages/bavarian-rank-engine-de_DE.po \
       -o /var/www/plugins/bre/bre-dev/languages/bavarian-rank-engine-de_DE.mo
```

**Step 4: Add English strings to `en_US.po`**

Same msgid blocks, msgstr values identical to msgid (English = source language).

**Step 5: Commit**

```bash
git add languages/
git commit -m "feat: localization strings for internal link suggestions (de/en)"
```

---

## Task 8: PHPCS + final checks

**Step 1: Run PHPCS on new files**

```bash
php composer.phar exec phpcs -- --standard=WordPress \
    includes/Features/LinkSuggest.php \
    includes/Admin/LinkSuggestPage.php \
    includes/Admin/views/link-suggest-settings.php \
    includes/Admin/views/link-suggest-box.php
```

Fix any reported violations. Common ones: missing `// phpcs:ignore` on direct DB queries, variable naming.

**Step 2: Run full PHPUnit suite**

```bash
php composer.phar exec phpunit
```

Expected: all tests pass, no regressions.

**Step 3: Commit fixes (if any)**

```bash
git add -p
git commit -m "fix: PHPCS + PHPUnit cleanup for link suggestions feature"
```

---

## Task 9: Version bump + STATE.md

**Files:**
- Modify: `bavarian-rank-engine.php` — bump `BRE_VERSION` and plugin header
- Modify: `readme.txt` — stable tag + changelog entry
- Modify: `STATE.md`

**Step 1: Bump version**

In `bavarian-rank-engine.php`:
- Plugin header: `Version: 1.3.0`
- Constant: `define( 'BRE_VERSION', '1.3.0' );`

In `readme.txt`:
```
Stable tag: 1.3.0

== Changelog ==

= 1.3.0 =
* New: Internal Link Suggestions — context-aware suggestions while writing
* New: Link Suggestions settings page (trigger, exclude, boost, AI options)
* Fix: Bulk Generator no longer shows AI provider when no provider is connected
```

**Step 2: Update STATE.md**

```bash
git add bavarian-rank-engine.php readme.txt STATE.md
git commit -m "release: v1.3.0 — internal link suggestions"
```

---

## Implementation Order Summary

```
Task 1 → Core matching (pure PHP, fully tested)
Task 2 → WP candidate pool + AJAX handler
Task 3 → Settings page PHP + view
Task 4 → Editor meta box view
Task 5 → link-suggest.js
Task 6 → Wire Core + AdminMenu
Task 7 → Localization (de + en)
Task 8 → PHPCS + PHPUnit final run
Task 9 → Version bump + release commit
```

Each task is independently committable. Tasks 1–2 can be validated with `phpunit` alone, no WordPress install needed.
