<?php
namespace BavarianRankEngine\Admin;

class AdminMenu {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menus' ] );
    }

    public function add_menus(): void {
        add_menu_page(
            __( 'Bavarian Rank Engine', 'bavarian-rank-engine' ),
            __( 'Bavarian Rank', 'bavarian-rank-engine' ),
            'manage_options',
            'bavarian-rank',
            [ $this, 'render_dashboard' ],
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
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'bavarian-rank',
            __( 'AI Provider', 'bavarian-rank-engine' ),
            __( 'AI Provider', 'bavarian-rank-engine' ),
            'manage_options',
            'bre-provider',
            [ new ProviderPage(), 'render' ]
        );

        add_submenu_page(
            'bavarian-rank',
            __( 'Meta Generator', 'bavarian-rank-engine' ),
            __( 'Meta Generator', 'bavarian-rank-engine' ),
            'manage_options',
            'bre-meta',
            [ new MetaPage(), 'render' ]
        );

        add_submenu_page(
            'bavarian-rank',
            'llms.txt',
            'llms.txt',
            'manage_options',
            'bre-llms',
            [ new LlmsPage(), 'render' ]
        );

        add_submenu_page(
            'bavarian-rank',
            __( 'Bulk Generator', 'bavarian-rank-engine' ),
            __( 'Bulk Generator', 'bavarian-rank-engine' ),
            'manage_options',
            'bre-bulk',
            [ new BulkPage(), 'render' ]
        );

        add_submenu_page(
            'bavarian-rank',
            __( 'robots.txt / AI Bots', 'bavarian-rank-engine' ),
            __( 'robots.txt', 'bavarian-rank-engine' ),
            'manage_options',
            'bre-robots',
            [ new RobotsPage(), 'render' ]
        );
    }

    public function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $settings   = SettingsPage::getSettings();
        $provider   = $settings['provider'] ?? 'openai';
        $post_types = $settings['meta_post_types'] ?? [ 'post', 'page' ];
        $meta_stats = $this->get_meta_stats( $post_types );

        include BRE_DIR . 'includes/Admin/views/dashboard.php';
    }

    private function get_meta_stats( array $post_types ): array {
        global $wpdb;
        $stats = [];
        foreach ( $post_types as $pt ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                $pt
            ) );
            $with_meta = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 AND pm.meta_key = %s AND pm.meta_value != ''",
                $pt,
                '_bre_meta_description'
            ) );
            $stats[ $pt ] = [
                'total'     => $total,
                'with_meta' => $with_meta,
                'pct'       => $total > 0 ? round( ( $with_meta / $total ) * 100 ) : 0,
            ];
        }
        return $stats;
    }
}
