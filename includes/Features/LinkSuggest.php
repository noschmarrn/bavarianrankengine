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
			if ( strlen( $word ) <= 2 ) {
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
					$candidate = implode( ' ', $gram );
					// Verify the phrase exists outside existing <a> links.
					if ( self::phraseExistsOutsideLinks( $rawContent, $candidate ) ) {
						$bestScore  = $score;
						$bestPhrase = $candidate;
					}
				}
			}
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
