<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
delete_option( 'bre_settings' );
delete_post_meta_by_key( '_bre_meta_description' );
