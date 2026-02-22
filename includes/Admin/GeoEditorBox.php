<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\Features\GeoBlock;

class GeoEditorBox {
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_bre_geo_generate', array( $this, 'ajax_generate' ) );
		add_action( 'wp_ajax_bre_geo_clear', array( $this, 'ajax_clear' ) );
	}

	public function add_boxes(): void {
		$settings = GeoBlock::getSettings();
		foreach ( $settings['post_types'] as $pt ) {
			add_meta_box(
				'bre_geo_box',
				__( 'GEO Schnellüberblick (BRE)', 'bavarian-rank-engine' ),
				array( $this, 'render' ),
				$pt,
				'normal',
				'default'
			);
		}
	}

	public function render( \WP_Post $post ): void {
		$settings     = GeoBlock::getSettings();
		$meta         = GeoBlock::getMeta( $post->ID );
		$enabled      = get_post_meta( $post->ID, GeoBlock::META_ENABLED, true );
		$lock         = (bool) get_post_meta( $post->ID, GeoBlock::META_LOCK, true );
		$generated_at = get_post_meta( $post->ID, GeoBlock::META_GENERATED, true );
		$prompt_addon = get_post_meta( $post->ID, GeoBlock::META_ADDON, true ) ?: '';
		$global       = SettingsPage::getSettings();
		$has_api_key  = ! empty( $global['api_keys'][ $global['provider'] ] ?? '' );

		wp_nonce_field( 'bre_geo_save_' . $post->ID, 'bre_geo_nonce' );
		?>
		<div id="bre-geo-box" data-post-id="<?php echo esc_attr( $post->ID ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'bre_admin' ) ); ?>">

			<p style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
				<label>
					<input type="checkbox" name="bre_geo_enabled" value="1"
						<?php checked( $enabled, '1' ); ?>>
					<?php esc_html_e( 'GEO-Block für diesen Beitrag aktiv', 'bavarian-rank-engine' ); ?>
				</label>
				<label>
					<input type="checkbox" name="bre_geo_lock" value="1" id="bre-geo-lock"
						<?php checked( $lock, true ); ?>>
					<?php esc_html_e( 'Auto-Regeneration sperren', 'bavarian-rank-engine' ); ?>
				</label>
				<?php if ( $generated_at ) : ?>
				<span style="font-size:11px;color:#666;">
					<?php
					// translators: %s = human-readable date
					printf( esc_html__( 'Generiert: %s', 'bavarian-rank-engine' ), esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', (int) $generated_at ) ) );
					?>
				</span>
				<?php endif; ?>
			</p>

			<?php if ( $has_api_key ) : ?>
			<p>
				<button type="button" class="button" id="bre-geo-generate">
					<?php
					empty( $meta['summary'] )
						? esc_html_e( 'Jetzt generieren', 'bavarian-rank-engine' )
						: esc_html_e( 'Neu generieren', 'bavarian-rank-engine' );
					?>
				</button>
				<?php if ( ! empty( $meta['summary'] ) ) : ?>
				<button type="button" class="button" id="bre-geo-clear" style="margin-left:6px;">
					<?php esc_html_e( 'Leeren', 'bavarian-rank-engine' ); ?>
				</button>
				<?php endif; ?>
				<span id="bre-geo-status" style="margin-left:10px;font-size:12px;"></span>
			</p>
			<?php endif; ?>

			<p style="margin-bottom:4px;">
				<label for="bre-geo-summary"><strong><?php esc_html_e( 'Kurzfassung', 'bavarian-rank-engine' ); ?></strong></label>
			</p>
			<textarea id="bre-geo-summary" name="bre_geo_summary" rows="3"
				style="width:100%;box-sizing:border-box;"><?php echo esc_textarea( $meta['summary'] ); ?></textarea>

			<p style="margin-bottom:4px;margin-top:10px;">
				<label for="bre-geo-bullets"><strong><?php esc_html_e( 'Kernaussagen', 'bavarian-rank-engine' ); ?></strong></label>
				<span style="font-size:11px;color:#666;margin-left:8px;"><?php esc_html_e( '(eine pro Zeile)', 'bavarian-rank-engine' ); ?></span>
			</p>
			<textarea id="bre-geo-bullets" name="bre_geo_bullets" rows="5"
				style="width:100%;box-sizing:border-box;"><?php echo esc_textarea( implode( "\n", $meta['bullets'] ) ); ?></textarea>

			<p style="margin-bottom:4px;margin-top:10px;">
				<label for="bre-geo-faq"><strong><?php esc_html_e( 'FAQ', 'bavarian-rank-engine' ); ?></strong></label>
				<span style="font-size:11px;color:#666;margin-left:8px;"><?php esc_html_e( '(Format: Frage? | Antwort — eine pro Zeile)', 'bavarian-rank-engine' ); ?></span>
			</p>
			<textarea id="bre-geo-faq" name="bre_geo_faq" rows="4"
				style="width:100%;box-sizing:border-box;">
				<?php
				$faq_lines = array_map(
					function ( $item ) {
						return ( $item['q'] ?? '' ) . ' | ' . ( $item['a'] ?? '' );
					},
					$meta['faq']
				);
															echo esc_textarea( implode( "\n", $faq_lines ) );
				?>
			</textarea>

			<?php if ( $settings['allow_prompt_addon'] ) : ?>
			<p style="margin-bottom:4px;margin-top:10px;">
				<label for="bre-geo-addon"><strong><?php esc_html_e( 'Prompt-Zusatz (optional)', 'bavarian-rank-engine' ); ?></strong></label>
			</p>
			<textarea id="bre-geo-addon" name="bre_geo_prompt_addon" rows="2"
				style="width:100%;box-sizing:border-box;"><?php echo esc_textarea( $prompt_addon ); ?></textarea>
			<?php endif; ?>
		</div>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['bre_geo_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bre_geo_nonce'] ) ), 'bre_geo_save_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Per-post enabled flag ('' = follow global, '1' = on, '0' = off)
		$enabled = isset( $_POST['bre_geo_enabled'] ) ? '1' : '0';
		update_post_meta( $post_id, GeoBlock::META_ENABLED, $enabled );

		$lock = isset( $_POST['bre_geo_lock'] ) ? '1' : '';
		update_post_meta( $post_id, GeoBlock::META_LOCK, $lock );

		// Manual field edits
		$summary = sanitize_text_field( wp_unslash( $_POST['bre_geo_summary'] ?? '' ) );
		update_post_meta( $post_id, GeoBlock::META_SUMMARY, $summary );

		$raw_bullets = sanitize_textarea_field( wp_unslash( $_POST['bre_geo_bullets'] ?? '' ) );
		$bullets     = array_values( array_filter( array_map( 'trim', explode( "\n", $raw_bullets ) ) ) );
		update_post_meta( $post_id, GeoBlock::META_BULLETS, wp_json_encode( $bullets ) );

		$raw_faq = sanitize_textarea_field( wp_unslash( $_POST['bre_geo_faq'] ?? '' ) );
		$faq     = array();
		foreach ( array_filter( array_map( 'trim', explode( "\n", $raw_faq ) ) ) as $line ) {
			$parts = explode( '|', $line, 2 );
			if ( count( $parts ) === 2 ) {
				$faq[] = array(
					'q' => trim( $parts[0] ),
					'a' => trim( $parts[1] ),
				);
			}
		}
		update_post_meta( $post_id, GeoBlock::META_FAQ, wp_json_encode( $faq ) );

		if ( isset( $_POST['bre_geo_prompt_addon'] ) ) {
			update_post_meta( $post_id, GeoBlock::META_ADDON, sanitize_textarea_field( wp_unslash( $_POST['bre_geo_prompt_addon'] ) ) );
		}
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_script(
			'bre-geo-editor',
			BRE_URL . 'assets/geo-editor.js',
			array( 'jquery' ),
			BRE_VERSION,
			true
		);
	}

	public function ajax_generate(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
		}

		$post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( __( 'Post not found.', 'bavarian-rank-engine' ) );
		}

		$geo = new GeoBlock();
		if ( $geo->generate( $post_id, true ) ) {
			$meta = GeoBlock::getMeta( $post_id );
			wp_send_json_success(
				array(
					'summary' => $meta['summary'],
					'bullets' => $meta['bullets'],
					'faq'     => $meta['faq'],
				)
			);
		} else {
			wp_send_json_error( __( 'Generierung fehlgeschlagen. API-Key und Provider-Einstellungen prüfen.', 'bavarian-rank-engine' ) );
		}
	}

	public function ajax_clear(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
		}

		$post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		delete_post_meta( $post_id, GeoBlock::META_SUMMARY );
		delete_post_meta( $post_id, GeoBlock::META_BULLETS );
		delete_post_meta( $post_id, GeoBlock::META_FAQ );
		delete_post_meta( $post_id, GeoBlock::META_GENERATED );
		wp_send_json_success();
	}
}
