<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div id="bre-link-suggest-box">
	<div style="display:flex;align-items:center;justify-content:space-between;">
		<span style="color:#888;font-size:12px;" id="bre-ls-status">
			<?php esc_html_e( 'Click Analyse to find internal link opportunities.', 'bavarian-rank-engine' ); ?>
		</span>
		<button type="button" id="bre-ls-analyse" class="button">
			<?php esc_html_e( 'Analyse', 'bavarian-rank-engine' ); ?>
		</button>
	</div>

	<div id="bre-ls-results" style="display:none;margin-top:10px;">
		<div id="bre-ls-list"></div>
		<div id="bre-ls-actions" style="display:none;margin-top:8px;align-items:center;gap:8px;flex-wrap:wrap;">
			<button type="button" id="bre-ls-select-all" class="button button-small">
				<?php esc_html_e( 'All', 'bavarian-rank-engine' ); ?>
			</button>
			<button type="button" id="bre-ls-select-none" class="button button-small">
				<?php esc_html_e( 'None', 'bavarian-rank-engine' ); ?>
			</button>
			<button type="button" id="bre-ls-apply" class="button button-primary" style="margin-left:auto;" disabled>
				<?php esc_html_e( 'Apply (0 links)', 'bavarian-rank-engine' ); ?>
			</button>
		</div>
	</div>

	<div id="bre-ls-applied" style="display:none;color:#46b450;margin-top:8px;font-size:12px;"></div>
</div>
