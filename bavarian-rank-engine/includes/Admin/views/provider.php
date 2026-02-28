<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'AI Provider', 'bavarian-rank-engine' ); ?></h1>

	<?php settings_errors( 'bre_provider' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'bre_provider' ); ?>

		<div class="bre-ai-toggle-wrap">
			<label style="font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
				<input type="checkbox" name="bre_settings[ai_enabled]" value="1" id="bre-ai-enabled"
					   <?php checked( $settings['ai_enabled'] ?? false, true ); ?>>
				<?php esc_html_e( 'Enable AI generation', 'bavarian-rank-engine' ); ?>
			</label>
			<p class="bre-ai-cost-notice">
				&#9888; <?php esc_html_e( 'This feature will incur costs with your AI provider. Make sure you understand the pricing before entering an API key.', 'bavarian-rank-engine' ); ?>
			</p>
		</div>
		<div id="bre-ai-fields">

		<h2><?php esc_html_e( 'AI Provider', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active Provider', 'bavarian-rank-engine' ); ?></th>
				<td>
					<select name="bre_settings[provider]" id="bre-provider">
						<?php foreach ( $providers as $id => $provider ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
						<option value="<?php echo esc_attr( $id ); ?>"
							<?php selected( $settings['provider'], $id ); ?>>
							<?php echo esc_html( $provider->getName() ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<?php foreach ( $providers as $id => $provider ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
			<tr class="bre-provider-row" data-provider="<?php echo esc_attr( $id ); ?>">
				<th scope="row"><?php echo esc_html( $provider->getName() ); ?> <?php esc_html_e( 'API Key', 'bavarian-rank-engine' ); ?></th>
				<td>
					<?php if ( ! empty( $masked_keys[ $id ] ) ) : ?>
					<span class="bre-key-saved">
						<?php esc_html_e( 'Saved:', 'bavarian-rank-engine' ); ?> <code><?php echo esc_html( $masked_keys[ $id ] ); ?></code>
					</span><br>
					<?php endif; ?>
					<input type="password"
							name="bre_settings[api_keys][<?php echo esc_attr( $id ); ?>]"
							value=""
							placeholder="<?php echo ! empty( $masked_keys[ $id ] ) ? esc_attr__( 'Enter new key to overwrite', 'bavarian-rank-engine' ) : esc_attr__( 'Enter API key', 'bavarian-rank-engine' ); ?>"
							class="regular-text"
							autocomplete="new-password">
					<button type="button" class="button bre-test-btn" data-provider="<?php echo esc_attr( $id ); ?>">
						<?php esc_html_e( 'Test connection', 'bavarian-rank-engine' ); ?>
					</button>
					<span class="bre-test-result" id="test-result-<?php echo esc_attr( $id ); ?>"></span>
					<br><br>
					<label><?php esc_html_e( 'Model:', 'bavarian-rank-engine' ); ?></label>
					<select name="bre_settings[models][<?php echo esc_attr( $id ); ?>]">
						<?php
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
						$saved_model = $settings['models'][ $id ] ?? array_key_first( $provider->getModels() );
						foreach ( $provider->getModels() as $model_id => $model_label ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							?>
						<option value="<?php echo esc_attr( $model_id ); ?>"
							<?php selected( $saved_model, $model_id ); ?>>
							<?php echo esc_html( $model_label ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				$pricing_url = $pricing_urls[ $id ] ?? '';
				if ( $pricing_url ) :
					?>
				<p style="margin-top:8px;">
					<a href="<?php echo esc_url( $pricing_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'View current pricing →', 'bavarian-rank-engine' ); ?>
					</a>
				</p>
<?php endif; ?>
				<p style="margin-top:12px;"><strong><?php esc_html_e( 'Cost per 1 million tokens (for the Bulk cost overview):', 'bavarian-rank-engine' ); ?></strong></p>
				<?php
				foreach ( $provider->getModels() as $model_id => $model_label ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$saved_costs = $settings['costs'][ $id ][ $model_id ] ?? array();
					?>
				<div style="margin-bottom:6px;display:flex;align-items:center;gap:12px;">
					<label style="min-width:180px;font-size:12px;"><?php echo esc_html( $model_label ); ?>:</label>
					<span>Input $<input type="number" step="0.0001" min="0"
						name="bre_settings[costs][<?php echo esc_attr( $id ); ?>][<?php echo esc_attr( $model_id ); ?>][input]"
						value="<?php echo esc_attr( $saved_costs['input'] ?? '' ); ?>"
						placeholder="z.B. 0.15" style="width:75px;"> / 1M</span>
					<span>Output $<input type="number" step="0.0001" min="0"
						name="bre_settings[costs][<?php echo esc_attr( $id ); ?>][<?php echo esc_attr( $model_id ); ?>][output]"
						value="<?php echo esc_attr( $saved_costs['output'] ?? '' ); ?>"
						placeholder="z.B. 0.60" style="width:75px;"> / 1M</span>
				</div>
<?php endforeach; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		</div><!-- /#bre-ai-fields -->

		<?php submit_button( __( 'Save Settings', 'bavarian-rank-engine' ) ); ?>
	</form>

	<p class="bre-footer">
		Bavarian Rank Engine <?php echo esc_html( BRE_VERSION ); ?> &mdash;
		<?php esc_html_e( 'developed with', 'bavarian-rank-engine' ); ?> ♥
		<a href="https://donau2space.de" target="_blank" rel="noopener">Donau2Space.de</a>
	</p>
</div>
