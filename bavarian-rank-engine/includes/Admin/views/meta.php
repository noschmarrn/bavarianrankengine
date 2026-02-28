<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'Meta Generator', 'bavarian-rank-engine' ); ?></h1>

	<?php if ( ! $has_ai ) : ?>
	<div class="notice notice-warning inline" style="margin:12px 0;">
		<p>
			<strong><?php esc_html_e( 'No AI provider active.', 'bavarian-rank-engine' ); ?></strong>
			<?php esc_html_e( 'Meta descriptions will use the fallback method (first paragraph of the post) until an API key is configured and AI generation is enabled.', 'bavarian-rank-engine' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-provider' ) ); ?>">
				<?php esc_html_e( 'Configure AI Provider →', 'bavarian-rank-engine' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

	<?php settings_errors( 'bre_meta' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'bre_meta' ); ?>

		<h2><?php esc_html_e( 'Meta Generator', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto Mode', 'bavarian-rank-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox"
								name="bre_meta_settings[meta_auto_enabled]"
								value="1"
								<?php checked( $settings['meta_auto_enabled'], true ); ?>>
						<?php esc_html_e( 'Automatically generate meta description on publish', 'bavarian-rank-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Post Types', 'bavarian-rank-engine' ); ?></th>
				<td>
					<?php foreach ( $post_types as $pt_slug => $pt_obj ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
					<label style="margin-right:15px;">
						<input type="checkbox"
								name="bre_meta_settings[meta_post_types][]"
								value="<?php echo esc_attr( $pt_slug ); ?>"
								<?php checked( in_array( $pt_slug, $settings['meta_post_types'], true ), true ); ?>>
						<?php echo esc_html( $pt_obj->labels->singular_name ); ?>
					</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Token Mode', 'bavarian-rank-engine' ); ?></th>
				<td>
					<?php if ( ! $has_ai ) : ?>
					<p class="description" style="margin-bottom:6px;color:#996800;">
						<?php esc_html_e( 'Fallback mode active — configure an AI provider to enable AI generation.', 'bavarian-rank-engine' ); ?>
					</p>
					<?php endif; ?>
					<label>
						<input type="radio" name="bre_meta_settings[token_mode]" value="full"
								<?php checked( $settings['token_mode'], 'full' ); ?>>
						<?php esc_html_e( 'Send full article', 'bavarian-rank-engine' ); ?>
					</label>
					&nbsp;&nbsp;
					<label>
						<input type="radio" name="bre_meta_settings[token_mode]" value="limit"
								<?php checked( $settings['token_mode'], 'limit' ); ?>>
						<?php esc_html_e( 'Limit to', 'bavarian-rank-engine' ); ?>
						<input type="number"
								name="bre_meta_settings[token_limit]"
								value="<?php echo esc_attr( $settings['token_limit'] ); ?>"
								min="100" max="8000" style="width:80px;">
						<?php esc_html_e( 'tokens', 'bavarian-rank-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Prompt', 'bavarian-rank-engine' ); ?></th>
				<td>
					<textarea name="bre_meta_settings[prompt]"
								rows="8"
								class="large-text code"><?php echo esc_textarea( $settings['prompt'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Variables:', 'bavarian-rank-engine' ); ?>
						<code>{title}</code>, <code>{content}</code>, <code>{excerpt}</code>, <code>{language}</code><br>
						<button type="button" class="button" id="bre-reset-prompt"><?php esc_html_e( 'Reset prompt', 'bavarian-rank-engine' ); ?></button>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'SEO Widget', 'bavarian-rank-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox"
								name="bre_meta_settings[theme_has_h1]"
								value="1"
								<?php checked( ['theme_has_h1'] ?? true, true ); ?>>
						<?php esc_html_e( 'Theme outputs post title as H1 (suppresses "no H1" warning in editor)', 'bavarian-rank-engine' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Most themes render the post title as an H1 tag on the front end. Enable this to avoid false warnings in the SEO Widget when the content itself contains no H1.', 'bavarian-rank-engine' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'bavarian-rank-engine' ) ); ?>
	</form>

	<p class="bre-footer">
		Bavarian Rank Engine <?php echo esc_html( BRE_VERSION ); ?> &mdash;
		<?php esc_html_e( 'developed with', 'bavarian-rank-engine' ); ?> ♥
		<a href="https://donau2space.de" target="_blank" rel="noopener">Donau2Space.de</a>
	</p>
</div>
