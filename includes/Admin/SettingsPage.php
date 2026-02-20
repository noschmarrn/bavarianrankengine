<?php
namespace BavarianRankEngine\Admin;

use BavarianRankEngine\ProviderRegistry;
use BavarianRankEngine\Helpers\KeyVault;

class SettingsPage {
    private const OPTION_KEY = 'bre_settings';

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_bre_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_bre_get_default_prompt', [ $this, 'ajax_get_default_prompt' ] );
    }

    public function add_menu(): void {
        add_options_page(
            'Bavarian Rank Engine',
            'Bavarian Rank Engine',
            'manage_options',
            'bre-settings',
            [ $this, 'render' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'bre', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'settings_page_bre-settings' ) return;
        wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', [], BRE_VERSION );
        wp_enqueue_script( 'bre-admin', BRE_URL . 'assets/admin.js', [ 'jquery' ], BRE_VERSION, true );
        wp_localize_script( 'bre-admin', 'breAdmin', [
            'nonce'   => wp_create_nonce( 'bre_admin' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    public static function getSettings(): array {
        $defaults = [
            'provider'          => 'openai',
            'api_keys'          => [],
            'models'            => [],
            'meta_auto_enabled' => true,
            'meta_post_types'   => [ 'post', 'page' ],
            'token_mode'        => 'limit',
            'token_limit'       => 1000,
            'prompt'            => self::getDefaultPrompt(),
            'schema_enabled'    => [],
            'schema_same_as'    => [],
        ];
        $saved    = get_option( self::OPTION_KEY, [] );
        $settings = array_merge( $defaults, $saved );

        foreach ( $settings['api_keys'] as $id => $stored ) {
            $decrypted = KeyVault::decrypt( $stored );
            // Fallback: if decrypt returns empty, the stored value is a legacy plain-text key
            $settings['api_keys'][ $id ] = $decrypted !== '' ? $decrypted : $stored;
        }

        return $settings;
    }

    public static function getDefaultPrompt(): string {
        return 'Schreibe eine SEO-optimierte Meta-Beschreibung für den folgenden Artikel.' . "\n"
             . 'Die Beschreibung soll für menschliche Leser verständlich und hilfreich sein,' . "\n"
             . 'den Inhalt treffend zusammenfassen und zwischen 150 und 160 Zeichen lang sein.' . "\n"
             . 'Schreibe die Meta-Beschreibung auf {language}.' . "\n"
             . 'Antworte ausschließlich mit der Meta-Beschreibung, ohne Erklärung.' . "\n\n"
             . 'Titel: {title}' . "\n"
             . 'Inhalt: {content}';
    }

    public function sanitize_settings( mixed $input ): array {
        $input    = is_array( $input ) ? $input : [];
        $raw_existing = get_option( self::OPTION_KEY, [] );
        $existing      = is_array( $raw_existing ) ? $raw_existing : [];
        $clean    = [];

        $clean['provider']          = sanitize_key( $input['provider'] ?? 'openai' );
        $clean['meta_auto_enabled'] = ! empty( $input['meta_auto_enabled'] );
        $clean['token_mode']        = in_array( $input['token_mode'] ?? '', [ 'limit', 'full' ], true ) ? $input['token_mode'] : 'limit';
        $clean['token_limit']       = max( 100, (int) ( $input['token_limit'] ?? 1000 ) );
        $clean['prompt']            = sanitize_textarea_field( $input['prompt'] ?? self::getDefaultPrompt() );

        $clean['api_keys'] = [];
        foreach ( ( $input['api_keys'] ?? [] ) as $provider_id => $raw ) {
            $provider_id = sanitize_key( $provider_id );
            $raw         = sanitize_text_field( $raw );
            if ( $raw !== '' ) {
                $clean['api_keys'][ $provider_id ] = KeyVault::encrypt( $raw );
            } elseif ( isset( $existing['api_keys'][ $provider_id ] ) ) {
                $clean['api_keys'][ $provider_id ] = $existing['api_keys'][ $provider_id ]; // keep encrypted
            }
        }

        $clean['models'] = [];
        foreach ( ( $input['models'] ?? [] ) as $provider_id => $model ) {
            $clean['models'][ sanitize_key( $provider_id ) ] = sanitize_text_field( $model );
        }

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

    public function ajax_test_connection(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }

        $provider_id = sanitize_key( $_POST['provider'] ?? '' );

        $settings = self::getSettings(); // already decrypted
        $api_key  = $settings['api_keys'][ $provider_id ] ?? '';
        if ( empty( $api_key ) ) {
            wp_send_json_error( 'Kein API Key gespeichert. Bitte zuerst speichern.' );
        }

        $provider = ProviderRegistry::instance()->get( $provider_id );
        if ( ! $provider ) {
            wp_send_json_error( 'Unbekannter Provider.' );
        }

        $result = $provider->testConnection( $api_key );
        if ( $result['success'] ) {
            wp_send_json_success( $result['message'] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    public function ajax_get_default_prompt(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        wp_send_json_success( self::getDefaultPrompt() );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $settings      = self::getSettings();
        $providers     = ProviderRegistry::instance()->all();
        $post_types    = get_post_types( [ 'public' => true ], 'objects' );
        $schema_labels = [
            'organization'  => 'Organization (sameAs Social-Profile)',
            'author'        => 'Author (sameAs Profil-Links)',
            'speakable'     => 'Speakable (für AI-Assistenten)',
            'article_about' => 'Article about/mentions',
            'breadcrumb'    => 'BreadcrumbList',
            'ai_meta_tags'  => 'AI-optimierte Meta-Tags (max-snippet etc.)',
        ];

        $masked_keys  = [];
        $raw_settings = get_option( self::OPTION_KEY, [] );
        foreach ( $raw_settings['api_keys'] ?? [] as $id => $stored ) {
            $plain               = KeyVault::decrypt( $stored );
            $masked_keys[ $id ]  = KeyVault::mask( $plain !== '' ? $plain : $stored );
        }

        include BRE_DIR . 'includes/Admin/views/settings.php';
    }
}
