<?php
namespace BavarianRankEngine\Admin;

use BavarianRankEngine\Features\LlmsTxt;

class LlmsPage {
    public function register(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_settings(): void {
        register_setting( 'bre_llms', 'bre_llms_settings', [
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'bavarian-rank_page_bre-llms' ) return;
        wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', [], BRE_VERSION );
    }

    public function sanitize( mixed $input ): array {
        $input = is_array( $input ) ? $input : [];
        $clean = [];

        $clean['enabled']            = ! empty( $input['enabled'] );
        $clean['title']              = sanitize_text_field( $input['title'] ?? '' );
        $clean['description_before'] = sanitize_textarea_field( $input['description_before'] ?? '' );
        $clean['description_after']  = sanitize_textarea_field( $input['description_after'] ?? '' );
        $clean['description_footer'] = sanitize_textarea_field( $input['description_footer'] ?? '' );
        $clean['custom_links']       = sanitize_textarea_field( $input['custom_links'] ?? '' );

        $all_post_types      = array_keys( get_post_types( [ 'public' => true ] ) );
        $clean['post_types'] = array_values( array_intersect(
            array_map( 'sanitize_key', (array) ( $input['post_types'] ?? [] ) ),
            $all_post_types
        ) );

        return $clean;
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $settings = LlmsTxt::getSettings();
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $llms_url   = home_url( '/llms.txt' );
        include BRE_DIR . 'includes/Admin/views/llms.php';
    }
}
