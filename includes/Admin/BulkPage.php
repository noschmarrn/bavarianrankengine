<?php
namespace SeoGeo\Admin;

use SeoGeo\ProviderRegistry;

class BulkPage {
    public function register(): void {
        add_management_page(
            'GEO Bulk Meta',
            'GEO Bulk Meta',
            'manage_options',
            'seo-geo-bulk',
            [ $this, 'render' ]
        );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'tools_page_seo-geo-bulk' ) return;
        wp_enqueue_style( 'seo-geo-admin', SEO_GEO_URL . 'assets/admin.css', [], SEO_GEO_VERSION );
        wp_enqueue_script( 'seo-geo-bulk', SEO_GEO_URL . 'assets/bulk.js', [ 'jquery' ], SEO_GEO_VERSION, true );
        wp_localize_script( 'seo-geo-bulk', 'seoGeoBulk', [
            'nonce'   => wp_create_nonce( 'seo_geo_admin' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $settings  = SettingsPage::getSettings();
        $registry  = ProviderRegistry::instance();
        $providers = $registry->all();
        include SEO_GEO_DIR . 'includes/Admin/views/bulk.php';
    }
}
