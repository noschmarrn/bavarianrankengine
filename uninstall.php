<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
delete_option( 'seo_geo_settings' );
delete_post_meta_by_key( '_seo_geo_meta_description' );
