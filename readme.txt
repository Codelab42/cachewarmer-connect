=== Cache-Warmer Connect ===
Tags: cache, cache warming, performance, page cache, preload
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Integrates your site with cache-warmer.com. Clearing your cache or publishing content automatically schedules a cache warming run.

== Description ==

Cache-Warmer Connect connects your WordPress site to the [cache-warmer.com](https://www.cache-warmer.com) service. When your page cache is cleared, or when you publish, update or unpublish content, the plugin tells cache-warmer.com to warm your site's cache right away, so visitors never hit a cold cache.

**Triggers**

* A full purge in WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache, Cache Enabler or Autoptimize.
* Publishing, updating, unpublishing or trashing any public post type.
* Switching themes, saving the Customizer, editing menus and installing plugin or theme updates.
* Manually: the "Warm cache now" button, the "Warm cache" admin bar link, or `wp cachewarmer warm`.

Multiple triggers in one request are combined into a single run. After a run, further automatic triggers wait for the cooldown (5 minutes by default) and are then combined into one follow-up run, so bulk edits don't trigger dozens of runs.

== Installation ==

1. Upload the `cachewarmer-connect` folder to `/wp-content/plugins/`, or upload the zip via Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to Settings > Cache-Warmer Connect, enter your project ID and secret, check "Enable Cache-Warmer integration" and save.
4. Click "Warm cache now" to test the connection.

To keep credentials out of the database, define them in `wp-config.php`:

    define( 'CACHEWARMER_PROJECT_ID', '123' );
    define( 'CACHEWARMER_SECRET', 'your-secret' );

== Frequently Asked Questions ==

= Where can I find my project ID and secret? =

Log in to your account on cache-warmer.com and open your project. The "Webhook integration" section shows your project ID and secret.

= My cache plugin isn't listed. =

Call `do_action( 'cachewarmer_connect_cache_cleared' );` after clearing the cache, or add your plugin's hook with the `cachewarmer_connect_cache_purge_hooks` filter.

= Follow-up runs after the cooldown don't happen. =

They're scheduled with WP-Cron. If you've set `DISABLE_WP_CRON`, make sure a system cron calls `wp-cron.php` regularly.

== Developers ==

* Filter `cachewarmer_connect_cache_purge_hooks`: the action hooks that trigger a warm.
* Filter `cachewarmer_connect_capability`: the capability needed to configure and trigger warming (default `manage_options`).
* Action `cachewarmer_connect_triggered( $result, $reason )`: fires after each webhook call. `$result` is `true` or a `WP_Error`.
* WP-CLI: `wp cachewarmer warm`, `wp cachewarmer status`.

== Changelog ==

= 1.0.0 =
* Initial release.
