<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\ProviderRegistry;
use BavarianRankEngine\Helpers\KeyVault;

class ProviderPage {
	private const PRICING_URLS = array(
		'openai'    => 'https://openai.com/de-DE/api/pricing',
		'anthropic' => 'https://platform.claude.com/docs/en/about-claude/pricing',
		'gemini'    => 'https://ai.google.dev/gemini-api/docs/pricing?hl=de',
		'grok'      => 'https://docs.x.ai/developers/models',
	);

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_bre_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bre_get_default_prompt', array( $this, 'ajax_get_default_prompt' ) );
	}

	public function register_settings(): void {
		register_setting(
			'bre_provider',
			SettingsPage::OPTION_KEY_PROVIDER,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'bavarian-rank_page_bre-provider' ) {
			return;
		}
		wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', array(), BRE_VERSION );
		wp_enqueue_script( 'bre-admin', BRE_URL . 'assets/admin.js', array( 'jquery' ), BRE_VERSION, true );
		wp_localize_script(
			'bre-admin',
			'breAdmin',
			array(
				'nonce'        => wp_create_nonce( 'bre_admin' ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'testing'      => __( 'Testing…', 'bavarian-rank-engine' ),
				'networkError' => __( 'Network error', 'bavarian-rank-engine' ),
				'resetConfirm' => __( 'Really reset the prompt?', 'bavarian-rank-engine' ),
			)
		);
	}

	public function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$raw_ex   = get_option( SettingsPage::OPTION_KEY_PROVIDER, array() );
		$existing = is_array( $raw_ex ) ? $raw_ex : array();
		$clean    = array();

		$clean['provider'] = sanitize_key( $input['provider'] ?? 'openai' );

		$clean['api_keys'] = array();
		foreach ( ( $input['api_keys'] ?? array() ) as $provider_id => $raw ) {
			$provider_id = sanitize_key( $provider_id );
			$raw         = sanitize_text_field( $raw );
			if ( $raw !== '' ) {
				$clean['api_keys'][ $provider_id ] = KeyVault::encrypt( $raw );
			} elseif ( isset( $existing['api_keys'][ $provider_id ] ) ) {
				$clean['api_keys'][ $provider_id ] = $existing['api_keys'][ $provider_id ];
			}
		}

		$clean['models'] = array();
		foreach ( ( $input['models'] ?? array() ) as $provider_id => $model ) {
			$clean['models'][ sanitize_key( $provider_id ) ] = sanitize_text_field( $model );
		}

		$clean['costs'] = array();
		foreach ( ( $input['costs'] ?? array() ) as $provider_id => $models ) {
			$provider_id = sanitize_key( $provider_id );
			foreach ( (array) $models as $model_id => $prices ) {
				$in  = (float) str_replace( ',', '.', $prices['input'] ?? '0' );
				$out = (float) str_replace( ',', '.', $prices['output'] ?? '0' );
				if ( $in > 0 || $out > 0 ) {
					$clean['costs'][ $provider_id ][ sanitize_text_field( $model_id ) ] = array(
						'input'  => max( 0.0, $in ),
						'output' => max( 0.0, $out ),
					);
				}
			}
		}

		return $clean;
	}

	public function ajax_test_connection(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
		}
		$provider_id = sanitize_key( $_POST['provider'] ?? '' );
		$settings    = SettingsPage::getSettings();
		$api_key     = $settings['api_keys'][ $provider_id ] ?? '';
		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'No API key saved. Please save first.', 'bavarian-rank-engine' ) );
		}
		$provider = ProviderRegistry::instance()->get( $provider_id );
		if ( ! $provider ) {
			wp_send_json_error( __( 'Unknown provider.', 'bavarian-rank-engine' ) );
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
		wp_send_json_success( SettingsPage::getDefaultPrompt() );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings     = SettingsPage::getSettings();
		$providers    = ProviderRegistry::instance()->all();
		$masked_keys  = array();
		$raw_settings = get_option( SettingsPage::OPTION_KEY_PROVIDER, array() );
		foreach ( ( $raw_settings['api_keys'] ?? array() ) as $id => $stored ) {
			$plain              = KeyVault::decrypt( $stored );
			$masked_keys[ $id ] = KeyVault::mask( $plain );
		}
		$pricing_urls = self::PRICING_URLS;
		include BRE_DIR . 'includes/Admin/views/provider.php';
	}
}
