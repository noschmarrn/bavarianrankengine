<?php
namespace SeoGeo\Features;

use SeoGeo\Admin\SettingsPage;
use SeoGeo\ProviderRegistry;
use SeoGeo\Helpers\TokenEstimator;

class MetaGenerator {
    public function register(): void {
        $settings = SettingsPage::getSettings();

        if ( ! empty( $settings['meta_auto_enabled'] ) ) {
            add_action( 'publish_post', [ $this, 'onPublish' ], 20, 2 );
            add_action( 'publish_page', [ $this, 'onPublish' ], 20, 2 );

            foreach ( $settings['meta_post_types'] as $post_type ) {
                if ( ! in_array( $post_type, [ 'post', 'page' ], true ) ) {
                    add_action( "publish_{$post_type}", [ $this, 'onPublish' ], 20, 2 );
                }
            }
        }

        add_action( 'wp_ajax_seo_geo_bulk_generate', [ $this, 'ajaxBulkGenerate' ] );
        add_action( 'wp_ajax_seo_geo_bulk_stats',    [ $this, 'ajaxBulkStats' ] );
    }

    public function onPublish( int $post_id, \WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        if ( $this->hasExistingMeta( $post_id ) ) return;

        $settings = SettingsPage::getSettings();
        if ( ! in_array( $post->post_type, $settings['meta_post_types'], true ) ) return;

        try {
            $description = $this->generate( $post, $settings );
            if ( ! empty( $description ) ) {
                $this->saveMeta( $post_id, $description );
            }
        } catch ( \Exception $e ) {
            error_log( '[SEO-GEO] Meta generation failed for post ' . $post_id . ': ' . $e->getMessage() );
        }
    }

    public function generate( \WP_Post $post, array $settings ): string {
        $registry = ProviderRegistry::instance();
        $provider = $registry->get( $settings['provider'] );

        if ( ! $provider ) {
            throw new \RuntimeException( 'Provider not found: ' . $settings['provider'] );
        }

        $api_key = $settings['api_keys'][ $settings['provider'] ] ?? '';
        if ( empty( $api_key ) ) {
            throw new \RuntimeException( 'No API key configured for provider: ' . $settings['provider'] );
        }

        $model   = $settings['models'][ $settings['provider'] ] ?? array_key_first( $provider->getModels() );
        $content = $this->prepareContent( $post, $settings );
        $prompt  = $this->buildPrompt( $post, $content, $settings );

        return $provider->generateText( $prompt, $api_key, $model, 300 );
    }

    private function prepareContent( \WP_Post $post, array $settings ): string {
        $content = wp_strip_all_tags( $post->post_content );
        if ( $settings['token_mode'] === 'limit' ) {
            $content = TokenEstimator::truncate( $content, (int) $settings['token_limit'] );
        }
        return $content;
    }

    private function buildPrompt( \WP_Post $post, string $content, array $settings ): string {
        $language = $this->detectLanguage( $post );
        $prompt   = $settings['prompt'];

        $prompt = str_replace( '{title}',    $post->post_title,          $prompt );
        $prompt = str_replace( '{content}',  $content,                   $prompt );
        $prompt = str_replace( '{excerpt}',  $post->post_excerpt ?: '',  $prompt );
        $prompt = str_replace( '{language}', $language,                  $prompt );

        return apply_filters( 'seo_geo_prompt', $prompt, $post );
    }

    private function detectLanguage( \WP_Post $post ): string {
        if ( function_exists( 'pll_get_post_language' ) ) {
            $lang = pll_get_post_language( $post->ID, 'name' );
            if ( $lang ) return $lang;
        }

        if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            return ICL_LANGUAGE_CODE;
        }

        $locale_map = [
            'de_DE' => 'Deutsch', 'de_AT' => 'Deutsch', 'de_CH' => 'Deutsch',
            'en_US' => 'English', 'en_GB' => 'English',
            'fr_FR' => 'Français', 'es_ES' => 'Español',
        ];

