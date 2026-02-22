<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\Features\RobotsTxt;

class RobotsPage {
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting(
			'bre_robots',
			'bre_robots_settings',
			array( $this, 'sanitize' )
		);
	}

	public function sanitize( mixed $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$blocked = array_values(
			array_intersect(
				array_map( 'sanitize_text_field', (array) ( $input['blocked_bots'] ?? array() ) ),
				array_keys( RobotsTxt::KNOWN_BOTS )
			)
		);
		return array( 'blocked_bots' => $blocked );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = RobotsTxt::getSettings();
		include BRE_DIR . 'includes/Admin/views/robots.php';
	}
}
