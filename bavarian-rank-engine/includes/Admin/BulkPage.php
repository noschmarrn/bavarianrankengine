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
				'i18n'      => array(
					'lockWarning'      => __( 'A bulk process is already running', 'bavarian-rank-engine' ),
					'since'            => __( 'since', 'bavarian-rank-engine' ),
					'postsWithoutMeta' => __( 'Posts without meta description:', 'bavarian-rank-engine' ),
					'total'            => __( 'Total:', 'bavarian-rank-engine' ),
					'inputTokens'      => __( 'Input tokens', 'bavarian-rank-engine' ),
					'outputTokens'     => __( 'Output tokens', 'bavarian-rank-engine' ),
					'logStart'         => __( '▶ Start — max {limit} posts, Provider: {provider}', 'bavarian-rank-engine' ),
					'stopRequested'    => __( 'Stop requested…', 'bavarian-rank-engine' ),
					'logProcess'       => __( '↻ Processing {count} posts… ({remaining} remaining)', 'bavarian-rank-engine' ),
					'unknownError'     => __( 'Unknown error', 'bavarian-rank-engine' ),
					'attempt'          => __( 'attempt', 'bavarian-rank-engine' ),
					'networkError'     => __( 'Network error', 'bavarian-rank-engine' ),
					'processed'        => __( 'processed', 'bavarian-rank-engine' ),
					'done'             => __( '— Done —', 'bavarian-rank-engine' ),
					'postsFailed'      => __( 'posts failed:', 'bavarian-rank-engine' ),
				),
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
		$has_ai    = ! empty( $settings['ai_enabled'] )
					&& ! empty( $settings['api_keys'][ $settings['provider'] ] );
		include BRE_DIR . 'includes/Admin/views/bulk.php';
	}
}
