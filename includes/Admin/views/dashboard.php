<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap bre-settings">
    <h1><?php esc_html_e( 'Bavarian Rank Engine — Dashboard', 'bavarian-rank-engine' ); ?></h1>

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

        <div class="postbox">
            <div class="postbox-header"><h2><?php esc_html_e( 'Interne Link-Analyse', 'bavarian-rank-engine' ); ?></h2></div>
            <div class="inside" id="bre-link-analysis-content">
                <em><?php esc_html_e( 'Wird geladen…', 'bavarian-rank-engine' ); ?></em>
            </div>
        </div>

        <div class="postbox">
            <div class="postbox-header"><h2><?php esc_html_e( 'AI Crawler — letzte 30 Tage', 'bavarian-rank-engine' ); ?></h2></div>
            <div class="inside">
                <?php $crawlers = \BavarianRankEngine\Features\CrawlerLog::get_recent_summary( 30 ); ?>
                <?php if ( empty( $crawlers ) ) : ?>
                    <p><?php esc_html_e( 'Noch keine AI-Crawls aufgezeichnet.', 'bavarian-rank-engine' ); ?></p>
                <?php else : ?>
                <table class="widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e( 'Bot', 'bavarian-rank-engine' ); ?></th>
                        <th><?php esc_html_e( 'Besuche', 'bavarian-rank-engine' ); ?></th>
                        <th><?php esc_html_e( 'Zuletzt', 'bavarian-rank-engine' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $crawlers as $row ) : ?>
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
    jQuery(function($){
        $.post(ajaxurl, {
            action: 'bre_link_analysis',
            nonce: '<?php echo esc_js( wp_create_nonce( 'bre_admin' ) ); ?>'
        }).done(function(res){
            if(!res.success){
                $('#bre-link-analysis-content').text('Analysefehler.');
                return;
            }
            var d=res.data, h='';
            h+='<p><strong>Posts ohne interne Links ('+d.no_internal_links.length+')</strong></p>';
            if(d.no_internal_links.length){
                h+='<ul style="margin:0 0 10px 20px;">';
                $.each(d.no_internal_links.slice(0,10),function(i,p){
                    h+='<li>'+$('<span>').text(p.title).html()+'</li>';
                });
                if(d.no_internal_links.length>10) h+='<li>…</li>';
                h+='</ul>';
            } else { h+='<p>Alle Posts haben interne Links.</p>'; }

            h+='<p><strong>Posts mit vielen externen Links (≥'+d.threshold+')</strong></p>';
            if(d.too_many_external.length){
                h+='<ul style="margin:0 0 10px 20px;">';
                $.each(d.too_many_external.slice(0,5),function(i,p){
                    h+='<li>'+$('<span>').text(p.title).html()+' ('+p.count+')</li>';
                });
                h+='</ul>';
            } else { h+='<p>Keine auffälligen Posts.</p>'; }

            h+='<p><strong>Pillar Pages (Top 5)</strong></p>';
            if(d.pillar_pages.length){
                h+='<ul style="margin:0 0 10px 20px;">';
                $.each(d.pillar_pages,function(i,p){
                    h+='<li><a href="'+$('<span>').text(p.url).html()+'" target="_blank">'+$('<span>').text(p.url).html()+'</a> ('+p.count+'x)</li>';
                });
                h+='</ul>';
            } else { h+='<p>Keine Daten.</p>'; }

            $('#bre-link-analysis-content').html(h);
        }).fail(function(){
            $('#bre-link-analysis-content').text('Verbindungsfehler.');
        });
    });
    </script>
</div>
