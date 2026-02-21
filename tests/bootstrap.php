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

// Dynamic post meta stub for tests
$GLOBALS['bre_test_meta'] = [];
// Replace or add get_post_meta stub:
if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key = '', $single = false ) {
        $val = $GLOBALS['bre_test_meta'][ $post_id ][ $key ] ?? '';
        return $single ? $val : ( $val !== '' ? [ $val ] : [] );
    }
}

$GLOBALS['bre_transients'] = [];
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiry = 0 ) {
        $GLOBALS['bre_transients'][ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        return $GLOBALS['bre_transients'][ $key ] ?? false;
    }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) {
        unset( $GLOBALS['bre_transients'][ $key ] );
        return true;
    }
}

if ( ! function_exists( 'delete_post_meta' ) ) {
    function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) { return true; }
}
if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
        $GLOBALS['bre_test_meta'][ $post_id ][ $meta_key ] = $meta_value;
        return true;
    }
}
if ( ! function_exists( 'get_the_title' ) ) {
    function get_the_title( $post = 0 ) {
        if ( is_object( $post ) ) return $post->post_title ?? '';
        return $GLOBALS['bre_test_meta'][ $post ]['_title'] ?? 'Post ' . $post;
    }
}
if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
        return $post;
    }
}
if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) { return true; }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability, ...$args ) { return true; }
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null, $status_code = null, $flags = 0 ) {}
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null, $status_code = null, $flags = 0 ) {}
}
if ( ! function_exists( 'error_log' ) ) {
    function error_log( $message, $message_type = 0 ) {}
}
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) { return $text; }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) { return 'https://example.com' . $path; }
}
if ( ! function_exists( 'wp_reset_postdata' ) ) {
    function wp_reset_postdata() {}
}
if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $post = 0 ) {
        $id = is_object( $post ) ? $post->ID : $post;
        return 'https://example.com/?p=' . $id;
    }
}
if ( ! function_exists( 'get_the_date' ) ) {
    function get_the_date( $format = '', $post = null ) { return '2024-01-01'; }
}
if ( ! function_exists( 'status_header' ) ) {
    function status_header( $code ) {}
}

if ( ! function_exists( 'add_meta_box' ) ) {
    function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null ) {}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
        return '';
    }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action = -1 ) { return true; }
}
if ( ! function_exists( 'wp_is_post_autosave' ) ) {
    function wp_is_post_autosave( $post ) { return false; }
}
if ( ! function_exists( 'wp_is_post_revision' ) ) {
    function wp_is_post_revision( $post ) { return false; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) { return stripslashes_deep( $value ); }
}
if ( ! function_exists( 'stripslashes_deep' ) ) {
    function stripslashes_deep( $value ) {
        return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : stripslashes( $value );
    }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $in_footer = false ) {}
}
if ( ! function_exists( 'esc_textarea' ) ) {
    function esc_textarea( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) { return 'test_nonce'; }
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
}
