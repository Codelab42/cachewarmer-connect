<?php
/**
 * WP-CLI commands.
 *
 * @package CacheWarmer_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Trigger and inspect cache-warmer.com runs.
 */
class CacheWarmer_Connect_CLI {

	/**
	 * Schedules a new cache warming run right away, ignoring the cooldown.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cachewarmer warm
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function warm( $args, $assoc_args ) {
		$result = CacheWarmer_Connect::warm( 'cli' );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( 'Cache warming triggered.' );
	}

	/**
	 * Shows the configuration status and the last trigger.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cachewarmer status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		$last = CacheWarmer_Connect::last_trigger();
		$next = wp_next_scheduled( CacheWarmer_Connect::CRON_HOOK );

		$rows = array(
			array( 'field' => 'configured', 'value' => CacheWarmer_Connect::is_configured() ? 'yes' : 'no' ),
			array( 'field' => 'active', 'value' => CacheWarmer_Connect::is_active() ? 'yes' : 'no' ),
			array( 'field' => 'project_id', 'value' => (string) CacheWarmer_Connect::get_setting( 'project_id' ) ),
			array( 'field' => 'webhook_url', 'value' => (string) CacheWarmer_Connect::get_setting( 'webhook_url' ) ),
			array( 'field' => 'last_trigger', 'value' => $last ? gmdate( 'Y-m-d H:i:s', $last['time'] ) . ' UTC (' . $last['reason'] . ')' : '-' ),
			array( 'field' => 'last_result', 'value' => $last ? $last['message'] : '-' ),
			array( 'field' => 'next_deferred_run', 'value' => $next ? gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' : '-' ),
		);

		WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value' ) );
	}
}
