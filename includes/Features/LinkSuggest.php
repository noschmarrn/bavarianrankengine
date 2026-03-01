<?php
namespace BavarianRankEngine\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LinkSuggest — pure static matching helpers for internal link suggestions.
 *
 * All methods are side-effect-free and have no WordPress dependencies beyond
 * wp_strip_all_tags(), which is stubbed in the test bootstrap.
 */
class LinkSuggest {

	// -------------------------------------------------------------------------
	// Stop-word lists
	// -------------------------------------------------------------------------

	/** @var array<string,string[]> */
	private static array $stopWords = [
		'en' => [
			'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to',
			'for', 'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were',
			'be', 'been', 'has', 'have', 'had', 'do', 'does', 'did', 'will',
			'would', 'could', 'should', 'may', 'might', 'that', 'this',
			'these', 'those', 'it', 'its', 'as', 'up', 'out', 'over', 'so',
			'if', 'about', 'into', 'than', 'then', 'when', 'where', 'which',
			'who', 'not', 'no', 'can', 'he', 'she', 'we', 'you', 'they',
			'their', 'our', 'your', 'his', 'her', 'my',
		],
		'de' => [
			'der', 'die', 'das', 'ein', 'eine', 'und', 'oder', 'aber', 'in',
			'an', 'auf', 'zu', 'für', 'von', 'mit', 'bei', 'aus', 'nach',
			'über', 'unter', 'vor', 'ist', 'sind', 'war', 'waren', 'sein',
			'haben', 'hat', 'hatte', 'ich', 'du', 'er', 'sie', 'es', 'wir',
			'ihr', 'den', 'dem', 'des', 'einer', 'einem', 'nicht', 'auch',
			'noch', 'schon', 'so', 'wie', 'da', 'dann', 'wenn', 'als', 'um',
			'durch', 'am', 'im', 'beim',
		],
	];

	// -------------------------------------------------------------------------
	// Public static API
	// -------------------------------------------------------------------------

	/**
	 * Tokenize $text into a filtered array of lowercase content words.
	 *
	 * @param string $text  HTML or plain text to tokenize.
	 * @param string $lang  Language code ('en' or 'de'). Defaults to 'en'.
	 * @return string[]
	 */
	public static function tokenize( string $text, string $lang = 'en' ): array {
		// 1. Strip HTML (using the WP function, stubbed in tests).
		$plain = wp_strip_all_tags( $text );

		// 2. Lowercase.
		$plain = mb_strtolower( $plain, 'UTF-8' );

		// 3. Split on non-word characters (unicode-aware).
		$words = preg_split( '/\W+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) ) {
			return [];
		}

		// 4. Filter: remove stop words and tokens with strlen ≤ 2.
		$stopWords = self::$stopWords[ $lang ] ?? self::$stopWords['en'];
		$stopSet   = array_flip( $stopWords );

		$tokens = [];
		foreach ( $words as $word ) {
			if ( mb_strlen( $word, 'UTF-8' ) <= 2 ) {
				continue;
			}
			if ( isset( $stopSet[ $word ] ) ) {
				continue;
			}
			$tokens[] = $word;
		}

		return $tokens;
	}

	/**
	 * Score a candidate post against content tokens.
	 *
	 * @param string[] $contentTokens Tokens from the current page content.
	 * @param array{title_tokens: string[], tag_tokens: string[], cat_tokens: string[]} $candidate
	 * @return float
	 */
	public static function scoreCandidate( array $contentTokens, array $candidate ): float {
		$titleOverlap = self::overlap( $contentTokens, $candidate['title_tokens'] );
		$tagOverlap   = self::overlap( $contentTokens, $candidate['tag_tokens'] );
		$catOverlap   = self::overlap( $contentTokens, $candidate['cat_tokens'] );

		return ( $titleOverlap * 3.0 ) + ( $tagOverlap * 2.0 ) + ( $catOverlap * 1.0 );
	}

	/**
	 * Multiply a relevance score by a boost factor.
	 * A zero score stays zero (boost cannot manufacture relevance).
	 *
	 * @param float $score Base score.
	 * @param float $boost Multiplier.
	 * @return float
	 */
	public static function applyBoost( float $score, float $boost ): float {
		return $score * $boost;
	}

