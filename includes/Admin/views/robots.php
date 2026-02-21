<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap bre-settings">
    <h1><?php esc_html_e( 'robots.txt — AI Bots', 'bavarian-rank-engine' ); ?></h1>
    <p>
        <?php esc_html_e( 'Bekannte AI-Bots für diese Website blockieren.', 'bavarian-rank-engine' ); ?>
        <strong><?php esc_html_e( 'Hinweis: Bots müssen sich nicht daran halten.', 'bavarian-rank-engine' ); ?></strong>
    </p>

    <form method="post" action="options.php">
        <?php settings_fields( 'bre_robots' ); ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'User-Agent', 'bavarian-rank-engine' ); ?></th>
                    <th><?php esc_html_e( 'Beschreibung', 'bavarian-rank-engine' ); ?></th>
                    <th style="width:80px;text-align:center;"><?php esc_html_e( 'Blockieren', 'bavarian-rank-engine' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( \BavarianRankEngine\Features\RobotsTxt::KNOWN_BOTS as $bot_key => $bot_label ) : ?>
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

        <?php submit_button( __( 'Einstellungen speichern', 'bavarian-rank-engine' ) ); ?>
    </form>

    <p>
        <a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e( 'Aktuelle robots.txt ansehen →', 'bavarian-rank-engine' ); ?>
        </a>
    </p>
</div>
