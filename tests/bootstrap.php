<?php
/**
 * PHPUnit bootstrap: autoloader + minimal WordPress function stubs.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// WordPress function stubs — only defined if WordPress is not already loaded.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'esc_url_raw' ) ) {
    /**
     * Stub for WordPress esc_url_raw().
     * Strips whitespace and returns the URL unchanged for unit-test purposes.
     *
     * @param string $url
     * @return string
     */
    function esc_url_raw( string $url ): string {
        return trim( $url );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( $str ) );
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $str ) {
        $lines = explode( "\n", $str );
        return implode( "\n", array_map( 'sanitize_text_field', $lines ) );
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        return $default;
    }
}

if ( ! function_exists( 'get_post_types' ) ) {
    function get_post_types( $args = [], $output = 'names' ) {
        return [ 'post' => 'post', 'page' => 'page' ];
    }
}

if ( ! function_exists( 'add_settings_error' ) ) {
    function add_settings_error( ...$args ) {}
}
