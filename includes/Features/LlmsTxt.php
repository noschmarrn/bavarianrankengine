<?php
namespace BavarianRankEngine\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LlmsTxt {
	private const OPTION_KEY = 'bre_llms_settings';
	private const CACHE_KEY  = 'bre_llms_cache';

	public function register(): void {
		add_action( 'parse_request', array( $this, 'maybe_serve' ), 1 );
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'admin_notices', array( $this, 'rank_math_notice' ) );
	}

	public function maybe_serve(): void {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '?' ) : '';
		if ( $uri === '/llms.txt' ) {
			$this->serve_page( 1 );
			return;
		}
		if ( preg_match( '#^/llms-(\d+)\.txt$#', $uri, $m ) ) {
			$this->serve_page( (int) $m[1] );
		}
	}

	public function rank_math_notice(): void {
		if ( ! defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}
		$settings = self::getSettings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		echo '<div class="notice notice-info is-dismissible"><p>'
			. esc_html__( 'Bavarian Rank Engine bedient llms.txt mit Priorität — kein Handlungsbedarf bei Rank Math.', 'bavarian-rank-engine' )
			. '</p></div>';
	}

	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?bre_llms=1', 'top' );
	}

	public function add_query_var( array $vars ): array {
		$vars[] = 'bre_llms';
		return $vars;
	}

	private function serve_page( int $page ): void {
		$settings = self::getSettings();
		if ( empty( $settings['enabled'] ) ) {
			status_header( 404 );
			exit;
		}

		$cache_key = self::CACHE_KEY . '_p' . $page;
		$cached    = get_transient( $cache_key );

		if ( $cached === false ) {
			$cached = $this->build( $settings, $page );
			set_transient( $cache_key, $cached, 0 );
		}

		$etag          = '"' . md5( $cached ) . '"';
		$last_modified = $this->get_last_modified();

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
		header( 'Cache-Control: public, max-age=3600' );

		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		if ( $if_none_match === $etag ) {
			status_header( 304 );
			exit;
		}

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cached;
		exit;
	}

	private function get_last_modified(): int {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return time();
		}
		$latest = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT UNIX_TIMESTAMP(MAX(post_modified_gmt)) FROM {$wpdb->posts}
             WHERE post_status = 'publish'"
		);
		return $latest ? (int) $latest : time();
	}

	public static function clear_cache(): void {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return;
		}
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bre_llms_cache%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bre_llms_cache%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	}

	private function build( array $s, int $page = 1 ): string {
		$max_links  = max( 50, (int) ( $s['max_links'] ?? 500 ) );
		$post_types = $s['post_types'] ?? array( 'post', 'page' );
		$all_posts  = $this->get_all_posts( $post_types );
		$total      = count( $all_posts );
		$pages      = $total > 0 ? (int) ceil( $total / $max_links ) : 1;
		$offset     = ( $page - 1 ) * $max_links;
		$page_posts = array_slice( $all_posts, $offset, $max_links );

		$out = '';

		if ( $page === 1 ) {
			if ( ! empty( $s['title'] ) ) {
				$out .= '# ' . $s['title'] . "\n\n";
			}
			if ( ! empty( $s['description_before'] ) ) {
				$out .= trim( $s['description_before'] ) . "\n\n";
			}
			if ( ! empty( $s['custom_links'] ) ) {
				$out .= "## Featured Resources\n\n";
				foreach ( explode( "\n", trim( $s['custom_links'] ) ) as $line ) {
					$line = trim( $line );
					if ( $line !== '' ) {
						$out .= $line . "\n";
					}
				}
				$out .= "\n";
			}
		}

		if ( ! empty( $page_posts ) ) {
			$out .= "## Content\n\n";
			foreach ( $page_posts as $post ) {
				$out .= sprintf(
					'- [%s](%s) — %s',
					$post->post_title,
					get_permalink( $post ),
					get_the_date( 'Y-m-d', $post )
				) . "\n";
			}
			$out .= "\n";
		}

		if ( $pages > 1 ) {
			$out .= "## More\n\n";
			for ( $p = 1; $p <= $pages; $p++ ) {
				if ( $p === $page ) {
					continue;
				}
				$filename = $p === 1 ? 'llms.txt' : "llms-{$p}.txt";
				$url      = home_url( '/' . $filename );
				$out     .= "- [{$filename}]({$url})\n";
			}
			$out .= "\n";
		}

		if ( $page === 1 ) {
			if ( ! empty( $s['description_after'] ) ) {
				$out .= "\n---\n" . trim( $s['description_after'] ) . "\n";
			}
			if ( ! empty( $s['description_footer'] ) ) {
				$out .= "\n---\n" . trim( $s['description_footer'] ) . "\n";
			}
		}

		return $out;
	}

	private function get_all_posts( array $post_types ): array {
		if ( empty( $post_types ) ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$posts = $query->posts;
		wp_reset_postdata();
		return $posts;
	}

	/**
	 * Flush rewrite rules on activation.
	 * Call this from your activation hook.
	 */
	public function flush_rules(): void {
		$this->add_rewrite_rule();
		flush_rewrite_rules();
	}

	public static function getSettings(): array {
		$defaults = array(
			'enabled'            => false,
			'title'              => '',
			'description_before' => '',
			'description_after'  => '',
			'description_footer' => '',
			'custom_links'       => '',
			'post_types'         => array( 'post', 'page' ),
			'max_links'          => 500,
		);
		$saved    = get_option( self::OPTION_KEY, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}
}
