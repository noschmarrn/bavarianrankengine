<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\Features\LinkSuggest;

class LinkSuggestPage {

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_settings(): void {
		register_setting(
			'bre_link_suggest',
			LinkSuggest::OPTION_KEY,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'bavarian-rank_page_bre-link-suggest' ) {
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

		// link-suggest.js enthält den Post-Such-Code für Exclude/Boost.
		// Minimales breLinkSuggest-Objekt — reicht damit der Such-Block läuft.
		wp_enqueue_script( 'bre-link-suggest', BRE_URL . 'assets/link-suggest.js', array( 'jquery' ), BRE_VERSION, true );
		wp_localize_script(
			'bre-link-suggest',
			'breLinkSuggest',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'restUrl'    => get_rest_url( null, 'wp/v2/search' ),
				'restNonce'  => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		$allowed_triggers       = array( 'manual', 'save', 'interval' );
		$clean['trigger']       = in_array( $input['trigger'] ?? '', $allowed_triggers, true )
									? $input['trigger'] : 'manual';
		$clean['interval_min']  = max( 1, min( 60, (int) ( $input['interval_min'] ?? 2 ) ) );
		$clean['ai_candidates'] = max( 1, min( 50, (int) ( $input['ai_candidates'] ?? 20 ) ) );
		$clean['ai_max_tokens'] = max( 100, min( 2000, (int) ( $input['ai_max_tokens'] ?? 400 ) ) );

		$clean['excluded_posts'] = array_values(
			array_map( 'intval', (array) ( $input['excluded_posts'] ?? array() ) )
		);

		$clean['boosted_posts'] = array();
		foreach ( (array) ( $input['boosted_posts'] ?? array() ) as $entry ) {
			$id    = (int) ( $entry['id'] ?? 0 );
			$boost = (float) ( $entry['boost'] ?? 1.5 );
			if ( $id > 0 ) {
				$clean['boosted_posts'][] = array(
					'id'    => $id,
					'boost' => max( 1.0, min( 10.0, $boost ) ),
				);
			}
		}

		return $clean;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = LinkSuggest::get_settings();
		$main     = SettingsPage::getSettings();
		$has_ai   = ! empty( $main['ai_enabled'] )
					&& ! empty( $main['api_keys'][ $main['provider'] ] );
		include BRE_DIR . 'includes/Admin/views/link-suggest-settings.php';
	}
}
