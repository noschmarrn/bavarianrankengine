<?php
namespace BavarianRankEngine\Admin;

use BavarianRankEngine\Helpers\KeyVault;

class SettingsPage {
    /**
     * Option key for provider + API key data (retains old key name for data continuity).
     */
    public const OPTION_KEY_PROVIDER = 'bre_settings';

    /**
     * Option key for meta generator settings.
     */
    public const OPTION_KEY_META = 'bre_meta_settings';

    /**
     * Returns merged settings from both option keys with defaults applied.
     * Called by MetaGenerator, SchemaEnhancer, BulkPage, and admin pages.
     */
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
            'costs'             => [],
        ];

        $saved_provider = get_option( self::OPTION_KEY_PROVIDER, [] );
        $saved_provider = is_array( $saved_provider ) ? $saved_provider : [];

        $saved_meta = get_option( self::OPTION_KEY_META, [] );
        $saved_meta = is_array( $saved_meta ) ? $saved_meta : [];

        $settings = array_merge( $defaults, $saved_provider, $saved_meta );

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

    /**
     * Kept for backwards compatibility — used by BulkPage and tests.
     * Validates and sanitises a combined settings array (provider + meta fields).
     */
    public function sanitize_settings( mixed $input ): array {
        $input         = is_array( $input ) ? $input : [];
        $raw_existing  = get_option( self::OPTION_KEY_PROVIDER, [] );
        $existing      = is_array( $raw_existing ) ? $raw_existing : [];
        $clean         = [];

        $clean['provider']          = sanitize_key( $input['provider'] ?? 'openai' );
        $clean['meta_auto_enabled'] = ! empty( $input['meta_auto_enabled'] );
        $clean['token_mode']        = in_array( $input['token_mode'] ?? '', [ 'limit', 'full' ], true )
                                        ? $input['token_mode'] : 'limit';
        $clean['token_limit']       = max( 100, (int) ( $input['token_limit'] ?? 1000 ) );
        $clean['prompt']            = sanitize_textarea_field( $input['prompt'] ?? self::getDefaultPrompt() );

        $clean['api_keys'] = [];
        foreach ( ( $input['api_keys'] ?? [] ) as $provider_id => $raw ) {
            $provider_id = sanitize_key( $provider_id );
            $raw         = sanitize_text_field( $raw );
            if ( $raw !== '' ) {
                $clean['api_keys'][ $provider_id ] = KeyVault::encrypt( $raw );
            } elseif ( isset( $existing['api_keys'][ $provider_id ] ) ) {
                $clean['api_keys'][ $provider_id ] = $existing['api_keys'][ $provider_id ];
            }
        }

        $clean['models'] = [];
        foreach ( ( $input['models'] ?? [] ) as $provider_id => $model ) {
            $clean['models'][ sanitize_key( $provider_id ) ] = sanitize_text_field( $model );
        }

        $clean['costs'] = [];
        foreach ( ( $input['costs'] ?? [] ) as $provider_id => $models ) {
            $provider_id = sanitize_key( $provider_id );
            foreach ( (array) $models as $model_id => $prices ) {
                $model_id = sanitize_text_field( $model_id );
                $clean['costs'][ $provider_id ][ $model_id ] = [
                    'input'  => max( 0.0, (float) ( $prices['input']  ?? 0 ) ),
                    'output' => max( 0.0, (float) ( $prices['output'] ?? 0 ) ),
                ];
            }
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
}
