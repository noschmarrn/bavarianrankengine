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
