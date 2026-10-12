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

/**
 * Removes the plugin's data from every site.
 */
function cachewarmer_connect_uninstall() {
	if ( ! is_multisite() ) {
		cachewarmer_connect_uninstall_site();
		return;
	}

	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		cachewarmer_connect_uninstall_site();
		restore_current_blog();
	}
}

cachewarmer_connect_uninstall();
