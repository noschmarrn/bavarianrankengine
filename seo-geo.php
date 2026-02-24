<?php
/**
 * Plugin Name:       Bavarian Rank Engine
 * Plugin URI:        https://bavarianrankengine.com
 * Description:       AI-powered meta descriptions, GEO structured data, and llms.txt for WordPress.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            mifupadev
 * Author URI:        https://donau2space.de
 * License:           GPL-2.0-or-later
 * Text Domain:       bavarian-rank-engine
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BRE_VERSION', '1.2.0' );
define( 'BRE_FILE', __FILE__ );
define( 'BRE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRE_URL', plugin_dir_url( __FILE__ ) );

require_once BRE_DIR . 'includes/Core.php';

function bavarian_rank_engine_init(): void {
	\BavarianRankEngine\Core::instance()->init();
}
add_action( 'plugins_loaded', 'bavarian_rank_engine_init' );

register_activation_hook(
	BRE_FILE,
	function () {
		require_once BRE_DIR . 'includes/Features/RobotsTxt.php';
		require_once BRE_DIR . 'includes/Features/CrawlerLog.php';
		\BavarianRankEngine\Features\CrawlerLog::install();
		add_rewrite_rule( '^llms\.txt$', 'index.php?bre_llms=1', 'top' );
		flush_rewrite_rules();
	}
);
