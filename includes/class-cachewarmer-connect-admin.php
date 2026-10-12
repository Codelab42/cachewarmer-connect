<?php
/**
 * Settings page, "Warm cache now" action and admin bar shortcut.
 *
 * @package CacheWarmer_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CacheWarmer_Connect_Admin.
 */
final class CacheWarmer_Connect_Admin {

	const PAGE   = 'cachewarmer-connect';
	const ACTION = 'cachewarmer_connect_warm';

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_warm_now' ) );
		add_action( 'admin_notices', array( __CLASS__, 'warm_now_notice' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 100 );
		add_filter( 'plugin_action_links_' . plugin_basename( CACHEWARMER_CONNECT_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Capability required to configure and trigger warming.
	 *
	 * @return string
	 */
	private static function capability() {
		return (string) apply_filters( 'cachewarmer_connect_capability', 'manage_options' );
	}

	/**
	 * Adds Settings > Cache-Warmer Connect.
	 */
	public static function add_page() {
		add_options_page(
			__( 'Cache-Warmer Connect', 'cachewarmer-connect' ),
			__( 'Cache-Warmer Connect', 'cachewarmer-connect' ),
			self::capability(),
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Adds a "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Action links.
	 *
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::page_url() ), esc_html__( 'Settings', 'cachewarmer-connect' ) ) );

		return $links;
	}

	/**
	 * URL of the settings page.
	 *
	 * @return string
	 */
	private static function page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	/**
	 * Nonce-protected URL that triggers a warm right away.
	 *
	 * @return string
	 */
	private static function warm_now_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
	}

	/**
	 * Registers the setting and its fields.
	 */
	public static function register_settings() {
		register_setting(
			self::PAGE,
			CacheWarmer_Connect::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => CacheWarmer_Connect::defaults(),
			)
		);

		add_settings_section( 'connection', __( 'Connection', 'cachewarmer-connect' ), array( __CLASS__, 'render_connection_intro' ), self::PAGE );
		add_settings_field( 'enabled', __( 'Status', 'cachewarmer-connect' ), array( __CLASS__, 'render_checkbox' ), self::PAGE, 'connection', array(
			'key'         => 'enabled',
			'label'       => __( 'Enable Cache-Warmer integration', 'cachewarmer-connect' ),
			'description' => __( 'When enabled, your cache is warmed automatically when your cache is cleared or content changes.', 'cachewarmer-connect' ),
		) );
		add_settings_field( 'project_id', __( 'Project ID', 'cachewarmer-connect' ), array( __CLASS__, 'render_text' ), self::PAGE, 'connection', array(
			'key'         => 'project_id',
			'description' => __( 'The project ID from your Cache-Warmer project page on cache-warmer.com.', 'cachewarmer-connect' ),
		) );
		add_settings_field( 'secret', __( 'Secret', 'cachewarmer-connect' ), array( __CLASS__, 'render_secret' ), self::PAGE, 'connection', array(
			'key'         => 'secret',
			'description' => __( 'The webhook secret from your Cache-Warmer project page on cache-warmer.com.', 'cachewarmer-connect' ),
		) );

		add_settings_section( 'triggers', __( 'Triggers', 'cachewarmer-connect' ), '__return_false', self::PAGE );
		add_settings_field( 'trigger_on_cache_purge', __( 'Cache cleared', 'cachewarmer-connect' ), array( __CLASS__, 'render_checkbox' ), self::PAGE, 'triggers', array(
			'key'         => 'trigger_on_cache_purge',
			'label'       => __( 'Warm when the page cache is cleared', 'cachewarmer-connect' ),
			'description' => __( 'Supports WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache, Cache Enabler and Autoptimize.', 'cachewarmer-connect' ),
		) );
		add_settings_field( 'trigger_on_content', __( 'Content changed', 'cachewarmer-connect' ), array( __CLASS__, 'render_checkbox' ), self::PAGE, 'triggers', array(
			'key'         => 'trigger_on_content',
			'label'       => __( 'Warm when content is published, updated or unpublished', 'cachewarmer-connect' ),
			'description' => __( 'Also covers theme switches, Customizer changes, menu edits and plugin/theme updates.', 'cachewarmer-connect' ),
		) );
		add_settings_field( 'cooldown', __( 'Cooldown', 'cachewarmer-connect' ), array( __CLASS__, 'render_cooldown' ), self::PAGE, 'triggers', array(
			'key' => 'cooldown',
		) );

