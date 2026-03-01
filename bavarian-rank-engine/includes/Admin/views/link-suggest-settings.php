<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; } ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'Link Suggestions', 'bavarian-rank-engine' ); ?></h1>

	<?php settings_errors( 'bre_link_suggest' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'bre_link_suggest' ); ?>

		<h2><?php esc_html_e( 'Analysis Trigger', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'When to analyse', 'bavarian-rank-engine' ); ?></th>
				<td>
					<label>
						<input type="radio"
							name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[trigger]"
							value="manual" <?php checked( $settings['trigger'], 'manual' ); ?>>
						<?php esc_html_e( 'Manual only (button)', 'bavarian-rank-engine' ); ?>
					</label><br>
					<label>
						<input type="radio"
							name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[trigger]"
							value="save" <?php checked( $settings['trigger'], 'save' ); ?>>
						<?php esc_html_e( 'On post save', 'bavarian-rank-engine' ); ?>
					</label><br>
					<label>
						<input type="radio"
							name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[trigger]"
							value="interval" <?php checked( $settings['trigger'], 'interval' ); ?>>
						<?php esc_html_e( 'Every', 'bavarian-rank-engine' ); ?>
						<input type="number" min="1" max="60"
							name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[interval_min]"
							value="<?php echo esc_attr( $settings['interval_min'] ); ?>"
							style="width:55px;">
						<?php esc_html_e( 'minutes', 'bavarian-rank-engine' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Exclude Posts / Pages', 'bavarian-rank-engine' ); ?></h2>
		<p class="description"><?php esc_html_e( 'These posts will never appear as link suggestions (e.g. Imprint, Contact, Terms).', 'bavarian-rank-engine' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Excluded', 'bavarian-rank-engine' ); ?></th>
				<td>
					<div id="bre-ls-excluded-list">
						<?php
						foreach ( $settings['excluded_posts'] as $pid ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$ptitle = get_the_title( $pid ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							if ( ! $ptitle ) {
								continue;
							}
							?>
						<span class="bre-ls-tag" style="display:inline-flex;align-items:center;gap:4px;background:#e0e0e0;padding:2px 8px;border-radius:3px;margin:2px;">
							<?php echo esc_html( $ptitle ); ?>
							<input type="hidden"
								name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[excluded_posts][]"
								value="<?php echo esc_attr( $pid ); ?>">
							<button type="button" class="bre-ls-remove" style="background:none;border:none;cursor:pointer;color:#555;" aria-label="<?php esc_attr_e( 'Remove', 'bavarian-rank-engine' ); ?>">&#10005;</button>
						</span>
						<?php endforeach; ?>
					</div>
					<input type="search" id="bre-ls-exclude-search"
						placeholder="<?php esc_attr_e( 'Search posts…', 'bavarian-rank-engine' ); ?>"
						style="width:300px;margin-top:6px;">
					<div id="bre-ls-exclude-results"
						style="display:none;border:1px solid #ddd;background:#fff;max-height:200px;overflow-y:auto;width:300px;position:absolute;z-index:100;"></div>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Prioritise Posts / Pages', 'bavarian-rank-engine' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Boosted posts rank higher when thematically relevant. A boost of 1.0 = no change.', 'bavarian-rank-engine' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Boosted', 'bavarian-rank-engine' ); ?></th>
				<td>
					<div id="bre-ls-boosted-list">
						<?php
						foreach ( $settings['boosted_posts'] as $idx => $entry ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$ptitle = get_the_title( $entry['id'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							if ( ! $ptitle ) {
								continue;
							}
							?>
						<div class="bre-ls-boost-row" style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
							<span>&#9733; <?php echo esc_html( $ptitle ); ?></span>
							<input type="hidden"
								name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[boosted_posts][<?php echo esc_attr( (string) (int) $idx ); ?>][id]"
								value="<?php echo esc_attr( $entry['id'] ); ?>">
							<label><?php esc_html_e( 'Boost:', 'bavarian-rank-engine' ); ?>
								<input type="number" step="0.1" min="1" max="10"
									name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[boosted_posts][<?php echo esc_attr( (string) (int) $idx ); ?>][boost]"
									value="<?php echo esc_attr( $entry['boost'] ); ?>"
									style="width:60px;">
							</label>
							<button type="button" class="button bre-ls-remove"><?php esc_html_e( 'Remove', 'bavarian-rank-engine' ); ?></button>
						</div>
						<?php endforeach; ?>
					</div>
					<input type="search" id="bre-ls-boost-search"
						placeholder="<?php esc_attr_e( 'Search posts…', 'bavarian-rank-engine' ); ?>"
						style="width:300px;margin-top:6px;">
					<div id="bre-ls-boost-results"
						style="display:none;border:1px solid #ddd;background:#fff;max-height:200px;overflow-y:auto;width:300px;position:absolute;z-index:100;"></div>
				</td>
			</tr>
		</table>

		<?php if ( $has_ai ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
		<h2><?php esc_html_e( 'AI Options (optional)', 'bavarian-rank-engine' ); ?></h2>
		<p class="description"><?php esc_html_e( 'AI is connected — these settings control how many candidates are sent for semantic analysis.', 'bavarian-rank-engine' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Candidates to AI', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="number" min="1" max="50"
						name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[ai_candidates]"
						value="<?php echo esc_attr( $settings['ai_candidates'] ); ?>"
						style="width:70px;">
					<p class="description"><?php esc_html_e( 'How many pre-scored candidates are passed to the AI (max 50).', 'bavarian-rank-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Max output tokens', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="number" min="100" max="2000"
						name="<?php echo esc_attr( \BavarianRankEngine\Features\LinkSuggest::OPTION_KEY ); ?>[ai_max_tokens]"
						value="<?php echo esc_attr( $settings['ai_max_tokens'] ); ?>"
						style="width:70px;">
				</td>
			</tr>
		</table>
		<?php endif; ?>

		<?php submit_button( __( 'Save Settings', 'bavarian-rank-engine' ) ); ?>
	</form>

	<p class="bre-footer">
		Bavarian Rank Engine <?php echo esc_html( BRE_VERSION ); ?> &mdash;
		<?php esc_html_e( 'developed with', 'bavarian-rank-engine' ); ?> ♥
		<a href="https://donau2space.de" target="_blank" rel="noopener">Donau2Space.de</a>
	</p>
</div>
