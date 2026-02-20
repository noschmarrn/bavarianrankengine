<?php
namespace BavarianRankEngine\Features;

class LlmsTxt {
    private const OPTION_KEY = 'bre_llms_settings';

    public function register(): void {
        add_action( 'init',              [ $this, 'add_rewrite_rule' ] );
        add_filter( 'query_vars',        [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'serve' ] );
    }

    public function add_rewrite_rule(): void {
        add_rewrite_rule( '^llms\.txt$', 'index.php?bre_llms=1', 'top' );
    }

    public function add_query_var( array $vars ): array {
        $vars[] = 'bre_llms';
        return $vars;
    }

    public function serve(): void {
        if ( ! get_query_var( 'bre_llms' ) ) {
            return;
        }
        $settings = self::getSettings();
        if ( empty( $settings['enabled'] ) ) {
            status_header( 404 );
            exit;
        }
        header( 'Content-Type: text/plain; charset=utf-8' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->build( $settings );
        exit;
    }

    private function build( array $s ): string {
        $out = '';

        if ( ! empty( $s['title'] ) ) {
            $out .= '# ' . $s['title'] . "\n\n";
        }

        if ( ! empty( $s['description_before'] ) ) {
            $out .= trim( $s['description_before'] ) . "\n\n";
        }

        if ( ! empty( $s['custom_links'] ) ) {
            $out .= "## Featured Resources\n\n";
            foreach ( explode( "\n", trim( $s['custom_links'] ) ) as $line ) {
                $line = trim( $line );
                if ( $line !== '' ) {
                    $out .= $line . "\n";
                }
            }
            $out .= "\n";
        }

        $post_types = $s['post_types'] ?? [ 'post', 'page' ];
        if ( ! empty( $post_types ) ) {
            $out .= "## Content\n\n";
            $out .= $this->build_content_list( $post_types );
        }

        if ( ! empty( $s['description_after'] ) ) {
            $out .= "\n---\n" . trim( $s['description_after'] ) . "\n";
        }

        if ( ! empty( $s['description_footer'] ) ) {
            $out .= "\n---\n" . trim( $s['description_footer'] ) . "\n";
        }

        return $out;
    }

    private function build_content_list( array $post_types ): string {
        $args  = [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];
        $query = new \WP_Query( $args );
        $lines = [];
        foreach ( $query->posts as $post ) {
            $lines[] = sprintf(
                '- [%s](%s) — %s',
                $post->post_title,
                get_permalink( $post ),
                get_the_date( 'Y-m-d', $post )
            );
        }
        wp_reset_postdata();
        return empty( $lines ) ? '' : implode( "\n", $lines ) . "\n";
    }

    /**
     * Flush rewrite rules on activation.
     * Call this from your activation hook.
     */
    public function flush_rules(): void {
        $this->add_rewrite_rule();
        flush_rewrite_rules();
    }

    public static function getSettings(): array {
        $defaults = [
            'enabled'            => false,
            'title'              => '',
            'description_before' => '',
            'description_after'  => '',
            'description_footer' => '',
            'custom_links'       => '',
            'post_types'         => [ 'post', 'page' ],
        ];
        $saved = get_option( self::OPTION_KEY, [] );
        return array_merge( $defaults, is_array( $saved ) ? $saved : [] );
    }
}