        return $locale_map[ get_locale() ] ?? 'Deutsch';
    }

    public function hasExistingMeta( int $post_id ): bool {
        $fields = [
            '_seo_geo_meta_description',
            'rank_math_description',
            '_yoast_wpseo_metadesc',
            '_aioseo_description',
            '_seopress_titles_desc',
            '_meta_description',
        ];
        foreach ( $fields as $field ) {
            if ( ! empty( get_post_meta( $post_id, $field, true ) ) ) {
                return true;
            }
        }
        return false;
    }

    public function saveMeta( int $post_id, string $description ): void {
        $clean = sanitize_text_field( $description );
        update_post_meta( $post_id, '_seo_geo_meta_description', $clean );

        if ( defined( 'RANK_MATH_VERSION' ) ) {
            update_post_meta( $post_id, 'rank_math_description', $clean );
        } elseif ( defined( 'WPSEO_VERSION' ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', $clean );
        } elseif ( defined( 'AIOSEO_VERSION' ) ) {
            update_post_meta( $post_id, '_aioseo_description', $clean );
        } elseif ( class_exists( 'SeoPress_Titles_Admin' ) ) {
            update_post_meta( $post_id, '_seopress_titles_desc', $clean );
        }

        do_action( 'seo_geo_meta_saved', $post_id, $description );
    }

    public function ajaxBulkStats(): void {
        check_ajax_referer( 'seo_geo_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }

        $settings = SettingsPage::getSettings();
        $stats    = [];

        foreach ( $settings['meta_post_types'] as $pt ) {
            $stats[ $pt ] = $this->countPostsWithoutMeta( $pt );
        }

        wp_send_json_success( $stats );
    }

    public function ajaxBulkGenerate(): void {
        check_ajax_referer( 'seo_geo_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }

        $post_type = sanitize_key( $_POST['post_type'] ?? 'post' );
        $limit     = min( 5, max( 1, (int) ( $_POST['batch_size'] ?? 5 ) ) );
        $settings  = SettingsPage::getSettings();

        if ( ! empty( $_POST['provider'] ) ) {
            $settings['provider'] = sanitize_key( $_POST['provider'] );
        }
        if ( ! empty( $_POST['model'] ) ) {
            $provider_obj    = ProviderRegistry::instance()->get( $settings['provider'] );
            $allowed_models  = $provider_obj ? array_keys( $provider_obj->getModels() ) : [];
            $requested_model = sanitize_text_field( $_POST['model'] );
            if ( in_array( $requested_model, $allowed_models, true ) ) {
                $settings['models'][ $settings['provider'] ] = $requested_model;
            }
        }

        $post_ids = $this->getPostsWithoutMeta( $post_type, $limit );
        $results  = [];

        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            try {
                $desc = $this->generate( $post, $settings );
                $this->saveMeta( $post_id, $desc );
                $results[] = [
                    'id'          => $post_id,
                    'title'       => get_the_title( $post_id ),
                    'description' => $desc,
                    'success'     => true,
                ];
            } catch ( \Exception $e ) {
                $results[] = [
                    'id'      => $post_id,
                    'title'   => get_the_title( $post_id ),
                    'error'   => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        wp_send_json_success( [
            'results'   => $results,
            'processed' => count( $results ),
            'remaining' => $this->countPostsWithoutMeta( $post_type ),
        ] );
    }

    private function countPostsWithoutMeta( string $post_type ): int {
        global $wpdb;

        $meta_fields = [
            '_seo_geo_meta_description',
            'rank_math_description',
            '_yoast_wpseo_metadesc',
            '_aioseo_description',
            '_seopress_titles_desc',
            '_meta_description',
        ];

        $not_exists = '';
        foreach ( $meta_fields as $field ) {
            $not_exists .= $wpdb->prepare(
                " AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm
                    WHERE pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value != ''
                )",
                $field
            );
        }

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             WHERE p.post_type = %s AND p.post_status = 'publish'" . $not_exists,
            $post_type
        ) );
    }

    private function getPostsWithoutMeta( string $post_type, int $limit ): array {
        global $wpdb;

        $fetch_limit = min( $limit * 10, 5000 );
        $all_ids     = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status = 'publish'
             ORDER BY ID DESC
             LIMIT %d",
            $post_type,
            $fetch_limit
        ) );

        $without_meta = [];
        foreach ( $all_ids as $id ) {
            if ( ! $this->hasExistingMeta( (int) $id ) ) {
                $without_meta[] = (int) $id;
                if ( count( $without_meta ) >= $limit ) break;
            }
        }

        return $without_meta;
    }
}
