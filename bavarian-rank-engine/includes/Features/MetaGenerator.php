<?php
namespace BavarianRankEngine\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\Admin\SettingsPage;
use BavarianRankEngine\ProviderRegistry;
use BavarianRankEngine\Helpers\TokenEstimator;
use BavarianRankEngine\Helpers\BulkQueue;
use BavarianRankEngine\Helpers\FallbackMeta;

class MetaGenerator {
	public function register(): void {
		$settings = SettingsPage::getSettings();

		if ( ! empty( $settings['meta_auto_enabled'] ) ) {
			add_action( 'publish_post', array( $this, 'onPublish' ), 20, 2 );
			add_action( 'publish_page', array( $this, 'onPublish' ), 20, 2 );

			foreach ( $settings['meta_post_types'] as $post_type ) {
				if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
					add_action( "publish_{$post_type}", array( $this, 'onPublish' ), 20, 2 );
				}
			}
		}

		add_action( 'wp_ajax_bre_bulk_generate', array( $this, 'ajaxBulkGenerate' ) );
		add_action( 'wp_ajax_bre_bulk_stats', array( $this, 'ajaxBulkStats' ) );
		add_action( 'wp_ajax_bre_bulk_release', array( $this, 'ajaxBulkRelease' ) );
		add_action( 'wp_ajax_bre_bulk_status', array( $this, 'ajaxBulkStatus' ) );
	}

	public function onPublish( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( $this->hasExistingMeta( $post_id ) ) {
			return;
		}

		$settings = SettingsPage::getSettings();
		if ( ! in_array( $post->post_type, $settings['meta_post_types'], true ) ) {
			return;
		}

		try {
			$api_key     = $settings['api_keys'][ $settings['provider'] ] ?? '';
			$source      = ! empty( $api_key ) ? 'ai' : 'fallback';
			$description = $this->generate( $post, $settings );
			if ( ! empty( $description ) ) {
				$this->saveMeta( $post_id, $description, $source );
			}
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[BRE] Meta generation failed for post ' . $post_id . ': ' . $e->getMessage() );
			}
			// Try fallback
			$fallback = FallbackMeta::extract( $post );
			if ( $fallback !== '' ) {
				$this->saveMeta( $post_id, $fallback, 'fallback' );
			}
		}
	}

	public function generate( \WP_Post $post, array $settings ): string {
		$registry = ProviderRegistry::instance();
		$provider = $registry->get( $settings['provider'] );
		$api_key  = $settings['api_keys'][ $settings['provider'] ] ?? '';

		// No provider or no API key → use fallback immediately
		if ( ! $provider || empty( $api_key ) ) {
			return FallbackMeta::extract( $post );
		}

		if ( empty( $settings['ai_enabled'] ) ) {
			return FallbackMeta::extract( $post );
		}

		$model   = $settings['models'][ $settings['provider'] ] ?? array_key_first( $provider->getModels() );
		$content = $this->prepareContent( $post, $settings );
		$prompt  = $this->buildPrompt( $post, $content, $settings );

		$result      = $provider->generateText( $prompt, $api_key, $model, 300 );
		$tokens_in  = TokenEstimator::estimate( $prompt );
		$tokens_out = TokenEstimator::estimate( $result );
		self::record_usage( $tokens_in, $tokens_out );
		return $result;
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

		$prompt = str_replace( '{title}', $post->post_title, $prompt );
		$prompt = str_replace( '{content}', $content, $prompt );
		$prompt = str_replace( '{excerpt}', $post->post_excerpt ?: '', $prompt );
		$prompt = str_replace( '{language}', $language, $prompt );

		return apply_filters( 'bre_prompt', $prompt, $post ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	private function detectLanguage( \WP_Post $post ): string {
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post->ID, 'name' );
			if ( $lang ) {
				return $lang;
			}
		}

		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			return ICL_LANGUAGE_CODE;
		}

		$locale_map = array(
			'de_DE' => 'Deutsch',
			'de_AT' => 'Deutsch',
			'de_CH' => 'Deutsch',
			'en_US' => 'English',
			'en_GB' => 'English',
			'fr_FR' => 'Français',
			'es_ES' => 'Español',
		);

		return $locale_map[ get_locale() ] ?? 'Deutsch';
	}

	public function hasExistingMeta( int $post_id ): bool {
		$fields = array(
			'_bre_meta_description',
			'rank_math_description',
			'_yoast_wpseo_metadesc',
			'_aioseo_description',
			'_seopress_titles_desc',
			'_meta_description',
		);
		foreach ( $fields as $field ) {
			if ( ! empty( get_post_meta( $post_id, $field, true ) ) ) {
				return true;
			}
		}
		return false;
	}

	public function saveMeta( int $post_id, string $description, string $source = 'ai' ): void {
		$clean = sanitize_text_field( $description );
		update_post_meta( $post_id, '_bre_meta_source', sanitize_key( $source ) );
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

		do_action( 'bre_meta_saved', $post_id, $description ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		delete_transient( 'bre_meta_stats' );
	}

	public function ajaxBulkStats(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
		}

		$settings = SettingsPage::getSettings();
		$stats    = array();

		foreach ( $settings['meta_post_types'] as $pt ) {
			$stats[ $pt ] = $this->countPostsWithoutMeta( $pt );
		}

		wp_send_json_success( $stats );
	}

	public function ajaxBulkRelease(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		BulkQueue::release();
		wp_send_json_success();
	}

	public function ajaxBulkStatus(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		wp_send_json_success(
			array(
				'locked'   => BulkQueue::isLocked(),
				'lock_age' => BulkQueue::lockAge(),
			)
		);
	}

	public function ajaxBulkGenerate(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
		}

		// Acquire lock on first batch
		if ( ! empty( $_POST['is_first'] ) ) {
			if ( ! BulkQueue::acquire() ) {
				wp_send_json_error(
					array(
						'locked'   => true,
						'lock_age' => BulkQueue::lockAge(),
						'message'  => __( 'A bulk process is already running.', 'bavarian-rank-engine' ),
					)
				);
				return;
			}
		}

		$post_type = sanitize_key( wp_unslash( $_POST['post_type'] ?? 'post' ) );
		$limit     = min( 20, max( 1, absint( wp_unslash( $_POST['batch_size'] ?? 5 ) ) ) );
		$settings  = SettingsPage::getSettings();

		if ( ! empty( $_POST['provider'] ) ) {
			$settings['provider'] = sanitize_key( wp_unslash( $_POST['provider'] ) );
		}
		if ( ! empty( $_POST['model'] ) ) {
			$provider_obj    = ProviderRegistry::instance()->get( $settings['provider'] );
			$allowed_models  = $provider_obj ? array_keys( $provider_obj->getModels() ) : array();
			$requested_model = sanitize_text_field( wp_unslash( $_POST['model'] ) );
			if ( in_array( $requested_model, $allowed_models, true ) ) {
				$settings['models'][ $settings['provider'] ] = $requested_model;
			}
		}

		$post_ids    = $this->getPostsWithoutMeta( $post_type, $limit );
		$results     = array();
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
					$results[] = array(
						'id'          => $post_id,
						'title'       => get_the_title( $post_id ),
						'description' => $desc,
						'success'     => true,
						'attempts'    => $attempt,
					);
					$success   = true;
					break;
				} catch ( \Exception $e ) {
					$last_error = $e->getMessage();
					if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( '[BRE] Post ' . $post_id . ' attempt ' . $attempt . '/' . $max_retries . ': ' . $last_error );
					}
					if ( $attempt < $max_retries ) {
						sleep( 1 );
					}
				}
			}

			if ( ! $success ) {
				update_post_meta( $post_id, '_bre_bulk_failed', $last_error );
				$results[] = array(
					'id'      => $post_id,
					'title'   => get_the_title( $post_id ),
					'error'   => $last_error,
					'success' => false,
				);
			}
		}

		// Release lock when JS signals last batch
		if ( ! empty( $_POST['is_last'] ) ) {
			BulkQueue::release();
		}

		wp_send_json_success(
			array(
				'results'   => $results,
				'processed' => count( $results ),
				'remaining' => $this->countPostsWithoutMeta( $post_type ),
				'locked'    => BulkQueue::isLocked(),
			)
		);
	}

	private function countPostsWithoutMeta( string $post_type ): int {
		global $wpdb;

		$meta_fields = array(
			'_bre_meta_description',
			'rank_math_description',
			'_yoast_wpseo_metadesc',
			'_aioseo_description',
			'_seopress_titles_desc',
			'_meta_description',
		);

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

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
                 WHERE p.post_type = %s AND p.post_status = 'publish'" . $not_exists, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$post_type
			)
		);
	}

	private function getPostsWithoutMeta( string $post_type, int $limit ): array {
		global $wpdb;

		$meta_fields = array(
			'_bre_meta_description',
			'rank_math_description',
			'_yoast_wpseo_metadesc',
			'_aioseo_description',
			'_seopress_titles_desc',
			'_meta_description',
		);

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

		return array_map(
			'intval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
                 WHERE p.post_type = %s AND p.post_status = 'publish'"
					. $not_exists . // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					' ORDER BY p.ID DESC LIMIT %d',
					$post_type,
					$limit
				)
			)
		);
	}

	public static function record_usage( int $tokens_in, int $tokens_out ): void {
		$stats               = get_option( 'bre_usage_stats', array( 'tokens_in' => 0, 'tokens_out' => 0, 'count' => 0 ) );
		$stats['tokens_in']  = (int) ( $stats['tokens_in'] ?? 0 ) + $tokens_in;
		$stats['tokens_out'] = (int) ( $stats['tokens_out'] ?? 0 ) + $tokens_out;
		$stats['count']      = (int) ( $stats['count'] ?? 0 ) + 1;
		update_option( 'bre_usage_stats', $stats, false );
	}
}
