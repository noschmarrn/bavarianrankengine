<?php
namespace BavarianRankEngine\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\Admin\SettingsPage;
use BavarianRankEngine\ProviderRegistry;
use BavarianRankEngine\Helpers\TokenEstimator;

class GeoBlock {
	public const OPTION_KEY = 'bre_geo_settings';

	// Post meta keys
	public const META_ENABLED   = '_bre_geo_enabled';
	public const META_LOCK      = '_bre_geo_lock';
	public const META_GENERATED = '_bre_geo_last_generated_at';
	public const META_SUMMARY   = '_bre_geo_summary';
	public const META_BULLETS   = '_bre_geo_bullets';
	public const META_FAQ       = '_bre_geo_faq';
	public const META_ADDON     = '_bre_geo_prompt_addon';

	// Fluff phrases to detect in AI output
	private const FLUFF_PHRASES = array(
		'ultimativ',
		'gamechanger',
		'in diesem artikel',
		'wir schauen uns an',
		'in this article',
		'ultimate guide',
		'game changer',
		'game-changer',
	);

	public static function getSettings(): array {
		$defaults = array(
			'enabled'            => false,
			'mode'               => 'auto_on_publish',
			'post_types'         => array( 'post', 'page' ),
			'position'           => 'after_first_p',
			'output_style'       => 'details_collapsible',
			'title'              => 'Schnellüberblick',
			'label_summary'      => 'Kurzfassung',
			'label_bullets'      => 'Kernaussagen',
			'label_faq'          => 'FAQ',
			'minimal_css'        => true,
			'custom_css'         => '',
			'prompt_default'     => self::getDefaultPrompt(),
			'word_threshold'     => 350,
			'regen_on_update'    => false,
			'allow_prompt_addon' => false,
		);
		$saved    = get_option( self::OPTION_KEY, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	public static function getDefaultPrompt(): string {
		return 'Analysiere den folgenden Artikel und erstelle einen strukturierten Schnellüberblick.' . "\n"
			. 'Antworte ausschließlich mit einem validen JSON-Objekt (keine Markdown-Code-Blöcke, kein Text davor oder danach).' . "\n\n"
			. 'Sprache: {language}' . "\n"
			. 'Artikel-Titel: {title}' . "\n\n"
			. 'Regeln:' . "\n"
			. '- summary: 40–90 Wörter, neutral, sachlich, keine Werbung, keine Superlative.' . "\n"
			. '- bullets: 3–7 kurze Kernaussagen. Keine Wiederholungen aus der summary.' . "\n"
			. '- faq: 0–5 Frage-Antwort-Paare, NUR wenn der Artikel echte Fragen beantwortet. Sonst leeres Array [].' . "\n"
			. '- Nichts erfinden. Keine Keyword-Häufung. Kurze, klare Sätze.' . "\n"
			. '- Keine Phrasen wie "In diesem Artikel", "ultimativ", "Gamechanger".' . "\n\n"
			. 'JSON-Format (exakt):' . "\n"
			. '{"summary":"...","bullets":["...","..."],"faq":[{"q":"...","a":"..."}]}' . "\n\n"
			. 'Artikel-Inhalt:' . "\n"
			. '{content}';
	}
	public function generate( int $post_id, bool $force = false ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$settings = self::getSettings();

		// Check lock
		if ( ! $force && get_post_meta( $post_id, self::META_LOCK, true ) ) {
			return false;
		}

		$global   = SettingsPage::getSettings();
		$provider = ProviderRegistry::instance()->get( $global['provider'] );
		$api_key  = $global['api_keys'][ $global['provider'] ] ?? '';

		if ( ! $provider || empty( $api_key ) ) {
			return false;
		}

		$model   = $global['models'][ $global['provider'] ] ?? array_key_first( $provider->getModels() );
		$content = wp_strip_all_tags( do_shortcode( $post->post_content ) );

		// Token-limit the content input
		$content = TokenEstimator::truncate( $content, 2000 );

		$word_count   = str_word_count( $content );
		$force_no_faq = $word_count < (int) $settings['word_threshold'];
		$addon        = $settings['allow_prompt_addon']
						? sanitize_textarea_field( get_post_meta( $post_id, self::META_ADDON, true ) )
						: '';
		$prompt       = $this->buildPrompt( $post, $content, $settings, $addon, $force_no_faq );

		try {
			$raw    = $provider->generateText( $prompt, $api_key, $model, 800 );
			$parsed = $this->parseResponse( $raw );
			if ( null === $parsed ) {
				return false;
			}
			$data = $this->qualityGate( $parsed, $force_no_faq );
			$this->saveMeta( $post_id, $data );
			return true;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[BRE GEO] Generation failed for post ' . $post_id . ': ' . $e->getMessage() );
			}
			return false;
		}
	}

	private function buildPrompt( \WP_Post $post, string $content, array $settings, string $addon, bool $force_no_faq ): string {
		$locale_map = array(
			'de_DE' => 'Deutsch',
			'de_AT' => 'Deutsch',
			'de_CH' => 'Deutsch',
			'en_US' => 'English',
			'en_GB' => 'English',
			'fr_FR' => 'Français',
			'es_ES' => 'Español',
		);

		$language = $locale_map[ get_locale() ] ?? 'Deutsch';
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post->ID, 'name' );
			if ( $lang ) {
				$language = $lang;
			}
		} elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$language = ICL_LANGUAGE_CODE;
		}

		$prompt = $settings['prompt_default'];
		$prompt = str_replace( '{title}', $post->post_title, $prompt );
		$prompt = str_replace( '{content}', $content, $prompt );
		$prompt = str_replace( '{language}', $language, $prompt );

		if ( $force_no_faq ) {
			$prompt .= "\n\nWICHTIG: Setze faq immer auf ein leeres Array: []";
		}
		if ( ! empty( $addon ) ) {
			$prompt .= "\n\nZusätzliche Anweisung: " . $addon;
		}

		return $prompt;
	}

	private function parseResponse( string $raw ): ?array {
		// Strip markdown code fences if present
		$raw = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw ) );
		$raw = preg_replace( '/\s*```$/', '', $raw );
		$raw = trim( $raw );

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		// Require at minimum a summary
		if ( empty( $data['summary'] ) || ! is_string( $data['summary'] ) ) {
			return null;
		}
		return $data;
	}

	private function qualityGate( array $data, bool $force_no_faq ): array {
		$summary = trim( $data['summary'] ?? '' );
		$bullets = array_values( array_filter( (array) ( $data['bullets'] ?? array() ), 'is_string' ) );
		$faq     = $force_no_faq ? array() : array_values(
			array_filter(
				(array) ( $data['faq'] ?? array() ),
				function ( $item ) {
					return is_array( $item ) && ! empty( $item['q'] ) && ! empty( $item['a'] );
				}
			)
		);

		// Hard bounds: trim summary if too long
		$word_count = str_word_count( $summary );
		if ( $word_count > 140 ) {
			$words   = explode( ' ', $summary );
			$summary = implode( ' ', array_slice( $words, 0, 140 ) );
		}

		// Trim bullets/FAQ to soft max
		if ( count( $bullets ) > 7 ) {
			$bullets = array_slice( $bullets, 0, 7 );
		}
		if ( count( $faq ) > 5 ) {
			$faq = array_slice( $faq, 0, 5 );
		}

		return array(
			'summary' => $summary,
			'bullets' => $bullets,
			'faq'     => $faq,
		);
	}

	public function saveMeta( int $post_id, array $data ): void {
		update_post_meta( $post_id, self::META_SUMMARY, sanitize_text_field( $data['summary'] ?? '' ) );
		update_post_meta( $post_id, self::META_BULLETS, wp_json_encode( array_map( 'sanitize_text_field', $data['bullets'] ?? array() ) ) );

		$faq_clean = array_map(
			function ( $item ) {
				return array(
					'q' => sanitize_text_field( $item['q'] ?? '' ),
					'a' => sanitize_text_field( $item['a'] ?? '' ),
				);
			},
			$data['faq'] ?? array()
		);
		update_post_meta( $post_id, self::META_FAQ, wp_json_encode( $faq_clean ) );
		update_post_meta( $post_id, self::META_GENERATED, time() );
	}

	public static function getMeta( int $post_id ): array {
		$summary = get_post_meta( $post_id, self::META_SUMMARY, true ) ?: '';
		$bullets = json_decode( get_post_meta( $post_id, self::META_BULLETS, true ) ?: '[]', true );
		$faq     = json_decode( get_post_meta( $post_id, self::META_FAQ, true ) ?: '[]', true );
		return array(
			'summary' => is_string( $summary ) ? $summary : '',
			'bullets' => is_array( $bullets ) ? $bullets : array(),
			'faq'     => is_array( $faq ) ? $faq : array(),
		);
	}

	public function register(): void {
		$settings = self::getSettings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		if ( $settings['output_style'] !== 'store_only_no_frontend' ) {
			add_filter( 'the_content', array( $this, 'injectBlock' ) );
		}
		if ( $settings['minimal_css'] ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueueCss' ) );
		}
		if ( ! empty( $settings['custom_css'] ) ) {
			add_action( 'wp_head', array( $this, 'inlineCustomCss' ) );
		}
		// Publish hook
		add_action( 'transition_post_status', array( $this, 'onStatusTransition' ), 20, 3 );
		// Update hook
		if ( ! empty( $settings['regen_on_update'] ) ) {
			add_action( 'save_post', array( $this, 'onSavePost' ), 20, 2 );
		}
	}

	public function enqueueCss(): void {
		if ( ! is_singular() ) {
			return;
		}
		wp_enqueue_style( 'bre-geo-frontend', BRE_URL . 'assets/geo-frontend.css', array(), BRE_VERSION );
	}

	public function inlineCustomCss(): void {
		if ( ! is_singular() ) {
			return;
		}
		$settings = self::getSettings();
		$css      = $settings['custom_css'] ?? '';
		if ( empty( $css ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<style>.bre-geo{' . esc_html( $css ) . '}</style>' . "\n";
	}

	public function injectBlock( string $content ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Per-post enabled override: '' = follow global, '1' = on, '0' = off
		$per_post = get_post_meta( $post_id, self::META_ENABLED, true );
		if ( $per_post === '0' ) {
			return $content;
		}

		$meta = self::getMeta( $post_id );
		if ( empty( $meta['summary'] ) && empty( $meta['bullets'] ) ) {
			return $content;
		}

		$block    = $this->renderBlock( $meta );
		$settings = self::getSettings();

		switch ( $settings['position'] ) {
			case 'top':
				return $block . $content;
			case 'bottom':
				return $content . $block;
			case 'after_first_p':
			default:
				$parts = preg_split( '/(<\/p>)/i', $content, 2, PREG_SPLIT_DELIM_CAPTURE );
				if ( count( $parts ) >= 3 ) {
					return $parts[0] . $parts[1] . $block . $parts[2];
				}
				return $block . $content;
		}
	}

	private function renderBlock( array $meta ): string {
		$settings = self::getSettings();
		$style    = $settings['output_style'];

		$title         = esc_html( $settings['title'] );
		$label_summary = esc_html( $settings['label_summary'] );
		$label_bullets = esc_html( $settings['label_bullets'] );
		$label_faq     = esc_html( $settings['label_faq'] );

		$inner = '';

		if ( ! empty( $meta['summary'] ) ) {
			$inner .= '<div class="bre-geo__section bre-geo__summary">'
					. '<h3>' . $label_summary . '</h3>'
					. '<p>' . esc_html( $meta['summary'] ) . '</p>'
					. '</div>';
		}

		if ( ! empty( $meta['bullets'] ) ) {
			$items = '';
			foreach ( $meta['bullets'] as $bullet ) {
				$items .= '<li>' . esc_html( $bullet ) . '</li>';
			}
			$inner .= '<div class="bre-geo__section bre-geo__bullets">'
					. '<h3>' . $label_bullets . '</h3>'
					. '<ul>' . $items . '</ul>'
					. '</div>';
		}

		if ( ! empty( $meta['faq'] ) ) {
			$pairs = '';
			foreach ( $meta['faq'] as $item ) {
				$pairs .= '<dt>' . esc_html( $item['q'] ) . '</dt>'
						. '<dd>' . esc_html( $item['a'] ) . '</dd>';
			}
			$inner .= '<div class="bre-geo__section bre-geo__faq">'
					. '<h3>' . $label_faq . '</h3>'
					. '<dl>' . $pairs . '</dl>'
					. '</div>';
		}

		$open_attr = ( $style === 'open_always' ) ? ' open' : '';
		return '<details class="bre-geo" data-bre="geo"' . $open_attr . '>'
			. '<summary><span class="bre-geo__title">' . $title . '</span></summary>'
			. $inner
			. '</details>';
	}

	public function onStatusTransition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( $new_status !== 'publish' ) {
			return;
		}
		$settings = self::getSettings();
		if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
			return;
		}
		$mode = $settings['mode'];
		if ( $mode === 'manual_only' ) {
			return;
		}
		if ( $mode === 'hybrid' ) {
			$meta = self::getMeta( $post->ID );
			if ( ! empty( $meta['summary'] ) ) {
				return;
			}
		}
		$this->generate( $post->ID );
	}

	public function onSavePost( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( $post->post_status !== 'publish' ) {
			return;
		}
		$settings = self::getSettings();
		if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
			return;
		}
		$this->generate( $post_id );
	}
}
