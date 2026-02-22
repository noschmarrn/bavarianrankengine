<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'llms.txt Configuration', 'bavarian-rank-engine' ); ?></h1>

	<div style="margin-bottom:20px;">
		<button id="bre-llms-clear-cache" class="button">
			<?php esc_html_e( 'Clear llms.txt Cache', 'bavarian-rank-engine' ); ?>
		</button>
		<span id="bre-cache-result" style="margin-left:10px;color:#46b450;"></span>
		<script>
		jQuery(document).ready(function($){
			$('#bre-llms-clear-cache').on('click', function(){
				$.post(ajaxurl, {
					action: 'bre_llms_clear_cache',
					nonce: '<?php echo esc_js( wp_create_nonce( 'bre_admin' ) ); ?>'
				}).done(function(res){
					$('#bre-cache-result').text(res.success ? res.data : <?php echo wp_json_encode( __( 'Error.', 'bavarian-rank-engine' ) ); ?>);
					setTimeout(function(){ $('#bre-cache-result').text(''); }, 3000);
				});
			});
		});
		</script>
	</div>

	<?php settings_errors( 'bre_llms' ); ?>

	<p>
		<?php esc_html_e( 'Your llms.txt will be served at:', 'bavarian-rank-engine' ); ?>
		<a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" rel="noopener">
			<?php echo esc_html( $llms_url ); ?>
		</a>
		<?php if ( $settings['enabled'] ) : ?>
			<span style="color:green;margin-left:8px;">&#9679; <?php esc_html_e( 'Active', 'bavarian-rank-engine' ); ?></span>
		<?php else : ?>
			<span style="color:#999;margin-left:8px;">&#9679; <?php esc_html_e( 'Inactive', 'bavarian-rank-engine' ); ?></span>
		<?php endif; ?>
	</p>

	<form method="post" action="options.php">
		<?php settings_fields( 'bre_llms' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable llms.txt', 'bavarian-rank-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bre_llms_settings[enabled]" value="1"
								<?php checked( $settings['enabled'], true ); ?>>
						<?php esc_html_e( 'Serve llms.txt at', 'bavarian-rank-engine' ); ?>
						<code>/llms.txt</code>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Title', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="text"
							name="bre_llms_settings[title]"
							value="<?php echo esc_attr( $settings['title'] ); ?>"
							class="regular-text"
							placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Appears as the # heading in llms.txt', 'bavarian-rank-engine' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Description (before links)', 'bavarian-rank-engine' ); ?></th>
				<td>
					<textarea name="bre_llms_settings[description_before]"
								rows="4" class="large-text"><?php echo esc_textarea( $settings['description_before'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Text shown after the title, before featured links.', 'bavarian-rank-engine' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Featured Links', 'bavarian-rank-engine' ); ?></th>
				<td>
					<textarea name="bre_llms_settings[custom_links]"
								rows="5" class="large-text"><?php echo esc_textarea( $settings['custom_links'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Important links to highlight for AI models. One per line.', 'bavarian-rank-engine' ); ?>
						<?php esc_html_e( 'Markdown format recommended:', 'bavarian-rank-engine' ); ?>
						<code>- [Link Name](https://url.com)</code>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Post Types', 'bavarian-rank-engine' ); ?></th>
				<td>
					<?php foreach ( $post_types as $pt_slug => $pt_obj ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
					<label style="margin-right:15px;">
						<input type="checkbox"
								name="bre_llms_settings[post_types][]"
								value="<?php echo esc_attr( $pt_slug ); ?>"
								<?php checked( in_array( $pt_slug, $settings['post_types'], true ), true ); ?>>
						<?php echo esc_html( $pt_obj->labels->singular_name ); ?>
					</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Which post types to include in the content list.', 'bavarian-rank-engine' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Max. links per page', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="number" name="bre_llms_settings[max_links]"
							value="<?php echo esc_attr( $settings['max_links'] ?? 500 ); ?>"
							min="50" max="5000" style="width:80px;">
					<p class="description">
						<?php esc_html_e( 'With more posts, llms-2.txt, llms-3.txt etc. are created and linked automatically.', 'bavarian-rank-engine' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Description (after content)', 'bavarian-rank-engine' ); ?></th>
				<td>
					<textarea name="bre_llms_settings[description_after]"
								rows="4" class="large-text"><?php echo esc_textarea( $settings['description_after'] ); ?></textarea>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Footer Description', 'bavarian-rank-engine' ); ?></th>
				<td>
					<textarea name="bre_llms_settings[description_footer]"
								rows="4" class="large-text"><?php echo esc_textarea( $settings['description_footer'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Appears at the end of llms.txt after a --- separator.', 'bavarian-rank-engine' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save llms.txt Settings', 'bavarian-rank-engine' ) ); ?>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Preview', 'bavarian-rank-engine' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'After saving, visit your llms.txt to verify:', 'bavarian-rank-engine' ); ?>
		<a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" rel="noopener">
			<?php echo esc_html( $llms_url ); ?>
		</a>
	</p>
	<p class="description" style="color:#d63638;">
		<?php esc_html_e( 'Note: If the URL shows a 404, go to Settings → Permalinks and click Save to flush rewrite rules.', 'bavarian-rank-engine' ); ?>
	</p>
</div>
