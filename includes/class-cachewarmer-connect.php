<?php
/**
 * Core functionality: deciding when to warm and calling the cache-warmer.com webhook.
 *
 * @package CacheWarmer_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CacheWarmer_Connect.
 */
final class CacheWarmer_Connect {

	const OPTION              = 'cachewarmer_connect_settings';
	const STATUS_OPTION       = 'cachewarmer_connect_last_trigger';
	const COOLDOWN_TRANSIENT  = 'cachewarmer_connect_cooldown';
	const CRON_HOOK           = 'cachewarmer_connect_deferred_warm';
	const DEFAULT_WEBHOOK_URL = 'https://www.cache-warmer.com/webhook/cachewarmer';
	const SIGNUP_URL          = 'https://www.cache-warmer.com/user/register';

	/**
	 * Settings that can be locked down from wp-config.php instead of the database.
	 */
	const CONSTANTS = array(
		'project_id'  => 'CACHEWARMER_PROJECT_ID',
		'secret'      => 'CACHEWARMER_SECRET',
		'webhook_url' => 'CACHEWARMER_WEBHOOK_URL',
	);

	/**
	 * Reason for the warm queued during this request, if any.
	 *
	 * @var string|null
	 */
	private static $queued_reason = null;

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_deferred' ) );

		if ( ! self::is_active() ) {
			return;
		}

		if ( self::get_setting( 'trigger_on_cache_purge' ) ) {
			foreach ( self::cache_purge_hooks() as $hook ) {
				add_action( $hook, array( __CLASS__, 'on_cache_purged' ), 999, 0 );
			}
		}

