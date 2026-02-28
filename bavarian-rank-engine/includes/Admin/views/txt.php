<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'TXT Files', 'bavarian-rank-engine' ); ?></h1>

	<nav class="nav-tab-wrapper" style="margin-bottom:0;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-txt&tab=llms' ) ); ?>"
			class="nav-tab <?php echo $active_tab === 'llms' ? 'nav-tab-active' : ''; ?>">
			llms.txt
			<?php if ( $llms_settings['enabled'] ) : ?>
				<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#46b450;margin-left:5px;vertical-align:middle;"></span>
			<?php else : ?>
				<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ccc;margin-left:5px;vertical-align:middle;"></span>
			<?php endif; ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-txt&tab=robots' ) ); ?>"
			class="nav-tab <?php echo $active_tab === 'robots' ? 'nav-tab-active' : ''; ?>">
			robots.txt
			<?php $bre_blocked_count = count( $robots_settings['blocked_bots'] ?? array() ); ?>
			<?php if ( $bre_blocked_count > 0 ) : ?>
				<span style="display:inline-block;background:#2271b1;color:#fff;border-radius:10px;font-size:11px;padding:1px 7px;margin-left:6px;vertical-align:middle;line-height:1.6;">
					<?php echo esc_html( $bre_blocked_count ); ?>
				</span>
			<?php endif; ?>
		</a>
	</nav>

	<div style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:20px 24px 0;margin-bottom:20px;">

	<?php if ( $active_tab === 'llms' ) : ?>

		<?php settings_errors( 'bre_llms' ); ?>

		<div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap;">
			<div>
				<?php esc_html_e( 'URL:', 'bavarian-rank-engine' ); ?>
				<a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( $llms_url ); ?>
				</a>
			</div>
			<button id="bre-llms-clear-cache" class="button button-small">
				<?php esc_html_e( 'Clear Cache', 'bavarian-rank-engine' ); ?>
			</button>
			<span id="bre-cache-result" style="color:#46b450;"></span>
		</div>

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

		<form method="post" action="options.php">
			<?php settings_fields( 'bre_llms' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable llms.txt', 'bavarian-rank-engine' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bre_llms_settings[enabled]" value="1"
									<?php checked( $llms_settings['enabled'], true ); ?>>
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
								value="<?php echo esc_attr( $llms_settings['title'] ); ?>"
								class="regular-text"
								placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Appears as the # heading in llms.txt', 'bavarian-rank-engine' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Description (before links)', 'bavarian-rank-engine' ); ?></th>
					<td>
						<textarea name="bre_llms_settings[description_before]"
									rows="4" class="large-text"><?php echo esc_textarea( $llms_settings['description_before'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Text shown after the title, before featured links.', 'bavarian-rank-engine' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Featured Links', 'bavarian-rank-engine' ); ?></th>
					<td>
						<textarea name="bre_llms_settings[custom_links]"
									rows="5" class="large-text"><?php echo esc_textarea( $llms_settings['custom_links'] ); ?></textarea>
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
									<?php checked( in_array( $pt_slug, $llms_settings['post_types'], true ), true ); ?>>
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
								value="<?php echo esc_attr( $llms_settings['max_links'] ?? 500 ); ?>"
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
									rows="4" class="large-text"><?php echo esc_textarea( $llms_settings['description_after'] ); ?></textarea>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Footer Description', 'bavarian-rank-engine' ); ?></th>
					<td>
						<textarea name="bre_llms_settings[description_footer]"
									rows="4" class="large-text"><?php echo esc_textarea( $llms_settings['description_footer'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Appears at the end of llms.txt after a --- separator.', 'bavarian-rank-engine' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save llms.txt Settings', 'bavarian-rank-engine' ) ); ?>
		</form>

		<p class="description" style="padding-bottom:4px;">
			<?php esc_html_e( 'Note: If the URL shows a 404, go to Settings → Permalinks and click Save to flush rewrite rules.', 'bavarian-rank-engine' ); ?>
		</p>

	<?php else : ?>

		<?php settings_errors( 'bre_robots' ); ?>

		<div style="margin-bottom:18px;">
			<p style="margin:0 0 6px;">
				<?php esc_html_e( 'Block known AI bots for this site.', 'bavarian-rank-engine' ); ?>
				<strong><?php esc_html_e( 'Note: Bots are not required to comply.', 'bavarian-rank-engine' ); ?></strong>
			</p>
			<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View current robots.txt →', 'bavarian-rank-engine' ); ?>
			</a>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'bre_robots' ); ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User-Agent', 'bavarian-rank-engine' ); ?></th>
						<th><?php esc_html_e( 'Description', 'bavarian-rank-engine' ); ?></th>
						<th style="width:80px;text-align:center;"><?php esc_html_e( 'Block', 'bavarian-rank-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( \BavarianRankEngine\Features\RobotsTxt::KNOWN_BOTS as $bot_key => $bot_label ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
				<tr>
					<td><code><?php echo esc_html( $bot_key ); ?></code></td>
					<td><?php echo esc_html( $bot_label ); ?></td>
					<td style="text-align:center;">
						<input type="checkbox"
								name="bre_robots_settings[blocked_bots][]"
								value="<?php echo esc_attr( $bot_key ); ?>"
								<?php checked( in_array( $bot_key, $robots_settings['blocked_bots'], true ) ); ?>>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Save robots.txt Settings', 'bavarian-rank-engine' ) ); ?>
		</form>

	<?php endif; ?>

	</div>

	<p class="bre-footer">
		Bavarian Rank Engine <?php echo esc_html( BRE_VERSION ); ?> &mdash;
		<?php esc_html_e( 'developed with', 'bavarian-rank-engine' ); ?> ♥
		<a href="https://donau2space.de" target="_blank" rel="noopener">Donau2Space.de</a>
	</p>
</div>
