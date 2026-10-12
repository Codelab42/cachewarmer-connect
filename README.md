# Cache-Warmer.com connector

Connect WordPress to [Cache-Warmer.com](https://www.cache-warmer.com).

Cache-Warmer.com connector warms your site's cache right after it's cleared. When your page cache is purged, or when you publish, update or unpublish content, the plugin tells Cache-Warmer.com to start a warming run, so visitors never hit a cold cache.

## Features

- **Automatic triggers**
  - A full cache purge in WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache, Cache Enabler or Autoptimize
  - Publishing, updating, unpublishing or trashing any public post type
  - Switching themes, saving the Customizer, editing menus, and plugin or theme updates
- **Manual triggers**: a "Warm cache now" button, a "Warm cache" admin bar link, and `wp cachewarmer warm`
- **No duplicate runs**: multiple triggers in one request are combined into a single run. After a run, further triggers wait for a cooldown (5 minutes by default) and are then combined into one follow-up run.
- **Status overview** of the last run and its result on the settings page
- **Credentials in `wp-config.php`** if you'd rather keep them out of the database

## Requirements

- WordPress 5.6 or later
- PHP 7.4 or later
- A [Cache-Warmer.com](https://www.cache-warmer.com/user/register) account

## Installation

1. Download the latest release zip, or clone this repository into `wp-content/plugins/cache-warmer-com-connector`:

   ```sh
   git clone https://github.com/Codelab42/cachewarmer-connect.git wp-content/plugins/cache-warmer-com-connector
   ```

2. Activate **Cache-Warmer.com connector** under **Plugins**.
3. Go to **Settings → Cache-Warmer.com connector**.
4. Enter your project ID and secret, check **Enable Cache-Warmer integration** and save.
5. Click **Warm cache now** to test the connection.

> **Where do I find my project ID and secret?**
> Log in to Cache-Warmer.com and open your project. The "Webhook integration" section shows your project ID and secret.

## Configuration

| Setting | Default | Description |
|---|---|---|
| Enable Cache-Warmer integration | Off | Turns automatic triggers on. Manual warming works either way. |
| Project ID | | Your Cache-Warmer.com project ID. |
| Secret | | Your project's webhook secret. It is never shown again after saving. |
| Cache cleared | On | Warm when a supported cache plugin purges its whole cache. |
| Content changed | On | Warm when content, the theme, menus or the Customizer change. |
| Cooldown | 300 seconds | Minimum time between automatic runs. `0` disables it. |

### wp-config.php

Credentials defined as constants override the settings page:

```php
define( 'CACHEWARMER_PROJECT_ID', '123' );
define( 'CACHEWARMER_SECRET', 'your-secret' );
```

## WP-CLI

```sh
wp cachewarmer warm     # Start a warming run now, ignoring the cooldown
wp cachewarmer status   # Show configuration and the last run
```

## Developers

### Supporting another cache plugin

Fire the built-in action after clearing the cache:

```php
do_action( 'cachewarmer_connect_cache_cleared' );
```

Or add your plugin's own hook:

```php
add_filter( 'cachewarmer_connect_cache_purge_hooks', function ( $hooks ) {
	$hooks[] = 'my_cache_plugin_purged_all';
	return $hooks;
} );
```

### Hooks

| Hook | Type | Description |
|---|---|---|
| `cachewarmer_connect_cache_purge_hooks` | Filter | Action hooks that trigger a warm. |
| `cachewarmer_connect_capability` | Filter | Capability needed to configure and trigger warming. Default `manage_options`. |
| `cachewarmer_connect_triggered` | Action | Fires after each run is requested. Receives `$result` (`true` or `WP_Error`) and `$reason`. |
| `cachewarmer_connect_cache_cleared` | Action | Fire it to trigger a warm. |

## FAQ

**Follow-up runs after the cooldown don't happen.**
They're scheduled with WP-Cron. If you've set `DISABLE_WP_CRON`, make sure a system cron calls `wp-cron.php` regularly.

**What happens when I uninstall the plugin?**
All settings, the cooldown and any scheduled follow-up run are removed.

## License

[GPL-3.0-or-later](LICENSE)