		if ( self::get_setting( 'trigger_on_content' ) ) {
			add_action( 'transition_post_status', array( __CLASS__, 'on_post_status_transition' ), 999, 3 );
			add_action( 'switch_theme', array( __CLASS__, 'on_site_change' ), 999, 0 );
			add_action( 'customize_save_after', array( __CLASS__, 'on_site_change' ), 999, 0 );
			add_action( 'wp_update_nav_menu', array( __CLASS__, 'on_site_change' ), 999, 0 );
			add_action( 'upgrader_process_complete', array( __CLASS__, 'on_site_change' ), 999, 0 );
		}
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'                => false,
			'project_id'             => '',
			'secret'                 => '',
			'webhook_url'            => self::DEFAULT_WEBHOOK_URL,
			'trigger_on_cache_purge' => true,
			'trigger_on_content'     => true,
			'cooldown'               => 300,
		);
	}

	/**
	 * Returns a setting, preferring a wp-config.php constant when one is defined.
	 *
	 * @param string $key Setting name.
	 *
	 * @return mixed
	 */
	public static function get_setting( $key ) {
		if ( self::is_constant_defined( $key ) ) {
			return constant( self::CONSTANTS[ $key ] );
		}

		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Whether a setting is overridden by a wp-config.php constant.
	 *
	 * @param string $key Setting name.
	 *
	 * @return bool
	 */
	public static function is_constant_defined( $key ) {
		return isset( self::CONSTANTS[ $key ] ) && defined( self::CONSTANTS[ $key ] );
	}

	/**
	 * Whether the project ID and secret are both set.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== (string) self::get_setting( 'project_id' ) && '' !== (string) self::get_setting( 'secret' );
	}

	/**
	 * Whether automatic triggers are enabled and configured.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return (bool) self::get_setting( 'enabled' ) && self::is_configured();
	}

	/**
	 * Action hooks fired by popular cache plugins after a full cache purge.
	 *
	 * @return string[]
	 */
	public static function cache_purge_hooks() {
		$hooks = array(
			'after_rocket_clean_domain',             // WP Rocket.
			'w3tc_flush_all',                        // W3 Total Cache.
			'wp_cache_cleared',                      // WP Super Cache.
			'litespeed_purged_all',                  // LiteSpeed Cache.
			'wpfc_delete_cache',                     // WP Fastest Cache.
			'cache_enabler_complete_cache_cleared',  // Cache Enabler.
			'autoptimize_action_cachepurged',        // Autoptimize.
			'cachewarmer_connect_cache_cleared',     // Custom: do_action( 'cachewarmer_connect_cache_cleared' ).
		);

		/**
		 * Filters the action hooks that trigger a cache warm.
		 *
		 * @param string[] $hooks Action hook names.
		 */
		return (array) apply_filters( 'cachewarmer_connect_cache_purge_hooks', $hooks );
	}

	/**
	 * Callback for cache plugin purge hooks.
	 */
	public static function on_cache_purged() {
		self::queue( 'cache_purge:' . current_action() );
	}

	/**
	 * Queues a warm when publicly visible content is published, updated or unpublished.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public static function on_post_status_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		// The block editor saves legacy meta boxes in a second request right after the REST save.
		if ( isset( $_REQUEST['meta-box-loader'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		self::queue( 'content:' . $post->post_type );
	}

	/**
	 * Callback for site-wide changes (theme, menus, customizer, updates).
	 */
	public static function on_site_change() {
		self::queue( 'site_change:' . current_action() );
	}

	/**
	 * Queues a warm to be sent once at the end of the request.
	 *
	 * Bulk edits fire many hooks per request; they are coalesced into a single webhook call.
	 *
	 * @param string $reason Why the warm was requested.
	 */
	public static function queue( $reason ) {
		if ( null !== self::$queued_reason ) {
			return;
		}

		self::$queued_reason = $reason;
		add_action( 'shutdown', array( __CLASS__, 'send_queued' ), 999 );
	}

	/**
	 * Sends the queued warm, or defers it until the cooldown ends.
	 */
	public static function send_queued() {
		$reason              = self::$queued_reason;
		self::$queued_reason = null;

		if ( null === $reason ) {
			return;
		}

		$cooldown_until = (int) get_transient( self::COOLDOWN_TRANSIENT );
		if ( $cooldown_until > time() ) {
			// A run was just triggered. Warm once more when the cooldown ends so the latest changes get warmed too.
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( $cooldown_until + 1, self::CRON_HOOK );
			}
			return;
		}

		// Send the response to the visitor before making the HTTP call.
		if ( function_exists( 'fastcgi_finish_request' ) && 'cli' !== PHP_SAPI ) {
			fastcgi_finish_request();
		}

		self::warm( $reason );
	}

	/**
	 * Cron callback for warms deferred during the cooldown.
	 */
	public static function run_deferred() {
		if ( self::is_active() ) {
			self::warm( 'deferred' );
		}
	}

	/**
	 * Calls the cache-warmer.com webhook to start a new warming run.
	 *
	 * @param string $reason Why the warm was requested; stored for the status overview.
	 *
	 * @return true|\WP_Error
	 */
	public static function warm( $reason = 'manual' ) {
		if ( ! self::is_configured() ) {
			return self::record( $reason, new WP_Error( 'cachewarmer_not_configured', __( 'Enter your project ID and secret first.', 'codelab42-cache-warming-connect' ) ) );
		}

		$cooldown = (int) self::get_setting( 'cooldown' );
		if ( $cooldown > 0 ) {
			set_transient( self::COOLDOWN_TRANSIENT, time() + $cooldown, $cooldown );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );

		$response = wp_remote_post(
			self::get_setting( 'webhook_url' ),
			array(
				'timeout'     => 15,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'        => wp_json_encode(
					array(
						'id'     => (string) self::get_setting( 'project_id' ),
						'secret' => (string) self::get_setting( 'secret' ),
					)
				),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::record( $reason, $response );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return self::record( $reason, true );
		}

		if ( 403 === $code ) {
			$message = __( 'The request was rejected (HTTP 403). Check your project ID and secret, and that your cache-warmer.com subscription is active.', 'codelab42-cache-warming-connect' );
		} else {
			/* translators: %d: HTTP status code. */
			$message = sprintf( __( 'cache-warmer.com could not be reached (HTTP %d). Please try again later.', 'codelab42-cache-warming-connect' ), $code );
		}

		return self::record( $reason, new WP_Error( 'cachewarmer_http_' . $code, $message ) );
	}

	/**
	 * Stores the outcome of the last trigger and logs failures.
	 *
	 * @param string         $reason Why the warm was requested.
	 * @param true|\WP_Error $result Outcome.
	 *
	 * @return true|\WP_Error The given result.
	 */
	private static function record( $reason, $result ) {
		$success = ! is_wp_error( $result );

		update_option(
			self::STATUS_OPTION,
			array(
				'time'    => time(),
				'reason'  => $reason,
				'success' => $success,
				'message' => $success ? __( 'Cache warming triggered.', 'codelab42-cache-warming-connect' ) : $result->get_error_message(),
			),
			false
		);

		if ( ! $success && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'Codelab42 Cache Warming Connect: an error occurred when trying to warm the cache: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		/**
		 * Fires after the cache-warmer.com webhook was called.
		 *
		 * @param true|\WP_Error $result Outcome.
		 * @param string         $reason Why the warm was requested.
		 */
		do_action( 'cachewarmer_connect_triggered', $result, $reason );

		return $result;
	}

	/**
	 * Returns the outcome of the last trigger, if any.
	 *
	 * @return array|null
	 */
	public static function last_trigger() {
		$status = get_option( self::STATUS_OPTION );

		return is_array( $status ) ? $status : null;
	}

	/**
	 * Deactivation: drop pending deferred warms.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_transient( self::COOLDOWN_TRANSIENT );
	}
}
