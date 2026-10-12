<?php
/**
 * Removes all Cache-Warmer Connect data.
 *
 * @package CacheWarmer_Connect
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the plugin's options, transient and scheduled event for the current site.
 */
function cachewarmer_connect_uninstall_site() {
	delete_option( 'cachewarmer_connect_settings' );
	delete_option( 'cachewarmer_connect_last_trigger' );
	delete_transient( 'cachewarmer_connect_cooldown' );
	wp_clear_scheduled_hook( 'cachewarmer_connect_deferred_warm' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $cachewarmer_site_id ) {
		switch_to_blog( $cachewarmer_site_id );
		cachewarmer_connect_uninstall_site();
		restore_current_blog();
	}
} else {
	cachewarmer_connect_uninstall_site();
}
