<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'Schema.org', 'bavarian-rank-engine' ); ?></h1>

	<?php settings_errors( 'bre_schema' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'bre_schema' ); ?>

		<h2><?php esc_html_e( 'Schema.org Enhancer (GEO)', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enabled Schema Types', 'bavarian-rank-engine' ); ?></th>
				<td>
					<?php foreach ( $schema_labels as $type => $label ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
					<label style="display:block;margin-bottom:8px;">
						<input type="checkbox"
								name="bre_schema_settings[schema_enabled][]"
								value="<?php echo esc_attr( $type ); ?>"
								<?php checked( in_array( $type, $settings['schema_enabled'], true ), true ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Organization sameAs URLs', 'bavarian-rank-engine' ); ?></th>
				<td>
					<p class="description"><?php esc_html_e( 'One URL per line (Twitter, LinkedIn, GitHub, Facebook…)', 'bavarian-rank-engine' ); ?></p>
					<textarea name="bre_schema_settings[schema_same_as][organization]"
								rows="5"
								class="large-text"><?php echo esc_textarea( implode( "\n", $settings['schema_same_as']['organization'] ?? array() ) ); ?></textarea>
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
