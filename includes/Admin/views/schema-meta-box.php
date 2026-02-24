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
	<!-- More type fields will be added -->
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
