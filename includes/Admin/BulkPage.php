<?php
namespace BavarianRankEngine\Admin;

use BavarianRankEngine\ProviderRegistry;

class BulkPage {
    public function register(): void {
        add_management_page(
            'GEO Bulk Meta',
            'GEO Bulk Meta',
            'manage_options',
            'bre-bulk',
            [ $this, 'render' ]
        );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'tools_page_bre-bulk' ) return;
        wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', [], BRE_VERSION );
        wp_enqueue_script( 'bre-bulk', BRE_URL . 'assets/bulk.js', [ 'jquery' ], BRE_VERSION, true );
        wp_localize_script( 'bre-bulk', 'breBulk', [
            'nonce'   => wp_create_nonce( 'bre_admin' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $settings  = SettingsPage::getSettings();
        $registry  = ProviderRegistry::instance();
        $providers = $registry->all();
        include BRE_DIR . 'includes/Admin/views/bulk.php';
    }
}
