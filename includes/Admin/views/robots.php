<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'robots.txt — AI Bots', 'bavarian-rank-engine' ); ?></h1>
	<p>
		<?php esc_html_e( 'Block known AI bots for this site.', 'bavarian-rank-engine' ); ?>
		<strong><?php esc_html_e( 'Note: Bots are not required to comply.', 'bavarian-rank-engine' ); ?></strong>
	</p>

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
							<?php checked( in_array( $bot_key, $settings['blocked_bots'], true ) ); ?>>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Settings', 'bavarian-rank-engine' ) ); ?>
	</form>

	<p>
		<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'View current robots.txt →', 'bavarian-rank-engine' ); ?>
		</a>
	</p>
</div>
