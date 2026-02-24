<?php
/**
 * BRE Schema Metabox view.
 *
 * Variables available: $type (string), $data (array), $enabled (array).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="bre-schema-metabox">
	<p>
		<label for="bre-schema-type"><strong><?php esc_html_e( 'Schema-Typ', 'bavarian-rank-engine' ); ?></strong></label><br>
		<select name="bre_schema[schema_type]" id="bre-schema-type">
			<option value="" <?php selected( $type, '' ); ?>><?php esc_html_e( '— Kein Schema —', 'bavarian-rank-engine' ); ?></option>
			<?php if ( in_array( 'howto', $enabled, true ) ) : ?>
			<option value="howto" <?php selected( $type, 'howto' ); ?>><?php esc_html_e( 'HowTo Anleitung', 'bavarian-rank-engine' ); ?></option>
			<?php endif; ?>
			<?php if ( in_array( 'review', $enabled, true ) ) : ?>
			<option value="review" <?php selected( $type, 'review' ); ?>><?php esc_html_e( 'Review / Bewertung', 'bavarian-rank-engine' ); ?></option>
			<?php endif; ?>
			<?php if ( in_array( 'recipe', $enabled, true ) ) : ?>
			<option value="recipe" <?php selected( $type, 'recipe' ); ?>><?php esc_html_e( 'Rezept', 'bavarian-rank-engine' ); ?></option>
			<?php endif; ?>
			<?php if ( in_array( 'event', $enabled, true ) ) : ?>
			<option value="event" <?php selected( $type, 'event' ); ?>><?php esc_html_e( 'Event', 'bavarian-rank-engine' ); ?></option>
			<?php endif; ?>
		</select>
	</p>
	<!-- HowTo fields -->
	<div class="bre-schema-fields" data-bre-type="howto" style="display:none;">
		<p>
			<label><strong><?php esc_html_e( 'Name der Anleitung', 'bavarian-rank-engine' ); ?></strong><br>
			<input type="text" name="bre_schema[howto_name]"
				value="<?php echo esc_attr( $data['howto']['name'] ?? '' ); ?>"
				class="widefat"></label>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Schritte (eine Zeile = ein Schritt)', 'bavarian-rank-engine' ); ?></strong><br>
			<textarea name="bre_schema[howto_steps]" rows="5" class="widefat"><?php
				echo esc_textarea( implode( "\n", $data['howto']['steps'] ?? array() ) );
			?></textarea></label>
		</p>
	</div>
	<!-- Review fields -->
	<div class="bre-schema-fields" data-bre-type="review" style="display:none;">
		<p>
			<label><strong><?php esc_html_e( 'Bewertetes Produkt / Dienst', 'bavarian-rank-engine' ); ?></strong><br>
			<input type="text" name="bre_schema[review_item]"
				value="<?php echo esc_attr( $data['review']['item'] ?? '' ); ?>"
				class="widefat"></label>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Bewertung (1–5)', 'bavarian-rank-engine' ); ?></strong><br>
			<input type="number" name="bre_schema[review_rating]" min="1" max="5" step="1"
				value="<?php echo esc_attr( $data['review']['rating'] ?? 3 ); ?>"
				style="width:60px;"></label>
		</p>
	</div>
	<!-- Recipe fields -->
	<div class="bre-schema-fields" data-bre-type="recipe" style="display:none;">
		<p>
			<label><strong><?php esc_html_e( 'Rezeptname', 'bavarian-rank-engine' ); ?></strong><br>
			<input type="text" name="bre_schema[recipe_name]"
				value="<?php echo esc_attr( $data['recipe']['name'] ?? '' ); ?>"
				class="widefat"></label>
		</p>
		<p style="display:flex;gap:8px;">
			<label style="flex:1;"><?php esc_html_e( 'Vorbereitung (Min)', 'bavarian-rank-engine' ); ?><br>
			<input type="number" name="bre_schema[recipe_prep]" min="0"
				value="<?php echo esc_attr( $data['recipe']['prep'] ?? '' ); ?>"
				style="width:100%;"></label>
			<label style="flex:1;"><?php esc_html_e( 'Kochzeit (Min)', 'bavarian-rank-engine' ); ?><br>
			<input type="number" name="bre_schema[recipe_cook]" min="0"
				value="<?php echo esc_attr( $data['recipe']['cook'] ?? '' ); ?>"
				style="width:100%;"></label>
		</p>
		<p>
			<label><?php esc_html_e( 'Portionen', 'bavarian-rank-engine' ); ?><br>
			<input type="text" name="bre_schema[recipe_servings]"
				value="<?php echo esc_attr( $data['recipe']['servings'] ?? '' ); ?>"
				class="widefat"></label>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Zutaten (eine pro Zeile)', 'bavarian-rank-engine' ); ?></strong><br>
			<textarea name="bre_schema[recipe_ingredients]" rows="4" class="widefat"><?php
				echo esc_textarea( implode( "\n", $data['recipe']['ingredients'] ?? array() ) );
			?></textarea></label>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Anleitung (ein Schritt pro Zeile)', 'bavarian-rank-engine' ); ?></strong><br>
			<textarea name="bre_schema[recipe_instructions]" rows="5" class="widefat"><?php
				echo esc_textarea( implode( "\n", $data['recipe']['instructions'] ?? array() ) );
			?></textarea></label>
		</p>
	</div>
	<!-- Event fields -->
	<div class="bre-schema-fields" data-bre-type="event" style="display:none;">
		<p>
			<label><strong><?php esc_html_e( 'Event-Name', 'bavarian-rank-engine' ); ?></strong><br>
			<input type="text" name="bre_schema[event_name]"
				value="<?php echo esc_attr( $data['event']['name'] ?? '' ); ?>"
				class="widefat"></label>
		</p>
		<p>
			<label><?php esc_html_e( 'Startdatum', 'bavarian-rank-engine' ); ?><br>
			<input type="date" name="bre_schema[event_start]"
				value="<?php echo esc_attr( $data['event']['start'] ?? '' ); ?>"></label>
		</p>
		<p>
			<label><?php esc_html_e( 'Enddatum (optional)', 'bavarian-rank-engine' ); ?><br>
			<input type="date" name="bre_schema[event_end]"
				value="<?php echo esc_attr( $data['event']['end'] ?? '' ); ?>"></label>
		</p>
		<p>
			<label><?php esc_html_e( 'Ort oder URL', 'bavarian-rank-engine' ); ?><br>
			<input type="text" name="bre_schema[event_location]"
				value="<?php echo esc_attr( $data['event']['location'] ?? '' ); ?>"
				class="widefat"></label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="bre_schema[event_online]" value="1"
					<?php checked( ! empty( $data['event']['online'] ) ); ?>>
				<?php esc_html_e( 'Online-Event', 'bavarian-rank-engine' ); ?>
			</label>
		</p>
	</div>
</div>
<script>
(function () {
	var sel = document.getElementById( 'bre-schema-type' );
	function toggle() {
		document.querySelectorAll( '.bre-schema-fields' ).forEach( function ( el ) {
			el.style.display = el.dataset.breType === sel.value ? '' : 'none';
		} );
	}
	if ( sel ) {
		sel.addEventListener( 'change', toggle );
		toggle();
	}
}());
</script>
