<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\ProviderRegistry;

class BulkPage {
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'bavarian-rank_page_bre-bulk' ) {
			return;
		}
		wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', array(), BRE_VERSION );
		wp_enqueue_script( 'bre-bulk', BRE_URL . 'assets/bulk.js', array( 'jquery' ), BRE_VERSION, true );
		$settings = SettingsPage::getSettings();
		wp_localize_script(
			'bre-bulk',
			'breBulk',
			array(
				'nonce'     => wp_create_nonce( 'bre_admin' ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'isLocked'  => \BavarianRankEngine\Helpers\BulkQueue::isLocked(),
				'lockAge'   => \BavarianRankEngine\Helpers\BulkQueue::lockAge(),
				'rateDelay' => 6000,
				'costs'     => $settings['costs'] ?? array(),
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings  = SettingsPage::getSettings();
		$registry  = ProviderRegistry::instance();
		$providers = $registry->all();
		include BRE_DIR . 'includes/Admin/views/bulk.php';
	}
}
