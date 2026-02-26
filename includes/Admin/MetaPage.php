<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MetaPage {
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_settings(): void {
		register_setting(
			'bre_meta',
			SettingsPage::OPTION_KEY_META,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'bavarian-rank_page_bre-meta' ) {
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

	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		$clean['meta_auto_enabled'] = ! empty( $input['meta_auto_enabled'] );
		$clean['token_mode']        = in_array( $input['token_mode'] ?? '', array( 'limit', 'full' ), true )
										? $input['token_mode'] : 'limit';
		$clean['token_limit']       = max( 100, (int) ( $input['token_limit'] ?? 1000 ) );
		$clean['prompt']            = sanitize_textarea_field( $input['prompt'] ?? SettingsPage::getDefaultPrompt() );

		$all_post_types           = array_keys( get_post_types( array( 'public' => true ) ) );
		$clean['meta_post_types'] = array_values(
			array_intersect(
				array_map( 'sanitize_key', (array) ( $input['meta_post_types'] ?? array() ) ),
				$all_post_types
			)
		);

		return $clean;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings   = SettingsPage::getSettings();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		include BRE_DIR . 'includes/Admin/views/meta.php';
	}
}