	/**
	 * Find the best N-gram phrase in $rawContent that overlaps with $titleTokens.
	 *
	 * @param string   $rawContent  HTML content to search within.
	 * @param string[] $titleTokens Lowercased tokens of the link target's title.
	 * @param int      $minLen      Minimum gram length (words). Default 2.
	 * @param int      $maxLen      Maximum gram length (words). Default 6.
	 * @return string Original-case phrase, or '' if no suitable match is found.
	 */
	public static function findBestPhrase(
		string $rawContent,
		array $titleTokens,
		int $minLen = 2,
		int $maxLen = 6
	): string {
		if ( empty( $titleTokens ) ) {
			return '';
		}

		// Strip existing <a>…</a> links from the search space so we do not
		// return text that is already hyperlinked.
		$stripped = preg_replace( '/<a\b[^>]*>.*?<\/a>/is', '', $rawContent );

		// Strip remaining HTML tags.
		$plain = wp_strip_all_tags( $stripped ?? '' );

		// Extract words preserving original case.
		if ( ! preg_match_all( '/\b[\wäöüÄÖÜß]+\b/u', $plain, $m ) ) {
			return '';
		}
		$words = $m[0];
		$total = count( $words );

		if ( $total === 0 ) {
			return '';
		}

		$titleSet  = array_flip( $titleTokens ); // O(1) lookup.
		$bestScore = -1.0;
		$bestPhrase = '';

		// Generate all N-grams between $minLen and $maxLen.
		for ( $len = $minLen; $len <= $maxLen; $len++ ) {
			for ( $i = 0; $i <= $total - $len; $i++ ) {
				$gram = array_slice( $words, $i, $len );

				// Count how many lowercased gram words appear in titleTokens.
				$shared = 0;
				foreach ( $gram as $gramWord ) {
					if ( isset( $titleSet[ mb_strtolower( $gramWord, 'UTF-8' ) ] ) ) {
						$shared++;
					}
				}

				if ( $shared === 0 ) {
					continue;
				}

				// Score: shared / len + len * 0.1  (rewards length + overlap).
				$score = ( $shared / $len ) + ( $len * 0.1 );

				if ( $score > $bestScore ) {
					$bestScore  = $score;
					$bestPhrase = implode( ' ', $gram );
				}
			}
		}

		// Verify the winning phrase exists outside existing <a> links (called once).
		if ( $bestPhrase !== '' && stripos( $plain, $bestPhrase ) === false ) {
			return '';
		}

		return $bestPhrase;
	}

	/**
	 * Remove candidates whose post_id appears in $excludedIds.
	 *
	 * @param array<int,array{post_id: int, ...}> $candidates
	 * @param int[]                               $excludedIds
	 * @return array<int,array{post_id: int, ...}>
	 */
	public static function filterExcluded( array $candidates, array $excludedIds ): array {
		$excludedSet = array_flip( $excludedIds );

		$filtered = array_filter(
			$candidates,
			static fn( array $c ): bool => ! isset( $excludedSet[ $c['post_id'] ] )
		);

		return array_values( $filtered );
	}

	// -------------------------------------------------------------------------
	// Settings key
	// -------------------------------------------------------------------------

	public const OPTION_KEY = 'bre_link_suggest_settings';

	// -------------------------------------------------------------------------
	// WP-dependent public methods
	// -------------------------------------------------------------------------

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

	public function ajax_suggest(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$content = wp_kses_post( wp_unslash( $_POST['post_content'] ?? '' ) );
		// phpcs:enable

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		if ( ! $post_id || ! $content ) {
			wp_send_json_success( [] );
			return;
		}

		$settings    = self::getSettings();
		$lang        = str_starts_with( get_locale(), 'de_' ) ? 'de' : 'en';
		$contentToks = self::tokenize( $content, $lang );

		if ( empty( $contentToks ) ) {
			wp_send_json_success( [] );
			return;
		}

		$pool     = $this->getCandidatePool( $post_id );
		$excluded = array_map( 'intval', $settings['excluded_posts'] );
		$pool     = self::filterExcluded( $pool, $excluded );
		$boostMap = self::buildBoostMap( $settings['boosted_posts'] );

		foreach ( $pool as &$candidate ) {
			$score               = self::scoreCandidate( $contentToks, $candidate );
			$boost               = $boostMap[ $candidate['post_id'] ] ?? 1.0;
			$candidate['score']  = self::applyBoost( $score, $boost );
			$candidate['boosted'] = isset( $boostMap[ $candidate['post_id'] ] );
		}
		unset( $candidate );

		$pool = array_filter( $pool, fn( $c ) => $c['score'] > 0.0 );
		usort( $pool, fn( $a, $b ) => $b['score'] <=> $a['score'] );
		$pool = array_slice( $pool, 0, 20 );

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

		if ( ! is_array( $posts ) ) {
			return [];
		}

		// Preload term cache for all post IDs in two queries (avoids N+1 problem).
		$post_ids = array_map( fn( $p ) => (int) $p->ID, $posts );
		update_object_term_cache( $post_ids, [ 'post_tag', 'category' ] );

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

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Compute the fraction of $candidate tokens that also appear in $content.
	 *
	 * @param string[] $content
	 * @param string[] $candidate
	 * @return float  0.0 if $candidate is empty.
	 */
	private static function overlap( array $content, array $candidate ): float {
		if ( empty( $candidate ) ) {
			return 0.0;
		}
		$shared = count( array_intersect( $candidate, $content ) );
		return $shared / count( $candidate );
	}

	/**
	 * Check that $phrase appears (case-insensitively) in $html outside <a> tags.
	 *
	 * @param string $html
	 * @param string $phrase
	 * @return bool
	 */
	private static function phraseExistsOutsideLinks( string $html, string $phrase ): bool {
		// Remove all <a>…</a> blocks then strip remaining tags.
		$stripped = preg_replace( '/<a\b[^>]*>.*?<\/a>/is', '', $html );
		$plain    = wp_strip_all_tags( $stripped ?? '' );
		return stripos( $plain, $phrase ) !== false;
	}
}
