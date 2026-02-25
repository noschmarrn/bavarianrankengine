<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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
				'nonce'   => wp_create_nonce( 'bre_admin' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
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
			'llms.txt',
			'llms.txt',
			'manage_options',
			'bre-llms',
			array( new LlmsPage(), 'render' )
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
			__( 'robots.txt / AI Bots', 'bavarian-rank-engine' ),
			__( 'robots.txt', 'bavarian-rank-engine' ),
			'manage_options',
			'bre-robots',
			array( new RobotsPage(), 'render' )
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

		$settings   = SettingsPage::getSettings();
		$provider   = $settings['provider'] ?? 'openai';
		$post_types = $settings['meta_post_types'] ?? array( 'post', 'page' );
		$meta_stats = $this->get_meta_stats( $post_types );
		$bre_compat = $this->get_compat_info();

		include BRE_DIR . 'includes/Admin/views/dashboard.php';
	}

	private function get_meta_stats( array $post_types ): array {
		global $wpdb;
		$stats = array();
		foreach ( $post_types as $pt ) {
			$total        = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
					$pt
				)
			);
			$with_meta    = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
