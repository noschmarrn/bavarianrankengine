<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\Features\LlmsTxt;

class LlmsPage {
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_bre_llms_clear_cache', array( $this, 'ajax_clear_cache' ) );
	}

	public function register_settings(): void {
		register_setting(
			'bre_llms',
			'bre_llms_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'bavarian-rank_page_bre-llms' ) {
			return;
		}
		wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', array(), BRE_VERSION );
	}

	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		$clean['enabled']            = ! empty( $input['enabled'] );
		$clean['title']              = sanitize_text_field( $input['title'] ?? '' );
		$clean['description_before'] = sanitize_textarea_field( $input['description_before'] ?? '' );
		$clean['description_after']  = sanitize_textarea_field( $input['description_after'] ?? '' );
		$clean['description_footer'] = sanitize_textarea_field( $input['description_footer'] ?? '' );
		$clean['custom_links']       = sanitize_textarea_field( $input['custom_links'] ?? '' );

		$all_post_types      = array_keys( get_post_types( array( 'public' => true ) ) );
		$clean['post_types'] = array_values(
			array_intersect(
				array_map( 'sanitize_key', (array) ( $input['post_types'] ?? array() ) ),
				$all_post_types
			)
		);

		$clean['max_links'] = max( 50, (int) ( $input['max_links'] ?? 500 ) );

		LlmsTxt::clear_cache();

		return $clean;
	}

	public function ajax_clear_cache(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		\BavarianRankEngine\Features\LlmsTxt::clear_cache();
		wp_send_json_success( __( 'Cache geleert.', 'bavarian-rank-engine' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings   = LlmsTxt::getSettings();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$llms_url   = home_url( '/llms.txt' );
		include BRE_DIR . 'includes/Admin/views/llms.php';
	}
}
