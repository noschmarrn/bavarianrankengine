<?php
/**
 * Plugin Name: SEO & GEO Tools
 * Plugin URI:  https://donau2space.de
 * Description: AI-powered meta descriptions and GEO-optimized structured data for any WordPress site.
 * Version:     1.0.0
 * Author:      Donau2Space
 * Author URI:  https://donau2space.de
 * License:     GPL-2.0-or-later
 * Text Domain: seo-geo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SEO_GEO_VERSION', '1.0.0' );
define( 'SEO_GEO_FILE',    __FILE__ );
define( 'SEO_GEO_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SEO_GEO_URL',     plugin_dir_url( __FILE__ ) );

require_once SEO_GEO_DIR . 'includes/Core.php';

function seo_geo_init(): void {
    \SeoGeo\Core::instance()->init();
}
add_action( 'plugins_loaded', 'seo_geo_init' );
