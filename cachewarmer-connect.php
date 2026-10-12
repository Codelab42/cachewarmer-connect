<?php
/**
 * Plugin Name:       Cache-Warmer Connect
 * Plugin URI:        https://www.cache-warmer.com
 * Description:       Integrates your site with cache-warmer.com. Clearing your cache or publishing content automatically schedules a cache warming run.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Cache-Warmer
 * Author URI:        https://www.cache-warmer.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       cachewarmer-connect
 *
 * @package CacheWarmer_Connect
 */

defined( 'ABSPATH' ) || exit;

define( 'CACHEWARMER_CONNECT_VERSION', '1.0.0' );
define( 'CACHEWARMER_CONNECT_FILE', __FILE__ );
define( 'CACHEWARMER_CONNECT_DIR', plugin_dir_path( __FILE__ ) );

require_once CACHEWARMER_CONNECT_DIR . 'includes/class-cachewarmer-connect.php';

require_once CACHEWARMER_CONNECT_DIR . 'includes/class-cachewarmer-connect-admin.php';

CacheWarmer_Connect::init();
CacheWarmer_Connect_Admin::init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once CACHEWARMER_CONNECT_DIR . 'includes/class-cachewarmer-connect-cli.php';
	WP_CLI::add_command( 'cachewarmer', 'CacheWarmer_Connect_CLI' );
}

register_deactivation_hook( __FILE__, array( 'CacheWarmer_Connect', 'deactivate' ) );
