<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {
	public const OPTION_KEY_AI_FEATURES = 'bre_ai_features';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_bre_dismiss_welcome', array( $this, 'ajax_dismiss_welcome' ) );
		add_action( 'admin_post_bre_save_ai_features', array( $this, 'save_ai_features' ) );
	}

	public static function get_ai_features(): array {
		$defaults = array(
			'meta'  => false,
			'geo'   => false,
			'links' => false,
		);
		$saved = get_option( self::OPTION_KEY_AI_FEATURES, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array_merge( $defaults, $saved );
	}

	public function save_ai_features(): void {
		check_admin_referer( 'bre_save_ai_features' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$input = is_array( $_POST['bre_ai_features'] ?? null ) ? $_POST['bre_ai_features'] : array();
		// phpcs:enable

		update_option(
			self::OPTION_KEY_AI_FEATURES,
			array(
				'meta'  => ! empty( $input['meta'] ),
				'geo'   => ! empty( $input['geo'] ),
				'links' => ! empty( $input['links'] ),
			)
		);

		wp_safe_redirect( add_query_arg( array( 'page' => 'bavarian-rank', 'bre-saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'toplevel_page_bavarian-rank' ) {
			return;
		}
		wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', array(), BRE_VERSION );
		wp_enqueue_script( 'bre-admin', BRE_URL . 'assets/admin.js', array( 'jquery' ), BRE_VERSION, true );
		wp_localize_script(
			'bre-admin',
			'breAdmin',
			array(
				'nonce'        => wp_create_nonce( 'bre_admin' ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'testing'      => __( 'Testing…', 'bavarian-rank-engine' ),
				'networkError' => __( 'Network error', 'bavarian-rank-engine' ),
				'resetConfirm' => __( 'Really reset the prompt?', 'bavarian-rank-engine' ),
			)
		);
	}

	public function add_menus(): void {
		add_menu_page(
			__( 'Bavarian Rank Engine', 'bavarian-rank-engine' ),
			__( 'Bavarian Rank', 'bavarian-rank-engine' ),
			'manage_options',
			'bavarian-rank',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-area',
			80
		);

		// First submenu replaces the parent menu link
		add_submenu_page(
			'bavarian-rank',
			__( 'Dashboard', 'bavarian-rank-engine' ),
			__( 'Dashboard', 'bavarian-rank-engine' ),
			'manage_options',
			'bavarian-rank',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'bavarian-rank',
			__( 'AI Provider', 'bavarian-rank-engine' ),
			__( 'AI Provider', 'bavarian-rank-engine' ),
			'manage_options',
			'bre-provider',
			array( new ProviderPage(), 'render' )
		);

		add_submenu_page(
			'bavarian-rank',
			__( 'Meta Generator', 'bavarian-rank-engine' ),
			__( 'Meta Generator', 'bavarian-rank-engine' ),
			'manage_options',
			'bre-meta',
			array( new MetaPage(), 'render' )
		);

		add_submenu_page(
			'bavarian-rank',
			__( 'Schema.org', 'bavarian-rank-engine' ),
			__( 'Schema.org', 'bavarian-rank-engine' ),
			'manage_options',
			'bre-schema',
			array( new SchemaPage(), 'render' )
		);

		add_submenu_page(
			'bavarian-rank',
			__( 'TXT Files', 'bavarian-rank-engine' ),
			__( 'TXT Files', 'bavarian-rank-engine' ),
			'manage_options',
			'bre-txt',
			array( new TxtPage(), 'render' )
		);

		add_submenu_page(
			'bavarian-rank',
			__( 'Bulk Generator', 'bavarian-rank-engine' ),
			__( 'Bulk Generator', 'bavarian-rank-engine' ),
			'manage_options',
			'bre-bulk',
			array( new BulkPage(), 'render' )
		);

		add_submenu_page(
			'bavarian-rank',
			__( 'Link Suggestions', 'bavarian-rank-engine' ),
			__( 'Link Suggestions', 'bavarian-rank-engine' ),
			'manage_options',
			'bre-link-suggest',
			array( new LinkSuggestPage(), 'render' )
		);

		add_submenu_page(
			'bavarian-rank',
			__( 'GEO Quick Overview', 'bavarian-rank-engine' ),
			__( 'GEO Block', 'bavarian-rank-engine' ),
			'manage_options',
			'bre-geo',
			array( new GeoPage(), 'render' )
		);
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings     = SettingsPage::getSettings();
		$provider_key = $settings['provider'] ?? 'openai';
		$api_key      = $settings['api_keys'][ $provider_key ] ?? '';
		$ai_enabled   = $settings['ai_enabled'] ?? false;
		$has_ai       = $ai_enabled && ! empty( $api_key );

		if ( ! $ai_enabled ) {
			$provider = __( 'AI disabled', 'bavarian-rank-engine' );
		} elseif ( empty( $api_key ) ) {
			$provider = __( '— Not configured —', 'bavarian-rank-engine' );
		} else {
			$prov_obj = \BavarianRankEngine\ProviderRegistry::instance()->get( $provider_key );
			$provider = $prov_obj ? $prov_obj->getName() : $provider_key;
		}

		$post_types = $settings['meta_post_types'] ?? array( 'post', 'page' );
		$meta_stats = $this->get_meta_stats( $post_types );
		$bre_compat = $this->get_compat_info();

		$bre_show_welcome = $this->should_show_welcome();

		$usage_stats  = get_option( 'bre_usage_stats', array( 'tokens_in' => 0, 'tokens_out' => 0, 'count' => 0 ) );
		$model        = $settings['models'][ $provider_key ] ?? '';
		$costs_config = $settings['costs'][ $provider_key ][ $model ] ?? array();
		$cost_usd     = null;
		if ( ! empty( $costs_config['input'] ) || ! empty( $costs_config['output'] ) ) {
			$cost_usd = round(
				( (int) ( $usage_stats['tokens_in'] ?? 0 ) / 1_000_000 ) * (float) ( $costs_config['input'] ?? 0 )
				+ ( (int) ( $usage_stats['tokens_out'] ?? 0 ) / 1_000_000 ) * (float) ( $costs_config['output'] ?? 0 ),
				4
			);
		}

		$crawlers = get_transient( 'bre_crawler_summary' );
		if ( false === $crawlers ) {
			$crawlers = \BavarianRankEngine\Features\CrawlerLog::get_recent_summary( 30 );
			set_transient( 'bre_crawler_summary', $crawlers, 5 * MINUTE_IN_SECONDS );
		}

		$ai_features = self::get_ai_features();

		include BRE_DIR . 'includes/Admin/views/dashboard.php';
	}

	private function should_show_welcome(): bool {
		if ( get_user_meta( get_current_user_id(), 'bre_welcome_dismissed', true ) ) {
			return false;
		}
		$activated = (int) get_option( 'bre_first_activated', 0 );
		if ( ! $activated ) {
			// First admin visit on a legacy install — set timestamp now and show
			update_option( 'bre_first_activated', time() );
			return true;
		}
		return ( time() - $activated ) < DAY_IN_SECONDS;
	}

	public function ajax_dismiss_welcome(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		update_user_meta( get_current_user_id(), 'bre_welcome_dismissed', 1 );
		wp_send_json_success();
	}

	private function get_meta_stats( array $post_types ): array {
		$cache_key = 'bre_meta_stats';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$stats = array();
		foreach ( $post_types as $pt ) {
			$total     = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
					$pt
				)
			);
			$with_meta = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 AND pm.meta_key = %s AND pm.meta_value != ''",
					$pt,
					'_bre_meta_description'
				)
			);
			$stats[ $pt ] = array(
				'total'     => $total,
				'with_meta' => $with_meta,
				'pct'       => $total > 0 ? round( ( $with_meta / $total ) * 100 ) : 0,
			);
		}

		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
		return $stats;
	}

	private function get_compat_info(): array {
		$compat = array();
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$compat[] = array(
				'name'  => 'Rank Math',
				'notes' => array(
					__( 'llms.txt: BRE serves the file with priority — Rank Math is bypassed.', 'bavarian-rank-engine' ),
					__( 'Schema.org: BRE suppresses its own JSON-LD to avoid duplicates.', 'bavarian-rank-engine' ),
					__( 'Meta descriptions: BRE writes to the Rank Math meta field.', 'bavarian-rank-engine' ),
				),
			);
		}
		if ( defined( 'WPSEO_VERSION' ) ) {
			$compat[] = array(
				'name'  => 'Yoast SEO',
				'notes' => array(
					__( 'Schema.org: BRE suppresses its own JSON-LD to avoid duplicates.', 'bavarian-rank-engine' ),
					__( 'Meta descriptions: BRE writes to the Yoast meta field.', 'bavarian-rank-engine' ),
				),
			);
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$compat[] = array(
				'name'  => 'All in One SEO',
				'notes' => array(
					__( 'Meta descriptions: BRE writes to the AIOSEO meta field.', 'bavarian-rank-engine' ),
				),
			);
		}
		if ( class_exists( 'SeoPress_Titles_Admin' ) ) {
			$compat[] = array(
				'name'  => 'SEOPress',
				'notes' => array(
					__( 'Meta descriptions: BRE writes to the SEOPress meta field.', 'bavarian-rank-engine' ),
				),
			);
		}
		return $compat;
	}
}