		add_settings_section( 'advanced', __( 'Advanced', 'cachewarmer-connect' ), '__return_false', self::PAGE );
		add_settings_field( 'webhook_url', __( 'Webhook URL', 'cachewarmer-connect' ), array( __CLASS__, 'render_text' ), self::PAGE, 'advanced', array(
			'key'         => 'webhook_url',
			'type'        => 'url',
			/* translators: %s: default webhook URL. */
			'description' => sprintf( __( 'Only change this for testing. Default: %s', 'cachewarmer-connect' ), CacheWarmer_Connect::DEFAULT_WEBHOOK_URL ),
		) );
	}

	/**
	 * Sanitizes submitted settings.
	 *
	 * @param mixed $input Submitted values.
	 *
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$previous = wp_parse_args( (array) get_option( CacheWarmer_Connect::OPTION, array() ), CacheWarmer_Connect::defaults() );

		$secret = isset( $input['secret'] ) ? trim( sanitize_text_field( $input['secret'] ) ) : '';
		$url    = isset( $input['webhook_url'] ) ? esc_url_raw( trim( $input['webhook_url'] ), array( 'http', 'https' ) ) : '';

		return array(
			'enabled'                => ! empty( $input['enabled'] ),
			'project_id'             => isset( $input['project_id'] ) ? trim( sanitize_text_field( $input['project_id'] ) ) : '',
			// An empty secret field means "keep the stored secret"; it is never echoed back into the form.
			'secret'                 => '' === $secret ? $previous['secret'] : $secret,
			'webhook_url'            => '' === $url ? CacheWarmer_Connect::DEFAULT_WEBHOOK_URL : $url,
			'trigger_on_cache_purge' => ! empty( $input['trigger_on_cache_purge'] ),
			'trigger_on_content'     => ! empty( $input['trigger_on_content'] ),
			'cooldown'               => isset( $input['cooldown'] ) ? min( DAY_IN_SECONDS, absint( $input['cooldown'] ) ) : 300,
		);
	}

	/**
	 * Intro text for the connection section.
	 */
	public static function render_connection_intro() {
		echo '<p>' . esc_html__( 'Log in to cache-warmer.com and open your project. The "Webhook integration" section shows your project ID and secret.', 'cachewarmer-connect' ) . '</p>';
	}

	/**
	 * Field name attribute for a setting.
	 *
	 * @param string $key Setting name.
	 *
	 * @return string
	 */
	private static function field_name( $key ) {
		return CacheWarmer_Connect::OPTION . '[' . $key . ']';
	}

	/**
	 * Prints a field description, or a note when the value comes from wp-config.php.
	 *
	 * @param array $args Field arguments.
	 */
	private static function render_description( $args ) {
		if ( CacheWarmer_Connect::is_constant_defined( $args['key'] ) ) {
			/* translators: %s: constant name. */
			printf( '<p class="description">' . esc_html__( 'Set in wp-config.php via %s.', 'cachewarmer-connect' ) . '</p>', '<code>' . esc_html( CacheWarmer_Connect::CONSTANTS[ $args['key'] ] ) . '</code>' );
		} elseif ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Renders a text or URL field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_text( $args ) {
		printf(
			'<input type="%s" class="regular-text" id="%s" name="%s" value="%s" %s />',
			esc_attr( isset( $args['type'] ) ? $args['type'] : 'text' ),
			esc_attr( 'cachewarmer-' . $args['key'] ),
			esc_attr( self::field_name( $args['key'] ) ),
			esc_attr( CacheWarmer_Connect::get_setting( $args['key'] ) ),
			disabled( CacheWarmer_Connect::is_constant_defined( $args['key'] ), true, false )
		);
		self::render_description( $args );
	}

	/**
	 * Renders the secret field without exposing the stored value.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_secret( $args ) {
		$has_secret = '' !== (string) CacheWarmer_Connect::get_setting( 'secret' );

		printf(
			'<input type="password" class="regular-text" id="cachewarmer-secret" name="%s" value="" autocomplete="new-password" placeholder="%s" %s />',
			esc_attr( self::field_name( 'secret' ) ),
			esc_attr( $has_secret ? __( 'Saved. Leave empty to keep it.', 'cachewarmer-connect' ) : '' ),
			disabled( CacheWarmer_Connect::is_constant_defined( 'secret' ), true, false )
		);
		self::render_description( $args );
	}

	/**
	 * Renders a checkbox.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_checkbox( $args ) {
		printf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( self::field_name( $args['key'] ) ),
			checked( (bool) CacheWarmer_Connect::get_setting( $args['key'] ), true, false ),
			esc_html( $args['label'] )
		);
		self::render_description( $args );
	}

	/**
	 * Renders the cooldown field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_cooldown( $args ) {
		printf(
			'<input type="number" class="small-text" min="0" max="%d" step="1" name="%s" value="%d" /> %s',
			(int) DAY_IN_SECONDS,
			esc_attr( self::field_name( 'cooldown' ) ),
			(int) CacheWarmer_Connect::get_setting( 'cooldown' ),
			esc_html__( 'seconds', 'cachewarmer-connect' )
		);
		echo '<p class="description">' . esc_html__( 'Minimum time between automatic runs. Changes made during the cooldown are warmed in one extra run when it ends. Use 0 to disable.', 'cachewarmer-connect' ) . '</p>';
	}

	/**
	 * Renders the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cache-Warmer Connect', 'cachewarmer-connect' ); ?></h1>

			<?php self::render_status(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the status box with the last trigger and the "Warm cache now" button.
	 */
	private static function render_status() {
		$last = CacheWarmer_Connect::last_trigger();
		$next = wp_next_scheduled( CacheWarmer_Connect::CRON_HOOK );
		?>
		<div class="card" style="max-width: 640px;">
			<h2><?php esc_html_e( 'Status', 'cachewarmer-connect' ); ?></h2>
			<p>
				<?php
				if ( CacheWarmer_Connect::is_active() ) {
					esc_html_e( 'Automatic warming is active.', 'cachewarmer-connect' );
				} elseif ( CacheWarmer_Connect::is_configured() ) {
					esc_html_e( 'Automatic warming is disabled. You can still warm manually.', 'cachewarmer-connect' );
				} else {
					esc_html_e( 'Not configured yet. Enter your project ID and secret below.', 'cachewarmer-connect' );
				}
				?>
			</p>
			<?php if ( ! CacheWarmer_Connect::is_configured() ) : ?>
				<p>
					<?php esc_html_e( "Don't have a Cache-Warmer account yet?", 'cachewarmer-connect' ); ?>
					<a href="<?php echo esc_url( CacheWarmer_Connect::SIGNUP_URL ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create an account on cache-warmer.com', 'cachewarmer-connect' ); ?></a>
				</p>
			<?php endif; ?>
			<?php if ( $last ) : ?>
				<p>
					<strong><?php esc_html_e( 'Last trigger:', 'cachewarmer-connect' ); ?></strong>
					<?php
					/* translators: %s: human-readable time difference. */
					echo esc_html( sprintf( __( '%s ago', 'cachewarmer-connect' ), human_time_diff( $last['time'] ) ) );
					echo ' (' . esc_html( $last['reason'] ) . ') &mdash; ';
					printf( '<span style="color: %s;">%s</span>', $last['success'] ? '#008a20' : '#d63638', esc_html( $last['message'] ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( $next ) : ?>
				<p>
					<?php
					/* translators: %s: human-readable time difference. */
					echo esc_html( sprintf( __( 'A follow-up run is scheduled in %s.', 'cachewarmer-connect' ), human_time_diff( $next ) ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( CacheWarmer_Connect::is_configured() ) : ?>
				<p><a href="<?php echo esc_url( self::warm_now_url() ); ?>" class="button button-secondary"><?php esc_html_e( 'Warm cache now', 'cachewarmer-connect' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles the "Warm cache now" action.
	 */
	public static function handle_warm_now() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'cachewarmer-connect' ), 403 );
		}
		check_admin_referer( self::ACTION );

		$result = CacheWarmer_Connect::warm( 'manual' );

		$redirect = wp_get_referer() ? wp_get_referer() : self::page_url();
		wp_safe_redirect( add_query_arg( 'cachewarmer', is_wp_error( $result ) ? 'error' : 'success', $redirect ) );
		exit;
	}

	/**
	 * Shows the outcome of "Warm cache now".
	 */
	public static function warm_now_notice() {
		if ( ! isset( $_GET['cachewarmer'] ) || ! current_user_can( self::capability() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$last = CacheWarmer_Connect::last_trigger();
		if ( ! $last ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p><strong>Cache-Warmer Connect:</strong> %s</p></div>',
			$last['success'] ? 'success' : 'error',
			esc_html( $last['message'] )
		);
	}

	/**
	 * Adds a "Warm cache" shortcut to the admin bar.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar.
	 */
	public static function admin_bar( $admin_bar ) {
		if ( ! current_user_can( self::capability() ) || ! CacheWarmer_Connect::is_configured() ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'cachewarmer-connect',
				'title' => __( 'Warm cache', 'cachewarmer-connect' ),
				'href'  => self::warm_now_url(),
			)
		);
	}
}
