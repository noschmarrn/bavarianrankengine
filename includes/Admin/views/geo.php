<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;}
?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'GEO Schnellüberblick', 'bavarian-rank-engine' ); ?></h1>

	<?php settings_errors( 'bre_geo' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'bre_geo' ); ?>

		<h2><?php esc_html_e( 'Aktivierung', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'GEO Block aktivieren', 'bavarian-rank-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bre_geo_settings[enabled]" value="1"
							<?php checked( $settings['enabled'], true ); ?>>
						<?php esc_html_e( 'Schnellüberblick-Block im Frontend ausgeben', 'bavarian-rank-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Modus', 'bavarian-rank-engine' ); ?></th>
				<td>
					<select name="bre_geo_settings[mode]">
						<option value="auto_on_publish" <?php selected( $settings['mode'], 'auto_on_publish' ); ?>>
							<?php esc_html_e( 'Auto bei Publish/Update (empfohlen)', 'bavarian-rank-engine' ); ?>
						</option>
						<option value="hybrid" <?php selected( $settings['mode'], 'hybrid' ); ?>>
							<?php esc_html_e( 'Hybrid: Auto nur wenn Felder leer', 'bavarian-rank-engine' ); ?>
						</option>
						<option value="manual_only" <?php selected( $settings['mode'], 'manual_only' ); ?>>
							<?php esc_html_e( 'Nur manuell (Editor-Button)', 'bavarian-rank-engine' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Post-Types', 'bavarian-rank-engine' ); ?></th>
				<td>
					<?php foreach ( $post_types as $pt_slug => $pt_obj ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
					<label style="margin-right:15px;">
						<input type="checkbox" name="bre_geo_settings[post_types][]"
							value="<?php echo esc_attr( $pt_slug ); ?>"
							<?php checked( in_array( $pt_slug, $settings['post_types'], true ), true ); ?>>
						<?php echo esc_html( $pt_obj->labels->singular_name ); ?>
					</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Bei Update neu generieren', 'bavarian-rank-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bre_geo_settings[regen_on_update]" value="1"
							<?php checked( $settings['regen_on_update'], true ); ?>>
						<?php esc_html_e( 'Bei jedem Speichern eines publizierten Beitrags neu generieren', 'bavarian-rank-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Wortschwellwert für FAQ', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="number" name="bre_geo_settings[word_threshold]"
						value="<?php echo esc_attr( $settings['word_threshold'] ); ?>"
						min="50" max="2000" style="width:80px;">
					<p class="description">
						<?php esc_html_e( 'Unter dieser Wortanzahl wird keine FAQ generiert. Standard: 350', 'bavarian-rank-engine' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Ausgabe', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Position', 'bavarian-rank-engine' ); ?></th>
				<td>
					<select name="bre_geo_settings[position]">
						<option value="after_first_p" <?php selected( $settings['position'], 'after_first_p' ); ?>>
							<?php esc_html_e( 'Nach dem ersten Absatz (Standard)', 'bavarian-rank-engine' ); ?>
						</option>
						<option value="top" <?php selected( $settings['position'], 'top' ); ?>>
							<?php esc_html_e( 'Anfang des Beitrags', 'bavarian-rank-engine' ); ?>
						</option>
						<option value="bottom" <?php selected( $settings['position'], 'bottom' ); ?>>
							<?php esc_html_e( 'Ende des Beitrags', 'bavarian-rank-engine' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ausgabe-Stil', 'bavarian-rank-engine' ); ?></th>
				<td>
					<select name="bre_geo_settings[output_style]">
						<option value="details_collapsible" <?php selected( $settings['output_style'], 'details_collapsible' ); ?>>
							<?php esc_html_e( 'Einklappbar <details> (Standard)', 'bavarian-rank-engine' ); ?>
						</option>
						<option value="open_always" <?php selected( $settings['output_style'], 'open_always' ); ?>>
							<?php esc_html_e( 'Immer aufgeklappt', 'bavarian-rank-engine' ); ?>
						</option>
						<option value="store_only_no_frontend" <?php selected( $settings['output_style'], 'store_only_no_frontend' ); ?>>
							<?php esc_html_e( 'Nur speichern, nicht ausgeben', 'bavarian-rank-engine' ); ?>
						</option>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Labels', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Block-Titel', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="text" name="bre_geo_settings[title]"
						value="<?php echo esc_attr( $settings['title'] ); ?>"
						class="regular-text" placeholder="Schnellüberblick">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Label Kurzfassung', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="text" name="bre_geo_settings[label_summary]"
						value="<?php echo esc_attr( $settings['label_summary'] ); ?>"
						class="regular-text" placeholder="Kurzfassung">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Label Kernaussagen', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="text" name="bre_geo_settings[label_bullets]"
						value="<?php echo esc_attr( $settings['label_bullets'] ); ?>"
						class="regular-text" placeholder="Kernaussagen">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Label FAQ', 'bavarian-rank-engine' ); ?></th>
				<td>
					<input type="text" name="bre_geo_settings[label_faq]"
						value="<?php echo esc_attr( $settings['label_faq'] ); ?>"
						class="regular-text" placeholder="FAQ">
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Styling', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Minimal-CSS laden', 'bavarian-rank-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bre_geo_settings[minimal_css]" value="1"
							<?php checked( $settings['minimal_css'], true ); ?>>
						<?php esc_html_e( 'Basis-Stylesheet für .bre-geo auf dem Frontend laden', 'bavarian-rank-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Custom CSS', 'bavarian-rank-engine' ); ?></th>
				<td>
					<textarea name="bre_geo_settings[custom_css]" rows="6" class="large-text code"><?php
						echo esc_textarea( $settings['custom_css'] );
					?></textarea>
					<p class="description">
						<?php esc_html_e( 'Wird automatisch auf .bre-geo{...} begrenzt. Nur CSS-Eigenschaften eingeben, keinen Selektor.', 'bavarian-rank-engine' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'KI Prompt', 'bavarian-rank-engine' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Standard-Prompt', 'bavarian-rank-engine' ); ?></th>
				<td>
					<textarea name="bre_geo_settings[prompt_default]" rows="12" class="large-text code"><?php
						echo esc_textarea( $settings['prompt_default'] );
					?></textarea>
					<p class="description">
						<?php esc_html_e( 'Variablen: {title}, {content}, {language}', 'bavarian-rank-engine' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Prompt-Zusatz pro Beitrag', 'bavarian-rank-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bre_geo_settings[allow_prompt_addon]" value="1"
							<?php checked( $settings['allow_prompt_addon'], true ); ?>>
						<?php esc_html_e( 'Autoren dürfen im Editor einen Prompt-Zusatz pro Beitrag eingeben', 'bavarian-rank-engine' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Einstellungen speichern', 'bavarian-rank-engine' ) ); ?>
	</form>

	<hr>
	<p style="color:#999;font-size:12px;">
		Bavarian Rank Engine <?php echo esc_html( BRE_VERSION ); ?> &mdash;
		<?php esc_html_e( 'developed with', 'bavarian-rank-engine' ); ?> ♥
		<a href="https://donau2space.de" target="_blank" rel="noopener">Donau2Space.de</a>
	</p>
</div>
