<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SchemaPage {
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_settings(): void {
		register_setting(
			'bre_schema',
			SettingsPage::OPTION_KEY_SCHEMA,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'bavarian-rank_page_bre-schema' ) {
			return;
		}
		wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', array(), BRE_VERSION );
	}

	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		$schema_types            = array(
			'organization',
			'author',
			'speakable',
			'article_about',
			'breadcrumb',
			'ai_meta_tags',
			'faq_schema',
			'blog_posting',
			'image_object',
			'video_object',
			'howto',
			'review',
			'recipe',
			'event',
		);
		$clean['schema_enabled'] = array_values(
			array_intersect(
				array_map( 'sanitize_key', (array) ( $input['schema_enabled'] ?? array() ) ),
				$schema_types
			)
		);

		$org_raw = $input['schema_same_as']['organization'] ?? '';
		if ( is_array( $org_raw ) ) {
			$org_raw = implode( "\n", $org_raw );
		}
		$clean['schema_same_as'] = array(
			'organization' => array_values(
				array_filter(
					array_map(
						'esc_url_raw',
						array_map( 'trim', explode( "\n", $org_raw ) )
					)
				)
			),
		);

		return $clean;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings      = SettingsPage::getSettings();
		$schema_labels = array(
			'organization'  => __( 'Organization (sameAs Social Profiles)', 'bavarian-rank-engine' ),
			'author'        => __( 'Author (sameAs Profile Links)', 'bavarian-rank-engine' ),
			'speakable'     => __( 'Speakable (for AI assistants)', 'bavarian-rank-engine' ),
			'article_about' => __( 'Article about/mentions', 'bavarian-rank-engine' ),
			'breadcrumb'    => __( 'BreadcrumbList', 'bavarian-rank-engine' ),
			'ai_meta_tags'  => __( 'AI-optimized Meta Tags (max-snippet etc.)', 'bavarian-rank-engine' ),
			'faq_schema'    => __( 'FAQPage (aus GEO Quick Overview — automatisch)', 'bavarian-rank-engine' ),
			'blog_posting'  => __( 'BlogPosting / Article (mit eingebettetem Author + Image)', 'bavarian-rank-engine' ),
			'image_object'  => __( 'ImageObject (Featured Image)', 'bavarian-rank-engine' ),
			'video_object'  => __( 'VideoObject (YouTube/Vimeo automatisch erkennen)', 'bavarian-rank-engine' ),
			'howto'         => __( 'HowTo (Metabox im Post-Editor)', 'bavarian-rank-engine' ),
			'review'        => __( 'Review mit Bewertung (Metabox im Post-Editor)', 'bavarian-rank-engine' ),
			'recipe'        => __( 'Recipe (Metabox im Post-Editor)', 'bavarian-rank-engine' ),
			'event'         => __( 'Event (Metabox im Post-Editor)', 'bavarian-rank-engine' ),
		);
		include BRE_DIR . 'includes/Admin/views/schema.php';
	}
}
