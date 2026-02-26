<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LinkAnalysis {
	private const CACHE_KEY = 'bre_link_analysis';
	private const CACHE_TTL = 3600;

	public function register(): void {
		add_action( 'wp_ajax_bre_link_analysis', array( $this, 'ajax_analyse' ) );
	}

	public function ajax_analyse(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( $cached !== false ) {
			wp_send_json_success( $cached );
			return;
		}

		$threshold = (int) get_option( 'bre_ext_link_threshold', 5 );

		$data = array(
			'no_internal_links' => $this->posts_without_internal_links(),
			'too_many_external' => $this->posts_with_many_external_links( $threshold ),
			'pillar_pages'      => $this->top_pillar_pages( 5 ),
			'threshold'         => $threshold,
		);

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		wp_send_json_success( $data );
	}

	private function posts_without_internal_links(): array {
		global $wpdb;
		$site    = esc_sql( rtrim( home_url(), '/' ) );
		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type IN ('post','page')
                   AND post_content NOT LIKE %s
                 ORDER BY post_date DESC
                 LIMIT 20",
				'%href="' . $site . '%'
			)
		);
		return array_map(
			fn( $r ) => array(
				'id'    => (int) $r->ID,
				'title' => $r->post_title,
			),
			$results
		);
	}

	private function posts_with_many_external_links( int $threshold ): array {
		global $wpdb;
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT ID, post_title, post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ('post','page')
             ORDER BY post_date DESC LIMIT 200"
		);
		$over  = array();
		foreach ( $posts as $post ) {
			preg_match_all( '/href="https?:\/\/([^"\/]+)/', $post->post_content, $m );
			$external = array_filter( $m[1], fn( $h ) => $h !== $host );
			$count    = count( $external );
			if ( $count >= $threshold ) {
				$over[] = array(
					'id'    => (int) $post->ID,
					'title' => $post->post_title,
					'count' => $count,
				);
			}
		}
		usort( $over, fn( $a, $b ) => $b['count'] <=> $a['count'] );
		return array_slice( $over, 0, 20 );
	}

	private function top_pillar_pages( int $top ): array {
		global $wpdb;
		$site   = rtrim( home_url(), '/' );
		$posts  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ('post','page')"
		);
		$counts = array();
		foreach ( $posts as $post ) {
			preg_match_all(
				'/href="(' . preg_quote( $site, '/' ) . '[^"]+)"/',
				$post->post_content,
				$m
			);
			foreach ( $m[1] as $url ) {
				$url            = rtrim( $url, '/' );
				$counts[ $url ] = ( $counts[ $url ] ?? 0 ) + 1;
			}
		}
		arsort( $counts );
		$result = array();
		foreach ( array_slice( $counts, 0, $top, true ) as $url => $count ) {
			$result[] = array(
				'url'   => $url,
				'count' => $count,
			);
		}
		return $result;
	}
}
