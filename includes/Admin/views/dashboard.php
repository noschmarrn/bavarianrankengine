<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap bre-settings">
	<h1><?php esc_html_e( 'Bavarian Rank Engine — Dashboard', 'bavarian-rank-engine' ); ?></h1>

	<div class="notice notice-info inline" style="margin:16px 0 0;padding:10px 14px;">
		<p style="margin:0;">&#128075; <?php esc_html_e( 'New here?', 'bavarian-rank-engine' ); ?>
			<strong><a href="<?php echo esc_url( 'https://bavarianrankengine.com/howto.html' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Read the setup guide — get running in five minutes →', 'bavarian-rank-engine' ); ?></a></strong>
		</p>
	</div>

	<div class="bre-dashboard-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:20px;">

		<div class="postbox">
			<div class="postbox-header"><h2><?php esc_html_e( 'Meta Coverage', 'bavarian-rank-engine' ); ?></h2></div>
			<div class="inside">
				<?php if ( empty( $meta_stats ) ) : ?>
					<p><?php esc_html_e( 'No post types configured.', 'bavarian-rank-engine' ); ?></p>
				<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Post Type', 'bavarian-rank-engine' ); ?></th>
						<th><?php esc_html_e( 'Published', 'bavarian-rank-engine' ); ?></th>
						<th><?php esc_html_e( 'With Meta', 'bavarian-rank-engine' ); ?></th>
						<th><?php esc_html_e( 'Coverage', 'bavarian-rank-engine' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $meta_stats as $pt => $stat ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $pt ); ?></strong></td>
							<td><?php echo esc_html( $stat['total'] ); ?></td>
							<td><?php echo esc_html( $stat['with_meta'] ); ?></td>
							<td><?php echo esc_html( $stat['pct'] ); ?>%</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
		</div>

		<div class="postbox">
			<div class="postbox-header"><h2><?php esc_html_e( 'Quick Links', 'bavarian-rank-engine' ); ?></h2></div>
			<div class="inside">
				<ul style="margin:0;padding:0 0 0 20px;">
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-provider' ) ); ?>"><?php esc_html_e( 'AI Provider Settings', 'bavarian-rank-engine' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-meta' ) ); ?>"><?php esc_html_e( 'Meta Generator Settings', 'bavarian-rank-engine' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-llms' ) ); ?>">llms.txt</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-bulk' ) ); ?>"><?php esc_html_e( 'Bulk Generator', 'bavarian-rank-engine' ); ?></a></li>
					<li><a href="<?php echo esc_url( 'https://bavarianrankengine.com/howto.html' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Documentation &amp; How To', 'bavarian-rank-engine' ); ?></a></li>
				</ul>
			</div>
		</div>

		<div class="postbox">
			<div class="postbox-header"><h2><?php esc_html_e( 'Status', 'bavarian-rank-engine' ); ?></h2></div>
			<div class="inside">
				<p><strong><?php esc_html_e( 'Version:', 'bavarian-rank-engine' ); ?></strong> <?php echo esc_html( BRE_VERSION ); ?></p>
				<p><strong><?php esc_html_e( 'Active Provider:', 'bavarian-rank-engine' ); ?></strong> <?php echo esc_html( $provider ); ?></p>
			</div>
		</div>

		<?php if ( ! empty( $bre_compat ) ) : ?>
		<div class="postbox">
			<div class="postbox-header"><h2><?php esc_html_e( 'Plugin Compatibility', 'bavarian-rank-engine' ); ?></h2></div>
			<div class="inside">
				<p style="color:#666;margin-top:0;"><?php esc_html_e( 'The following SEO plugins were detected. BRE adapts automatically.', 'bavarian-rank-engine' ); ?></p>
				<?php foreach ( $bre_compat as $plugin ) : ?>
				<p style="margin-bottom:4px;"><strong><?php echo esc_html( $plugin['name'] ); ?></strong></p>
				<ul style="margin:0 0 12px 20px;">
					<?php foreach ( $plugin['notes'] as $note ) : ?>
					<li><?php echo esc_html( $note ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="postbox">
			<div class="postbox-header"><h2><?php esc_html_e( 'Internal Link Analysis', 'bavarian-rank-engine' ); ?></h2></div>
			<div class="inside" id="bre-link-analysis-content">
				<em><?php esc_html_e( 'Loading…', 'bavarian-rank-engine' ); ?></em>
			</div>
		</div>

		<div class="postbox">
			<div class="postbox-header"><h2><?php esc_html_e( 'AI Crawlers — Last 30 Days', 'bavarian-rank-engine' ); ?></h2></div>
			<div class="inside">
				<?php $crawlers = \BavarianRankEngine\Features\CrawlerLog::get_recent_summary( 30 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
				<?php if ( empty( $crawlers ) ) : ?>
					<p><?php esc_html_e( 'No AI crawlers recorded yet.', 'bavarian-rank-engine' ); ?></p>
				<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Bot', 'bavarian-rank-engine' ); ?></th>
						<th><?php esc_html_e( 'Visits', 'bavarian-rank-engine' ); ?></th>
						<th><?php esc_html_e( 'Last Seen', 'bavarian-rank-engine' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $crawlers as $row ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
					<tr>
						<td><code><?php echo esc_html( $row['bot_name'] ); ?></code></td>
						<td><?php echo esc_html( $row['visits'] ); ?></td>
						<td><?php echo esc_html( $row['last_seen'] ); ?></td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
		</div>

	</div>

	<script>
	var breL10n = <?php echo wp_json_encode( array(
		'analysisError'    => __( 'Analysis error.', 'bavarian-rank-engine' ),
		'noLinksHeading'   => __( 'Posts without internal links', 'bavarian-rank-engine' ),
		'allLinked'        => __( 'All posts have internal links.', 'bavarian-rank-engine' ),
		'manyExternalPre'  => __( 'Posts with many external links (≥', 'bavarian-rank-engine' ),
		'noExternalIssues' => __( 'No posts with excessive external links.', 'bavarian-rank-engine' ),
		'pillarHeading'    => __( 'Pillar Pages (Top 5)', 'bavarian-rank-engine' ),
		'noData'           => __( 'No data.', 'bavarian-rank-engine' ),
		'connectionError'  => __( 'Connection error.', 'bavarian-rank-engine' ),
	) ); ?>;
	jQuery(function($){
		$.post(ajaxurl, {
			action: 'bre_link_analysis',
			nonce: '<?php echo esc_js( wp_create_nonce( 'bre_admin' ) ); ?>'
		}).done(function(res){
			if(!res.success){
				$('#bre-link-analysis-content').text(breL10n.analysisError);
				return;
			}
			var d=res.data, h='';
			h+='<p><strong>'+breL10n.noLinksHeading+' ('+d.no_internal_links.length+')</strong></p>';
			if(d.no_internal_links.length){
				h+='<ul style="margin:0 0 10px 20px;">';
				$.each(d.no_internal_links.slice(0,10),function(i,p){
					h+='<li>'+$('<span>').text(p.title).html()+'</li>';
				});
				if(d.no_internal_links.length>10) h+='<li>…</li>';
				h+='</ul>';
			} else { h+='<p>'+breL10n.allLinked+'</p>'; }

			h+='<p><strong>'+breL10n.manyExternalPre+d.threshold+')</strong></p>';
			if(d.too_many_external.length){
				h+='<ul style="margin:0 0 10px 20px;">';
				$.each(d.too_many_external.slice(0,5),function(i,p){
					h+='<li>'+$('<span>').text(p.title).html()+' ('+p.count+')</li>';
				});
				h+='</ul>';
			} else { h+='<p>'+breL10n.noExternalIssues+'</p>'; }

			h+='<p><strong>'+breL10n.pillarHeading+'</strong></p>';
			if(d.pillar_pages.length){
				h+='<ul style="margin:0 0 10px 20px;">';
				$.each(d.pillar_pages,function(i,p){
					h+='<li><a href="'+$('<span>').text(p.url).html()+'" target="_blank">'+$('<span>').text(p.url).html()+'</a> ('+p.count+'x)</li>';
				});
				h+='</ul>';
			} else { h+='<p>'+breL10n.noData+'</p>'; }

			$('#bre-link-analysis-content').html(h);
		}).fail(function(){
			$('#bre-link-analysis-content').text(breL10n.connectionError);
		});
	});
	</script>

	<p class="bre-footer">
		Bavarian Rank Engine <?php echo esc_html( BRE_VERSION ); ?> &mdash;
		<?php esc_html_e( 'developed with', 'bavarian-rank-engine' ); ?> ♥
		<a href="https://donau2space.de" target="_blank" rel="noopener">Donau2Space.de</a>
	</p>
</div>
