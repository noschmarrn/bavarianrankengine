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

    </div>
</div>
