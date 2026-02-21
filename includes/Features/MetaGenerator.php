<?php
namespace BavarianRankEngine\Features;

use BavarianRankEngine\Admin\SettingsPage;
use BavarianRankEngine\ProviderRegistry;
use BavarianRankEngine\Helpers\TokenEstimator;
use BavarianRankEngine\Helpers\BulkQueue;

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

        add_action( 'wp_ajax_bre_bulk_generate', [ $this, 'ajaxBulkGenerate' ] );
        add_action( 'wp_ajax_bre_bulk_stats',    [ $this, 'ajaxBulkStats' ] );
        add_action( 'wp_ajax_bre_bulk_release',  [ $this, 'ajaxBulkRelease' ] );
        add_action( 'wp_ajax_bre_bulk_status',   [ $this, 'ajaxBulkStatus' ] );
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
            error_log( '[BRE] Meta generation failed for post ' . $post_id . ': ' . $e->getMessage() );
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

        return apply_filters( 'bre_prompt', $prompt, $post );
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
            '_bre_meta_description',
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
        update_post_meta( $post_id, '_bre_meta_description', $clean );

        if ( defined( 'RANK_MATH_VERSION' ) ) {
            update_post_meta( $post_id, 'rank_math_description', $clean );
        } elseif ( defined( 'WPSEO_VERSION' ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', $clean );
        } elseif ( defined( 'AIOSEO_VERSION' ) ) {
            update_post_meta( $post_id, '_aioseo_description', $clean );
        } elseif ( class_exists( 'SeoPress_Titles_Admin' ) ) {
            update_post_meta( $post_id, '_seopress_titles_desc', $clean );
        }

        do_action( 'bre_meta_saved', $post_id, $description );
    }

    public function ajaxBulkStats(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
        }

        $settings = SettingsPage::getSettings();
        $stats    = [];

        foreach ( $settings['meta_post_types'] as $pt ) {
            $stats[ $pt ] = $this->countPostsWithoutMeta( $pt );
        }

        wp_send_json_success( $stats );
    }

    public function ajaxBulkRelease(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        BulkQueue::release();
        wp_send_json_success();
    }

    public function ajaxBulkStatus(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        wp_send_json_success( [
            'locked'   => BulkQueue::isLocked(),
            'lock_age' => BulkQueue::lockAge(),
        ] );
    }

    public function ajaxBulkGenerate(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
        }

        // Acquire lock on first batch
        if ( ! empty( $_POST['is_first'] ) ) {
            if ( ! BulkQueue::acquire() ) {
                wp_send_json_error( [
                    'locked'   => true,
                    'lock_age' => BulkQueue::lockAge(),
                    'message'  => __( 'Ein Bulk-Prozess läuft bereits.', 'bavarian-rank-engine' ),
                ] );
                return;
            }
        }

        $post_type = sanitize_key( $_POST['post_type'] ?? 'post' );
        $limit     = min( 20, max( 1, (int) ( $_POST['batch_size'] ?? 5 ) ) );
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

        $post_ids    = $this->getPostsWithoutMeta( $post_type, $limit );
        $results     = [];
        $max_retries = 3;

        foreach ( $post_ids as $post_id ) {
            $post       = get_post( $post_id );
            $success    = false;
            $last_error = '';

            for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
                try {
                    $desc = $this->generate( $post, $settings );
                    $this->saveMeta( $post_id, $desc );
                    delete_post_meta( $post_id, '_bre_bulk_failed' );
                    $results[] = [
                        'id'          => $post_id,
                        'title'       => get_the_title( $post_id ),
                        'description' => $desc,
                        'success'     => true,
                        'attempts'    => $attempt,
                    ];
                    $success = true;
                    break;
                } catch ( \Exception $e ) {
                    $last_error = $e->getMessage();
                    error_log( '[BRE] Post ' . $post_id . ' attempt ' . $attempt . '/' . $max_retries . ': ' . $last_error );
                    if ( $attempt < $max_retries ) {
                        sleep( 1 );
                    }
                }
            }

            if ( ! $success ) {
                update_post_meta( $post_id, '_bre_bulk_failed', $last_error );
                $results[] = [
                    'id'      => $post_id,
                    'title'   => get_the_title( $post_id ),
                    'error'   => $last_error,
                    'success' => false,
                ];
            }
        }

        // Release lock when JS signals last batch
        if ( ! empty( $_POST['is_last'] ) ) {
            BulkQueue::release();
        }

        wp_send_json_success( [
            'results'   => $results,
            'processed' => count( $results ),
            'remaining' => $this->countPostsWithoutMeta( $post_type ),
            'locked'    => BulkQueue::isLocked(),
        ] );
    }

    private function countPostsWithoutMeta( string $post_type ): int {
        global $wpdb;

        $meta_fields = [
            '_bre_meta_description',
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

        $meta_fields = [
            '_bre_meta_description', 'rank_math_description',
            '_yoast_wpseo_metadesc', '_aioseo_description',
            '_seopress_titles_desc', '_meta_description',
        ];

        $not_exists = '';
        foreach ( $meta_fields as $field ) {
            $not_exists .= $wpdb->prepare(
                " AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                  AND pm.meta_key = %s
                  AND pm.meta_value != ''
            )",
                $field
            );
        }

        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
         WHERE p.post_type = %s AND p.post_status = 'publish'"
            . $not_exists .
            " ORDER BY p.ID DESC LIMIT %d",
            $post_type,
            $limit
        ) ) );
    }
}
