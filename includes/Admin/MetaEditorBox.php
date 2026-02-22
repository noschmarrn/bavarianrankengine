<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BavarianRankEngine\Features\MetaGenerator;

class MetaEditorBox {
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_bre_regen_meta', array( $this, 'ajax_regen' ) );
	}

	public function add_boxes(): void {
		$settings   = SettingsPage::getSettings();
		$post_types = $settings['meta_post_types'] ?? array( 'post', 'page' );
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'bre_meta_box',
				__( 'Meta Description (BRE)', 'bavarian-rank-engine' ),
				array( $this, 'render' ),
				$pt,
				'normal',
				'high'
			);
		}
	}

	public function render( \WP_Post $post ): void {
		$description = get_post_meta( $post->ID, '_bre_meta_description', true ) ?: '';
		$source      = get_post_meta( $post->ID, '_bre_meta_source', true ) ?: 'none';

		$source_labels = array(
			'ai'       => __( 'KI generiert', 'bavarian-rank-engine' ),
			'fallback' => __( 'Fallback (erster Absatz)', 'bavarian-rank-engine' ),
			'manual'   => __( 'Manuell bearbeitet', 'bavarian-rank-engine' ),
			'none'     => __( 'Noch nicht generiert', 'bavarian-rank-engine' ),
		);

		$settings = SettingsPage::getSettings();
		$api_key  = $settings['api_keys'][ $settings['provider'] ] ?? '';
		$has_key  = ! empty( $api_key );

		wp_nonce_field( 'bre_save_meta_' . $post->ID, 'bre_meta_nonce' );
		?>
		<p>
			<span style="display:inline-block;background:#eee;padding:2px 8px;border-radius:3px;font-size:11px;color:#555;">
				<?php echo esc_html( $source_labels[ $source ] ?? $source ); ?>
			</span>
		</p>
		<textarea id="bre-meta-description"
					name="bre_meta_description"
					rows="3"
					maxlength="160"
					style="width:100%;box-sizing:border-box;"
		><?php echo esc_textarea( $description ); ?></textarea>
		<p style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
			<span id="bre-meta-count" style="font-size:11px;color:#666;">
				<?php echo esc_html( mb_strlen( $description ) ); ?> / 160
			</span>
			<?php if ( $has_key ) : ?>
			<button type="button"
					id="bre-regen-meta"
					class="button button-small"
					data-post-id="<?php echo esc_attr( $post->ID ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'bre_admin' ) ); ?>">
				<?php esc_html_e( 'Mit KI neu generieren', 'bavarian-rank-engine' ); ?>
			</button>
			<?php endif; ?>
		</p>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['bre_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bre_meta_nonce'] ) ), 'bre_save_meta_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['bre_meta_description'] ) ) {
			return;
		}

		$gen = new MetaGenerator();
		$gen->saveMeta(
			$post_id,
			sanitize_textarea_field( wp_unslash( $_POST['bre_meta_description'] ) ),
			'manual'
		);
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_script(
			'bre-editor-meta',
			BRE_URL . 'assets/editor-meta.js',
			array( 'jquery' ),
			BRE_VERSION,
			true
		);
	}

	public function ajax_regen(): void {
		check_ajax_referer( 'bre_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		$post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			wp_send_json_error( 'Post not found' );
			return;
		}

		$settings = SettingsPage::getSettings();
		$gen      = new MetaGenerator();

		try {
			$desc = $gen->generate( $post, $settings );
			$gen->saveMeta( $post_id, $desc, 'ai' );
			wp_send_json_success( array( 'description' => $desc ) );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
