<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeoWidget {
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_boxes(): void {
		$settings   = SettingsPage::getSettings();
		$post_types = $settings['meta_post_types'] ?? array( 'post', 'page' );
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'bre_seo_widget',
				__( 'SEO Analyse (BRE)', 'bavarian-rank-engine' ),
				array( $this, 'render' ),
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render( \WP_Post $post ): void {
		$title_len = mb_strlen( $post->post_title );
		?>
		<div id="bre-seo-widget" data-site-url="<?php echo esc_attr( home_url() ); ?>">
			<table style="width:100%;border-collapse:collapse;font-size:12px;line-height:1.8;">
				<tr>
					<td style="color:#888;"><?php esc_html_e( 'Titel:', 'bavarian-rank-engine' ); ?></td>
					<td id="bre-title-stat" style="text-align:right;font-weight:bold;">
						<?php echo esc_html( $title_len ); ?> / 60
					</td>
				</tr>
				<tr>
					<td style="color:#888;"><?php esc_html_e( 'Wörter:', 'bavarian-rank-engine' ); ?></td>
					<td id="bre-words-stat" style="text-align:right;">—</td>
				</tr>
				<tr>
					<td style="color:#888;"><?php esc_html_e( 'Lesezeit:', 'bavarian-rank-engine' ); ?></td>
					<td id="bre-read-stat" style="text-align:right;">—</td>
				</tr>
			</table>
			<hr style="margin:8px 0;border:none;border-top:1px solid #eee;">
			<strong style="font-size:11px;color:#555;"><?php esc_html_e( 'Überschriften', 'bavarian-rank-engine' ); ?></strong>
			<div id="bre-headings-stat" style="font-size:11px;margin-top:4px;color:#333;">—</div>
			<hr style="margin:8px 0;border:none;border-top:1px solid #eee;">
			<strong style="font-size:11px;color:#555;"><?php esc_html_e( 'Links', 'bavarian-rank-engine' ); ?></strong>
			<div id="bre-links-stat" style="font-size:11px;margin-top:4px;color:#333;">—</div>
			<div id="bre-seo-warnings" style="margin-top:8px;font-size:11px;color:#d63638;line-height:1.6;"></div>
		</div>
		<?php
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_script(
			'bre-seo-widget',
			BRE_URL . 'assets/seo-widget.js',
			array( 'jquery' ),
			BRE_VERSION,
			true
		);
	}
}
