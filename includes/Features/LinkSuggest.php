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
	private static array $stop_words = array(
		'en' => array(
			'a',
			'an',
			'the',
			'and',
			'or',
			'but',
			'in',
			'on',
			'at',
			'to',
			'for',
			'of',
			'with',
			'by',
			'from',
			'is',
			'are',
			'was',
			'were',
			'be',
			'been',
			'has',
			'have',
			'had',
			'do',
			'does',
			'did',
			'will',
			'would',
			'could',
			'should',
			'may',
			'might',
			'that',
			'this',
			'these',
			'those',
			'it',
			'its',
			'as',
			'up',
			'out',
			'over',
			'so',
			'if',
			'about',
			'into',
			'than',
			'then',
			'when',
			'where',
			'which',
			'who',
			'not',
			'no',
			'can',
			'he',
			'she',
			'we',
			'you',
			'they',
			'their',
			'our',
			'your',
			'his',
			'her',
			'my',
		),
		'de' => array(
			'der',
			'die',
			'das',
			'ein',
			'eine',
			'und',
			'oder',
			'aber',
			'in',
			'an',
			'auf',
			'zu',
			'für',
			'von',
			'mit',
			'bei',
			'aus',
			'nach',
			'über',
			'unter',
			'vor',
			'ist',
			'sind',
			'war',
			'waren',
			'sein',
			'haben',
			'hat',
			'hatte',
			'ich',
			'du',
			'er',
			'sie',
			'es',
			'wir',
			'ihr',
			'den',
			'dem',
			'des',
			'einer',
			'einem',
			'nicht',
			'auch',
			'noch',
			'schon',
			'so',
			'wie',
			'da',
			'dann',
			'wenn',
			'als',
			'um',
			'durch',
			'am',
			'im',
			'beim',
		),
	);

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
			return array();
		}

		// 4. Filter: remove stop words and tokens with strlen ≤ 2.
		$stop_words = self::$stop_words[ $lang ] ?? self::$stop_words['en'];
		$stop_set   = array_flip( $stop_words );

		$tokens = array();
		foreach ( $words as $word ) {
			if ( mb_strlen( $word, 'UTF-8' ) <= 2 ) {
				continue;
			}
			if ( isset( $stop_set[ $word ] ) ) {
				continue;
			}
			$tokens[] = $word;
		}

		return $tokens;
	}

	/**
	 * Score a candidate post against content tokens.
	 *
	 * @param string[]                                                                  $content_tokens Tokens from the current page content.
	 * @param array{title_tokens: string[], tag_tokens: string[], cat_tokens: string[]} $candidate
	 * @return float
	 */
	public static function score_candidate( array $content_tokens, array $candidate ): float {
		$title_overlap = self::overlap( $content_tokens, $candidate['title_tokens'] );
		$tag_overlap   = self::overlap( $content_tokens, $candidate['tag_tokens'] );
		$cat_overlap   = self::overlap( $content_tokens, $candidate['cat_tokens'] );

		return ( $title_overlap * 3.0 ) + ( $tag_overlap * 2.0 ) + ( $cat_overlap * 1.0 );
	}

	/**
	 * Multiply a relevance score by a boost factor.
	 * A zero score stays zero (boost cannot manufacture relevance).
	 *
	 * @param float $score Base score.
	 * @param float $boost Multiplier.
	 * @return float
	 */
	public static function apply_boost( float $score, float $boost ): float {
		return $score * $boost;
	}

	/**
	 * Find the best N-gram phrase in $raw_content that overlaps with $title_tokens.
	 *
	 * @param string   $raw_content  HTML content to search within.
	 * @param string[] $title_tokens Lowercased tokens of the link target's title.
	 * @param int      $min_len      Minimum gram length (words). Default 2.
	 * @param int      $max_len      Maximum gram length (words). Default 6.
	 * @return string Original-case phrase, or '' if no suitable match is found.
	 */
	public static function find_best_phrase(
		string $raw_content,
		array $title_tokens,
		int $min_len = 2,
		int $max_len = 6
	): string {
		if ( empty( $title_tokens ) ) {
			return '';
		}

		// Strip existing <a>…</a> links from the search space so we do not
		// return text that is already hyperlinked.
		$stripped = preg_replace( '/<a\b[^>]*>.*?<\/a>/is', '', $raw_content );

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

		$title_set   = array_flip( $title_tokens ); // O(1) lookup.
		$best_score  = -1.0;
		$best_phrase = '';

		// Generate all N-grams between $min_len and $max_len.
		for ( $len = $min_len; $len <= $max_len; $len++ ) {
			for ( $i = 0; $i <= $total - $len; $i++ ) {
				$gram = array_slice( $words, $i, $len );

				// Count how many lowercased gram words appear in title_tokens.
				$shared = 0;
				foreach ( $gram as $gram_word ) {
					if ( isset( $title_set[ mb_strtolower( $gram_word, 'UTF-8' ) ] ) ) {
						++$shared;
					}
				}

				if ( $shared === 0 ) {
					continue;
				}

				// Score: shared / len + len * 0.1  (rewards length + overlap).
				$score = ( $shared / $len ) + ( $len * 0.1 );

				if ( $score > $best_score ) {
					$best_score  = $score;
					$best_phrase = implode( ' ', $gram );
				}
			}
		}

		// Verify the winning phrase exists outside existing <a> links (called once).
		if ( $best_phrase !== '' && stripos( $plain, $best_phrase ) === false ) {
			return '';
		}

		return $best_phrase;
	}

	/**
	 * Remove candidates whose post_id appears in $excluded_ids.
	 *
	 * @param array<int,array{post_id: int, ...}> $candidates
	 * @param int[]                         $excluded_ids
	 * @return array<int,array{post_id: int, ...}>
	 */
	public static function filter_excluded( array $candidates, array $excluded_ids ): array {
		$excluded_set = array_flip( $excluded_ids );

		$filtered = array_filter(
			$candidates,
			static fn( array $c ): bool => ! isset( $excluded_set[ $c['post_id'] ] )
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

	public static function get_settings(): array {
		$defaults = array(
			'trigger'        => 'manual',
			'interval_min'   => 2,
			'excluded_posts' => array(),
			'boosted_posts'  => array(),
			'ai_candidates'  => 20,
			'ai_max_tokens'  => 400,
		);
		$saved    = get_option( self::OPTION_KEY, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		return array_merge( $defaults, $saved );
	}

	public static function build_boost_map( array $boosted_posts ): array {
		$map = array();
		foreach ( $boosted_posts as $entry ) {
			$id    = (int) ( $entry['id'] ?? 0 );
			$boost = (float) ( $entry['boost'] ?? 1.0 );
			if ( $id > 0 ) {
				$map[ $id ] = max( 1.0, $boost );
			}
		}
		return $map;
	}

	public function register(): void {
		add_action( 'wp_ajax_bre_link_suggestions', array( $this, 'ajax_suggest' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'save_post', array( $this, 'invalidate_cache' ) );
	}

	public function invalidate_cache(): void {
		delete_transient( 'bre_link_candidate_pool' );
	}

	public function add_meta_box(): void {
		$post_types = \BavarianRankEngine\Admin\SettingsPage::getSettings()['meta_post_types'] ?? array( 'post', 'page' );
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'bre_link_suggest',
				__( 'Internal Link Suggestions (BRE)', 'bavarian-rank-engine' ),
				array( $this, 'render_meta_box' ),
				$pt,
				'normal',
				'default'
			);
		}
	}

	public function render_meta_box( \WP_Post $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		include BRE_DIR . 'includes/Admin/views/link-suggest-box.php';
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$settings = self::get_settings();
		$lang     = str_starts_with( get_locale(), 'de_' ) ? 'de' : 'en';
		wp_enqueue_script( 'bre-link-suggest', BRE_URL . 'assets/link-suggest.js', array( 'jquery' ), BRE_VERSION, true );
		global $post;
		wp_localize_script(
			'bre-link-suggest',
			'breLinkSuggest',
			array(
				'nonce'       => wp_create_nonce( 'bre_admin' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'postId'      => $post ? (int) $post->ID : 0,
				'triggerMode' => $settings['trigger'],
				'intervalMs'  => max( 1, (int) $settings['interval_min'] ) * 60000,
				'lang'        => $lang,
				'i18n'        => array(
					'title'        => __( 'Internal Link Suggestions (BRE)', 'bavarian-rank-engine' ),
					'analyse'      => __( 'Analyse', 'bavarian-rank-engine' ),
					'loading'      => __( 'Analysing…', 'bavarian-rank-engine' ),
					'noResults'    => __( 'No suggestions found.', 'bavarian-rank-engine' ),
					/* translators: %d: number of links */
					'applyBtn'     => __( 'Apply (%d links)', 'bavarian-rank-engine' ),
					'selectAll'    => __( 'All', 'bavarian-rank-engine' ),
					'selectNone'   => __( 'None', 'bavarian-rank-engine' ),
					'preview'      => __( 'Preview', 'bavarian-rank-engine' ),
					'confirm'      => __( 'Confirm', 'bavarian-rank-engine' ),
					'cancel'       => __( 'Cancel', 'bavarian-rank-engine' ),
					/* translators: %d: number of links */
					'applied'      => __( 'Applied — %d links set ✓', 'bavarian-rank-engine' ),
					'boosted'      => __( 'Prioritised', 'bavarian-rank-engine' ),
					'openPost'     => __( 'Open post', 'bavarian-rank-engine' ),
					'networkError' => __( 'Network error', 'bavarian-rank-engine' ),
				),
			)
		);
	}

	public function ajax_suggest(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id = (int) ( wp_unslash( $_POST['post_id'] ?? 0 ) );
		$content = wp_kses_post( wp_unslash( $_POST['post_content'] ?? '' ) );
		// phpcs:enable

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		if ( ! $post_id || ! $content ) {
			wp_send_json_success( array() );
			return;
		}

		$settings     = self::get_settings();
		$lang         = str_starts_with( get_locale(), 'de_' ) ? 'de' : 'en';
		$content_toks = self::tokenize( $content, $lang );

		if ( empty( $content_toks ) ) {
			wp_send_json_success( array() );
			return;
		}

		$pool      = $this->get_candidate_pool( $post_id );
		$excluded  = array_map( 'intval', $settings['excluded_posts'] );
		$pool      = self::filter_excluded( $pool, $excluded );
		$boost_map = self::build_boost_map( $settings['boosted_posts'] );

		foreach ( $pool as &$candidate ) {
			$score                = self::score_candidate( $content_toks, $candidate );
			$boost                = $boost_map[ $candidate['post_id'] ] ?? 1.0;
			$candidate['score']   = self::apply_boost( $score, $boost );
			$candidate['boosted'] = isset( $boost_map[ $candidate['post_id'] ] );
		}
		unset( $candidate );

		$pool = array_filter( $pool, fn( $c ) => $c['score'] > 0.0 );
		usort( $pool, fn( $a, $b ) => $b['score'] <=> $a['score'] );
		$pool = array_slice( $pool, 0, 20 );

		$suggestions = array();
		foreach ( $pool as $candidate ) {
			$phrase = self::find_best_phrase( $content, $candidate['title_tokens'] );
			if ( $phrase === '' ) {
				continue;
			}
			$suggestions[] = array(
				'phrase'     => $phrase,
				'post_id'    => $candidate['post_id'],
				'post_title' => $candidate['post_title'],
				'url'        => $candidate['url'],
				'score'      => round( $candidate['score'], 3 ),
				'boosted'    => $candidate['boosted'],
			);
			if ( count( $suggestions ) >= 10 ) {
				break;
			}
		}

		wp_send_json_success( $suggestions );
	}

	private function get_candidate_pool( int $exclude_post_id ): array {
		$cached = get_transient( 'bre_link_candidate_pool' );
		if ( $cached !== false ) {
			return array_values( array_filter( $cached, fn( $c ) => $c['post_id'] !== $exclude_post_id ) );
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
			return array();
		}

		// Preload term cache for all post IDs in two queries (avoids N+1 problem).
		$post_ids = array_map( fn( $p ) => (int) $p->ID, $posts );
		update_object_term_cache( $post_ids, array( 'post_tag', 'category' ) );

		$pool = array();
		foreach ( $posts as $post ) {
			$tags = wp_get_post_terms( (int) $post->ID, 'post_tag', array( 'fields' => 'names' ) );
			$cats = wp_get_post_terms( (int) $post->ID, 'category', array( 'fields' => 'names' ) );

			$tag_str = is_array( $tags ) ? implode( ' ', $tags ) : '';
			$cat_str = is_array( $cats ) ? implode( ' ', $cats ) : '';

			$pool[] = array(
				'post_id'      => (int) $post->ID,
				'post_title'   => $post->post_title,
				'url'          => get_permalink( (int) $post->ID ),
				'title_tokens' => self::tokenize( $post->post_title, $lang ),
				'tag_tokens'   => self::tokenize( $tag_str, $lang ),
				'cat_tokens'   => self::tokenize( $cat_str, $lang ),
			);
		}

		set_transient( 'bre_link_candidate_pool', $pool, HOUR_IN_SECONDS );
		return array_values( array_filter( $pool, fn( $c ) => $c['post_id'] !== $exclude_post_id ) );
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
	private static function phrase_exists_outside_links( string $html, string $phrase ): bool {
		// Remove all <a>…</a> blocks then strip remaining tags.
		$stripped = preg_replace( '/<a\b[^>]*>.*?<\/a>/is', '', $html );
		$plain    = wp_strip_all_tags( $stripped ?? '' );
		return stripos( $plain, $phrase ) !== false;
	}
}
