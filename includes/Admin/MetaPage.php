<?php
namespace BavarianRankEngine\Admin;

class MetaPage {
    public function register(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_settings(): void {
        register_setting( 'bre_meta', SettingsPage::OPTION_KEY_META, [
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'bavarian-rank_page_bre-meta' ) return;
        wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', [], BRE_VERSION );
        wp_enqueue_script( 'bre-admin', BRE_URL . 'assets/admin.js', [ 'jquery' ], BRE_VERSION, true );
        wp_localize_script( 'bre-admin', 'breAdmin', [
            'nonce'   => wp_create_nonce( 'bre_admin' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    public function sanitize( mixed $input ): array {
        $input = is_array( $input ) ? $input : [];
        $clean = [];

        $clean['meta_auto_enabled'] = ! empty( $input['meta_auto_enabled'] );
        $clean['token_mode']        = in_array( $input['token_mode'] ?? '', [ 'limit', 'full' ], true )
                                        ? $input['token_mode'] : 'limit';
        $clean['token_limit']       = max( 100, (int) ( $input['token_limit'] ?? 1000 ) );
        $clean['prompt']            = sanitize_textarea_field( $input['prompt'] ?? SettingsPage::getDefaultPrompt() );

        $all_post_types           = array_keys( get_post_types( [ 'public' => true ] ) );
        $clean['meta_post_types'] = array_values( array_intersect(
            array_map( 'sanitize_key', (array) ( $input['meta_post_types'] ?? [] ) ),
            $all_post_types
        ) );

        $schema_types            = [ 'organization', 'author', 'speakable', 'article_about', 'breadcrumb', 'ai_meta_tags' ];
        $clean['schema_enabled'] = array_values( array_intersect(
            array_map( 'sanitize_key', (array) ( $input['schema_enabled'] ?? [] ) ),
            $schema_types
        ) );

        $org_raw = $input['schema_same_as']['organization'] ?? '';
        if ( is_array( $org_raw ) ) {
            $org_raw = implode( "\n", $org_raw );
        }
        $clean['schema_same_as'] = [
            'organization' => array_values( array_filter( array_map( 'esc_url_raw',
                array_map( 'trim', explode( "\n", $org_raw ) )
            ) ) ),
        ];

        return $clean;
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $settings      = SettingsPage::getSettings();
        $post_types    = get_post_types( [ 'public' => true ], 'objects' );
        $schema_labels = [
            'organization'  => __( 'Organization (sameAs Social Profiles)', 'bavarian-rank-engine' ),
            'author'        => __( 'Author (sameAs Profile Links)', 'bavarian-rank-engine' ),
            'speakable'     => __( 'Speakable (for AI assistants)', 'bavarian-rank-engine' ),
            'article_about' => __( 'Article about/mentions', 'bavarian-rank-engine' ),
            'breadcrumb'    => __( 'BreadcrumbList', 'bavarian-rank-engine' ),
            'ai_meta_tags'  => __( 'AI-optimized Meta Tags (max-snippet etc.)', 'bavarian-rank-engine' ),
        ];
        include BRE_DIR . 'includes/Admin/views/meta.php';
    }
}
