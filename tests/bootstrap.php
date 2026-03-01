<?php
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

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

$GLOBALS['bre_test_options'] = [];
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        return $GLOBALS['bre_test_options'][ $option ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        $GLOBALS['bre_test_options'][ $option ] = $value;
        return true;
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

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'settings_fields' ) ) {
    function settings_fields( $option_group ) {}
}
if ( ! function_exists( 'register_setting' ) ) {
    function register_setting( $option_group, $option_name, $args = [] ) {}
}
if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, $echo = true ) {
        $result = $checked == $current ? ' checked="checked"' : '';
        if ( $echo ) echo $result;
        return $result;
    }
}
if ( ! function_exists( 'submit_button' ) ) {
    function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {}
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook, $args = [] ) { return false; }
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $timestamp, $recurrence, $hook, $args = [] ) {}
}
if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) {
        return $type === 'timestamp' ? time() : date( 'Y-m-d H:i:s' );
    }
}

if ( ! function_exists( 'get_the_ID' ) ) {
    function get_the_ID() {
        return $GLOBALS['bre_current_post_id'] ?? 0;
    }
}
if ( ! function_exists( 'is_singular' ) ) {
    function is_singular( $post_types = '' ) {
        return $GLOBALS['bre_is_singular'] ?? false;
    }
}
if ( ! function_exists( 'get_post_type' ) ) {
    function get_post_type( $post = null ) {
        return $GLOBALS['bre_post_type'] ?? 'post';
    }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '', $filter = 'raw' ) {
        $map = [ 'name' => 'Test Blog', 'url' => 'https://example.com' ];
        return $map[ $show ] ?? '';
    }
}
if ( ! function_exists( 'get_the_author' ) ) {
    function get_the_author() {
        return $GLOBALS['bre_author_name'] ?? 'Test Author';
    }
}
if ( ! function_exists( 'get_the_author_meta' ) ) {
    function get_the_author_meta( $field, $user_id = 0 ) {
        return $GLOBALS['bre_author_meta'][ $field ] ?? '';
    }
}
if ( ! function_exists( 'get_author_posts_url' ) ) {
    function get_author_posts_url( $author_id, $author_nicename = '' ) {
        return 'https://example.com/author/' . $author_id;
    }
}
if ( ! function_exists( 'has_post_thumbnail' ) ) {
    function has_post_thumbnail( $post = null ) {
        return $GLOBALS['bre_has_thumbnail'] ?? false;
    }
}
if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
    function get_post_thumbnail_id( $post = null ) {
        return $GLOBALS['bre_thumbnail_id'] ?? 0;
    }
}
if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
    function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail', $icon = false ) {
        return $GLOBALS['bre_attachment_src'] ?? false;
    }
}
if ( ! function_exists( 'get_the_modified_date' ) ) {
    function get_the_modified_date( $format = '', $post = null ) {
        return '2024-06-01';
    }
}
if ( ! function_exists( 'get_the_excerpt' ) ) {
    function get_the_excerpt( $post = null ) {
        return '';
    }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $text, $remove_breaks = false ) {
        $text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
        $text = strip_tags( $text );
        if ( $remove_breaks ) {
            $text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
        }
        return trim( $text );
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) { return $data; }
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'get_locale' ) ) {
    function get_locale() { return $GLOBALS['bre_locale'] ?? 'en_US'; }
}
if ( ! function_exists( 'wp_get_post_terms' ) ) {
    function wp_get_post_terms( $post_id, $taxonomy, $args = [] ) {
        return $GLOBALS['bre_post_terms'][$post_id][$taxonomy] ?? [];
    }
}
if ( ! function_exists( 'update_object_term_cache' ) ) {
    function update_object_term_cache( $object_ids, $object_type ) { return true; }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( $handle, $name, $data ) {
        $GLOBALS['bre_localized'][$handle][$name] = $data;
    }
}
if ( ! defined( 'BRE_URL' ) ) {
    define( 'BRE_URL', 'https://example.com/wp-content/plugins/bre/' );
}
if ( ! defined( 'BRE_VERSION' ) ) {
    define( 'BRE_VERSION', '1.3.0' );
}
if ( ! defined( 'BRE_DIR' ) ) {
    define( 'BRE_DIR', dirname( __DIR__ ) . '/' );
}
