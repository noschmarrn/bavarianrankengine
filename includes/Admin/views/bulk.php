<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'Bulk Generator', 'bavarian-rank-engine' ); ?></h1>

	<div id="bre-lock-warning" style="display:none;background:#fcf8e3;border:1px solid #faebcc;padding:10px 15px;margin-bottom:15px;border-radius:3px;color:#8a6d3b;"></div>

	<p><?php esc_html_e( 'Generates meta descriptions for posts without an existing meta description.', 'bavarian-rank-engine' ); ?></p>

	<div id="bre-bulk-stats" style="background:#fff;padding:15px;border:1px solid #ddd;margin-bottom:20px;">
		<em><?php esc_html_e( 'Loading statistics…', 'bavarian-rank-engine' ); ?></em>
	</div>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Active Provider', 'bavarian-rank-engine' ); ?></th>
			<td>
				<select id="bre-bulk-provider">
					<?php foreach ( $providers as $id => $provider ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
					<option value="<?php echo esc_attr( $id ); ?>"
						<?php selected( $settings['provider'], $id ); ?>>
						<?php echo esc_html( $provider->getName() ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Model:', 'bavarian-rank-engine' ); ?></th>
			<td>
				<select id="bre-bulk-model">
					<?php
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$active_provider = $registry->get( $settings['provider'] );
					if ( $active_provider ) :
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
						$saved_model = $settings['models'][ $settings['provider'] ] ?? array_key_first( $active_provider->getModels() );
						foreach ( $active_provider->getModels() as $mid => $mlabel ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							?>
					<option value="<?php echo esc_attr( $mid ); ?>"
							<?php selected( $saved_model, $mid ); ?>>
							<?php echo esc_html( $mlabel ); ?>
					</option>
							<?php
					endforeach;
endif;
					?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Max. posts this run', 'bavarian-rank-engine' ); ?></th>
			<td>
				<input type="number" id="bre-bulk-limit" value="20" min="1" max="500">
				<p class="description" id="bre-cost-estimate"></p>
			</td>
		</tr>
	</table>

	<p>
		<button id="bre-bulk-start" class="button button-primary"><?php esc_html_e( 'Start Bulk Run', 'bavarian-rank-engine' ); ?></button>
		<button id="bre-bulk-stop" class="button" style="display:none;"><?php esc_html_e( 'Cancel', 'bavarian-rank-engine' ); ?></button>
	</p>

	<div id="bre-progress-wrap" style="display:none;margin:15px 0;">
		<div style="background:#ddd;border-radius:3px;height:20px;width:100%;">
			<div id="bre-progress-bar"
				style="background:#0073aa;height:20px;border-radius:3px;width:0;transition:width .3s;"></div>
		</div>
		<p id="bre-progress-text"><?php esc_html_e( '0 / 0 processed', 'bavarian-rank-engine' ); ?></p>
	</div>

	<div id="bre-bulk-log"
		style="background:#1e1e1e;color:#d4d4d4;padding:15px;font-family:monospace;font-size:12px;max-height:400px;overflow-y:auto;display:none;"></div>

	<div id="bre-failed-summary" style="display:none;background:#fdf2f2;border:1px solid #f5c6cb;padding:10px 15px;margin-top:15px;border-radius:3px;font-size:13px;"></div>
</div>
