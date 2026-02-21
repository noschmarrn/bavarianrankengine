<?php
namespace BavarianRankEngine\Features;

class CrawlerLog {
    private const TABLE = 'bre_crawler_log';
    private const CRON  = 'bre_purge_crawler_log';

    public static function install(): void {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bot_name    VARCHAR(64)     NOT NULL,
            ip_hash     VARCHAR(64)     NOT NULL DEFAULT '',
            url         VARCHAR(512)    NOT NULL DEFAULT '',
            visited_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY bot_name (bot_name),
            KEY visited_at (visited_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function register(): void {
        add_action( 'init', [ $this, 'maybe_log' ], 1 );
        add_action( self::CRON, [ $this, 'purge_old' ] );

        if ( ! wp_next_scheduled( self::CRON ) ) {
            wp_schedule_event( time(), 'weekly', self::CRON );
        }
    }

    public function maybe_log(): void {
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ( empty( $ua ) ) return;

        $bot = $this->detect_bot( $ua );
        if ( null === $bot ) return;

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'bot_name'   => $bot,
                'ip_hash'    => hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' ),
                'url'        => mb_substr( $_SERVER['REQUEST_URI'] ?? '', 0, 512 ),
                'visited_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );
    }

    private function detect_bot( string $ua ): ?string {
        foreach ( array_keys( RobotsTxt::KNOWN_BOTS ) as $bot ) {
            if ( false !== stripos( $ua, $bot ) ) {
                return $bot;
            }
        }
        return null;
    }

    public function purge_old(): void {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}" . self::TABLE .
            " WHERE visited_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }

    public static function get_recent_summary( int $days = 30 ): array {
        global $wpdb;
        if ( ! isset( $wpdb ) ) return [];
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT bot_name, COUNT(*) as visits, MAX(visited_at) as last_seen
             FROM {$wpdb->prefix}" . self::TABLE . "
             WHERE visited_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY bot_name
             ORDER BY visits DESC",
            $days
        ), ARRAY_A ) ?: [];
    }
}
